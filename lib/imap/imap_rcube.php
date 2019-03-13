<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide alternative IMAP library that doesn't rely on the standard  |
 |   C-Client based version. This allows to function regardless          |
 |   of whether or not the PHP build it's running on has IMAP            |
 |   functionality built-in.                                             |
 |                                                                       |
 |   Based on Iloha IMAP Library. See http://ilohamail.org/ for details  |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Ryo Chijiiwa <Ryo@IlohaMail.org>                              |
 +-----------------------------------------------------------------------+
*/

/**
 * PHP based wrapper class to connect to an IMAP server
 *
 * @package    Framework
 * @subpackage Storage
 */

namespace OCA\user_external\imap;

class imap_rcube
{
    public $error;
    public $errornum;
    public $result;
    public $resultcode;
    public $selected;
    public $data = array();
    public $flags = array(
        'SEEN'     => '\\Seen',
        'DELETED'  => '\\Deleted',
        'ANSWERED' => '\\Answered',
        'DRAFT'    => '\\Draft',
        'FLAGGED'  => '\\Flagged',
        'FORWARDED' => '$Forwarded',
        'MDNSENT'  => '$MDNSent',
        '*'        => '\\*',
    );

    protected $fp;
    protected $host;
    protected $cmd_tag;
    protected $cmd_num = 0;
    protected $resourceid;
    protected $prefs             = array();
    protected $logged            = false;
    protected $capability        = array();
    protected $capability_readed = false;
    protected $debug             = false;
    protected $debug_handler     = false;

    const ERROR_OK       = 0;
    const ERROR_NO       = -1;
    const ERROR_BAD      = -2;
    const ERROR_BYE      = -3;
    const ERROR_UNKNOWN  = -4;
    const ERROR_COMMAND  = -5;
    const ERROR_READONLY = -6;

    const COMMAND_NORESPONSE = 1;
    const COMMAND_CAPABILITY = 2;
    const COMMAND_LASTLINE   = 4;
    const COMMAND_ANONYMIZED = 8;

    const DEBUG_LINE_LENGTH = 4098; // 4KB + 2B for \r\n


    /**
     * Send simple (one line) command to the connection stream
     *
     * @param string $string     Command string
     * @param bool   $endln      True if CRLF need to be added at the end of command
     * @param bool   $anonymized Don't write the given data to log but a placeholder
     *
     * @param int Number of bytes sent, False on error
     */
    protected function putLine($string, $endln = true, $anonymized = false)
    {
        if (!$this->fp) {
            return false;
        }

        if ($this->debug) {
            // anonymize the sent command for logging
            $cut = $endln ? 2 : 0;
            if ($anonymized && preg_match('/^(A\d+ (?:[A-Z]+ )+)(.+)/', $string, $m)) {
                $log = $m[1] . sprintf('****** [%d]', strlen($m[2]) - $cut);
            }
            else if ($anonymized) {
                $log = sprintf('****** [%d]', strlen($string) - $cut);
            }
            else {
                $log = rtrim($string);
            }

            $this->debug('C: ' . $log);
        }

        if ($endln) {
            $string .= "\r\n";
        }

        $res = fwrite($this->fp, $string);

        if ($res === false) {
            $this->closeSocket();
        }

        return $res;
    }

    /**
     * Send command to the connection stream with Command Continuation
     * Requests (RFC3501 7.5) and LITERAL+ (RFC2088) support
     *
     * @param string $string     Command string
     * @param bool   $endln      True if CRLF need to be added at the end of command
     * @param bool   $anonymized Don't write the given data to log but a placeholder
     *
     * @return int|bool Number of bytes sent, False on error
     */
    protected function putLineC($string, $endln=true, $anonymized=false)
    {
        if (!$this->fp) {
            return false;
        }

        if ($endln) {
            $string .= "\r\n";
        }

        $res = 0;
        if ($parts = preg_split('/(\{[0-9]+\}\r\n)/m', $string, -1, PREG_SPLIT_DELIM_CAPTURE)) {
            for ($i=0, $cnt=count($parts); $i<$cnt; $i++) {
                if (preg_match('/^\{([0-9]+)\}\r\n$/', $parts[$i+1], $matches)) {
                    // LITERAL+ support
                    if ($this->prefs['literal+']) {
                        $parts[$i+1] = sprintf("{%d+}\r\n", $matches[1]);
                    }

                    $bytes = $this->putLine($parts[$i].$parts[$i+1], false, $anonymized);
                    if ($bytes === false) {
                        return false;
                    }

                    $res += $bytes;

                    // don't wait if server supports LITERAL+ capability
                    if (!$this->prefs['literal+']) {
                        $line = $this->readLine(1000);
                        // handle error in command
                        if ($line[0] != '+') {
                            return false;
                        }
                    }

                    $i++;
                }
                else {
                    $bytes = $this->putLine($parts[$i], false, $anonymized);
                    if ($bytes === false) {
                        return false;
                    }

                    $res += $bytes;
                }
            }
        }

        return $res;
    }

    /**
     * Reads line from the connection stream
     *
     * @param int $size Buffer size
     *
     * @return string Line of text response
     */
    protected function readLine($size = 1024)
    {
        $line = '';

        if (!$size) {
            $size = 1024;
        }

        do {
            if ($this->eof()) {
                return $line ?: null;
            }

            $buffer = fgets($this->fp, $size);

            if ($buffer === false) {
                $this->closeSocket();
                break;
            }

            if ($this->debug) {
                $this->debug('S: '. rtrim($buffer));
            }

            $line .= $buffer;
        }
        while (substr($buffer, -1) != "\n");

        return $line;
    }

    /**
     * Reads more data from the connection stream when provided
     * data contain string literal
     *
     * @param string  $line    Response text
     * @param bool    $escape  Enables escaping
     *
     * @return string Line of text response
     */
    protected function multLine($line, $escape = false)
    {
        $line = rtrim($line);
        if (preg_match('/\{([0-9]+)\}$/', $line, $m)) {
            $out   = '';
            $str   = substr($line, 0, -strlen($m[0]));
            $bytes = $m[1];

            while (strlen($out) < $bytes) {
                $line = $this->readBytes($bytes);
                if ($line === null) {
                    break;
                }

                $out .= $line;
            }

            $line = $str . ($escape ? $this->escape($out) : $out);
        }

        return $line;
    }

    /**
     * Reads specified number of bytes from the connection stream
     *
     * @param int $bytes Number of bytes to get
     *
     * @return string Response text
     */
    protected function readBytes($bytes)
    {
        $data = '';
        $len  = 0;

        while ($len < $bytes && !$this->eof()) {
            $d = fread($this->fp, $bytes-$len);
            if ($this->debug) {
                $this->debug('S: '. $d);
            }
            $data .= $d;
            $data_len = strlen($data);
            if ($len == $data_len) {
                break; // nothing was read -> exit to avoid apache lockups
            }
            $len = $data_len;
        }

        return $data;
    }

    /**
     * Reads complete response to the IMAP command
     *
     * @param array $untagged Will be filled with untagged response lines
     *
     * @return string Response text
     */
    protected function readReply(&$untagged = null)
    {
        do {
            $line = trim($this->readLine(1024));
            // store untagged response lines
            if ($line[0] == '*') {
                $untagged[] = $line;
            }
        }
        while ($line[0] == '*');

        if ($untagged) {
            $untagged = join("\n", $untagged);
        }

        return $line;
    }

    /**
     * Response parser.
     *
     * @param string $string     Response text
     * @param string $err_prefix Error message prefix
     *
     * @return int Response status
     */
    protected function parseResult($string, $err_prefix = '')
    {
        if (preg_match('/^[a-z0-9*]+ (OK|NO|BAD|BYE)(.*)$/i', trim($string), $matches)) {
            $res = strtoupper($matches[1]);
            $str = trim($matches[2]);

            if ($res == 'OK') {
                $this->errornum = self::ERROR_OK;
            }
            else if ($res == 'NO') {
                $this->errornum = self::ERROR_NO;
            }
            else if ($res == 'BAD') {
                $this->errornum = self::ERROR_BAD;
            }
            else if ($res == 'BYE') {
                $this->closeSocket();
                $this->errornum = self::ERROR_BYE;
            }

            if ($str) {
                $str = trim($str);
                // get response string and code (RFC5530)
                if (preg_match("/^\[([a-z-]+)\]/i", $str, $m)) {
                    $this->resultcode = strtoupper($m[1]);
                    $str = trim(substr($str, strlen($m[1]) + 2));
                }
                else {
                    $this->resultcode = null;
                    // parse response for [APPENDUID 1204196876 3456]
                    if (preg_match("/^\[APPENDUID [0-9]+ ([0-9]+)\]/i", $str, $m)) {
                        $this->data['APPENDUID'] = $m[1];
                    }
                    // parse response for [COPYUID 1204196876 3456:3457 123:124]
                    else if (preg_match("/^\[COPYUID [0-9]+ ([0-9,:]+) ([0-9,:]+)\]/i", $str, $m)) {
                        $this->data['COPYUID'] = array($m[1], $m[2]);
                    }
                }

                $this->result = $str;

                if ($this->errornum != self::ERROR_OK) {
                    $this->error = $err_prefix ? $err_prefix.$str : $str;
                }
            }

            return $this->errornum;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * Checks connection stream state.
     *
     * @return bool True if connection is closed
     */
    protected function eof()
    {
        if (!is_resource($this->fp)) {
            return true;
        }

        // If a connection opened by fsockopen() wasn't closed
        // by the server, feof() will hang.
        $start = microtime(true);

        if (feof($this->fp) ||
            ($this->prefs['timeout'] && (microtime(true) - $start > $this->prefs['timeout']))
        ) {
            $this->closeSocket();
            return true;
        }

        return false;
    }

    /**
     * Closes connection stream.
     */
    protected function closeSocket()
    {
        @fclose($this->fp);
        $this->fp = null;
    }

    /**
     * Error code/message setter.
     */
    protected function setError($code, $msg = '')
    {
        $this->errornum = $code;
        $this->error    = $msg;

        return $code;
    }

    /**
     * Checks response status.
     * Checks if command response line starts with specified prefix (or * BYE/BAD)
     *
     * @param string $string   Response text
     * @param string $match    Prefix to match with (case-sensitive)
     * @param bool   $error    Enables BYE/BAD checking
     * @param bool   $nonempty Enables empty response checking
     *
     * @return bool True any check is true or connection is closed.
     */
    protected function startsWith($string, $match, $error = false, $nonempty = false)
    {
        if (!$this->fp) {
            return true;
        }

        if (strncmp($string, $match, strlen($match)) == 0) {
            return true;
        }

        if ($error && preg_match('/^\* (BYE|BAD) /i', $string, $m)) {
            if (strtoupper($m[1]) == 'BYE') {
                $this->closeSocket();
            }
            return true;
        }

        if ($nonempty && !strlen($string)) {
            return true;
        }

        return false;
    }

    /**
     * Capabilities checker
     */
    protected function hasCapability($name)
    {
        if (empty($this->capability) || $name == '') {
            return false;
        }

        if (in_array($name, $this->capability)) {
            return true;
        }
        else if (strpos($name, '=')) {
            return false;
        }

        $result = array();
        foreach ($this->capability as $cap) {
            $entry = explode('=', $cap);
            if ($entry[0] == $name) {
                $result[] = $entry[1];
            }
        }

        return $result ?: false;
    }

    /**
     * DIGEST-MD5/CRAM-MD5/PLAIN Authentication
     *
     * @param string $user Username
     * @param string $pass Password
     * @param string $type Authentication type (PLAIN/CRAM-MD5/DIGEST-MD5)
     *
     * @return resource Connection resourse on success, error code on error
     */
    protected function authenticate($user, $pass, $type = 'PLAIN')
    {
        if ($type == 'CRAM-MD5' || $type == 'DIGEST-MD5') {
            if ($type == 'DIGEST-MD5' && !class_exists('Auth_SASL')) {
                return $this->setError(self::ERROR_BYE,
                    "The Auth_SASL package is required for DIGEST-MD5 authentication");
            }

            $this->putLine($this->nextTag() . " AUTHENTICATE $type");
            $line = trim($this->readReply());

            if ($line[0] == '+') {
                $challenge = substr($line, 2);
            }
            else {
                return $this->parseResult($line);
            }

            if ($type == 'CRAM-MD5') {
                // RFC2195: CRAM-MD5
                $ipad = '';
                $opad = '';
                $xor  = function($str1, $str2) {
                    $result = '';
                    $size   = strlen($str1);
                    for ($i=0; $i<$size; $i++) {
                        $result .= chr(ord($str1[$i]) ^ ord($str2[$i]));
                    }
                    return $result;
                };

                // initialize ipad, opad
                for ($i=0; $i<64; $i++) {
                    $ipad .= chr(0x36);
                    $opad .= chr(0x5C);
                }

                // pad $pass so it's 64 bytes
                $pass = str_pad($pass, 64, chr(0));

                // generate hash
                $hash  = md5($xor($pass, $opad) . pack("H*",
                    md5($xor($pass, $ipad) . base64_decode($challenge))));
                $reply = base64_encode($user . ' ' . $hash);

                // send result
                $this->putLine($reply, true, true);
            }
            else {
                // RFC2831: DIGEST-MD5
                // proxy authorization
                if (!empty($this->prefs['auth_cid'])) {
                    $authc = $this->prefs['auth_cid'];
                    $pass  = $this->prefs['auth_pw'];
                }
                else {
                    $authc = $user;
                    $user  = '';
                }

                $auth_sasl = new Auth_SASL;
                $auth_sasl = $auth_sasl->factory('digestmd5');
                $reply     = base64_encode($auth_sasl->getResponse($authc, $pass,
                    base64_decode($challenge), $this->host, 'imap', $user));

                // send result
                $this->putLine($reply, true, true);
                $line = trim($this->readReply());

                if ($line[0] != '+') {
                    return $this->parseResult($line);
                }

                // check response
                $challenge = substr($line, 2);
                $challenge = base64_decode($challenge);
                if (strpos($challenge, 'rspauth=') === false) {
                    return $this->setError(self::ERROR_BAD,
                        "Unexpected response from server to DIGEST-MD5 response");
                }

                $this->putLine('');
            }

            $line   = $this->readReply();
            $result = $this->parseResult($line);
        }
        else if ($type == 'GSSAPI') {
            if (!extension_loaded('krb5')) {
                return $this->setError(self::ERROR_BYE,
                    "The krb5 extension is required for GSSAPI authentication");
            }

            if (empty($this->prefs['gssapi_cn'])) {
                return $this->setError(self::ERROR_BYE,
                    "The gssapi_cn parameter is required for GSSAPI authentication");
            }

            if (empty($this->prefs['gssapi_context'])) {
                return $this->setError(self::ERROR_BYE,
                    "The gssapi_context parameter is required for GSSAPI authentication");
            }

            putenv('KRB5CCNAME=' . $this->prefs['gssapi_cn']);

            try {
                $ccache = new KRB5CCache();
                $ccache->open($this->prefs['gssapi_cn']);
                $gssapicontext = new GSSAPIContext();
                $gssapicontext->acquireCredentials($ccache);

                $token   = '';
                $success = $gssapicontext->initSecContext($this->prefs['gssapi_context'], null, null, null, $token);
                $token   = base64_encode($token);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                return $this->setError(self::ERROR_BYE, "GSSAPI authentication failed");
            }

            $this->putLine($this->nextTag() . " AUTHENTICATE GSSAPI " . $token);
            $line = trim($this->readReply());

            if ($line[0] != '+') {
                return $this->parseResult($line);
            }

            try {
                $itoken = base64_decode(substr($line, 2));

                if (!$gssapicontext->unwrap($itoken, $itoken)) {
                    throw new Exception("GSSAPI SASL input token unwrap failed");
                }

                if (strlen($itoken) < 4) {
                    throw new Exception("GSSAPI SASL input token invalid");
                }

                // Integrity/encryption layers are not supported. The first bit
                // indicates that the server supports "no security layers".
                // 0x00 should not occur, but support broken implementations.
                $server_layers = ord($itoken[0]);
                if ($server_layers && ($server_layers & 0x1) != 0x1) {
                    throw new Exception("Server requires GSSAPI SASL integrity/encryption");
                }

                // Construct output token. 0x01 in the first octet = SASL layer "none",
                // zero in the following three octets = no data follows.
                // See https://github.com/cyrusimap/cyrus-sasl/blob/e41cfb986c1b1935770de554872247453fdbb079/plugins/gssapi.c#L1284
                if (!$gssapicontext->wrap(pack("CCCC", 0x1, 0, 0, 0), $otoken, true)) {
                    throw new Exception("GSSAPI SASL output token wrap failed");
                }
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                return $this->setError(self::ERROR_BYE, "GSSAPI authentication failed");
            }

            $this->putLine(base64_encode($otoken));

            $line   = $this->readReply();
            $result = $this->parseResult($line);
        }
        else if ($type == 'PLAIN') {
            // proxy authorization
            if (!empty($this->prefs['auth_cid'])) {
                $authc = $this->prefs['auth_cid'];
                $pass  = $this->prefs['auth_pw'];
            }
            else {
                $authc = $user;
                $user  = '';
            }

            $reply = base64_encode($user . chr(0) . $authc . chr(0) . $pass);

            // RFC 4959 (SASL-IR): save one round trip
            if ($this->getCapability('SASL-IR')) {
                list($result, $line) = $this->execute("AUTHENTICATE PLAIN", array($reply),
                    self::COMMAND_LASTLINE | self::COMMAND_CAPABILITY | self::COMMAND_ANONYMIZED);
            }
            else {
                $this->putLine($this->nextTag() . " AUTHENTICATE PLAIN");
                $line = trim($this->readReply());

                if ($line[0] != '+') {
                    return $this->parseResult($line);
                }

                // send result, get reply and process it
                $this->putLine($reply, true, true);
                $line   = $this->readReply();
                $result = $this->parseResult($line);
            }
        }
        else if ($type == 'LOGIN') {
            $this->putLine($this->nextTag() . " AUTHENTICATE LOGIN");

            $line = trim($this->readReply());
            if ($line[0] != '+') {
                return $this->parseResult($line);
            }

            $this->putLine(base64_encode($user), true, true);

            $line = trim($this->readReply());
            if ($line[0] != '+') {
                return $this->parseResult($line);
            }

            // send result, get reply and process it
            $this->putLine(base64_encode($pass), true, true);

            $line   = $this->readReply();
            $result = $this->parseResult($line);
        }

        if ($result === self::ERROR_OK) {
            // optional CAPABILITY response
            if ($line && preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)) {
                $this->parseCapability($matches[1], true);
            }

            return $this->fp;
        }

        return $this->setError($result, "AUTHENTICATE $type: $line");
    }

    /**
     * LOGIN Authentication
     *
     * @param string $user Username
     * @param string $pass Password
     *
     * @return resource Connection resourse on success, error code on error
     */
    protected function login($user, $password)
    {
        // Prevent from sending credentials in plain text when connection is not secure
        if ($this->getCapability('LOGINDISABLED')) {
            return $this->setError(self::ERROR_BAD, "Login disabled by IMAP server");
        }

        list($code, $response) = $this->execute('LOGIN', array(
            $this->escape($user), $this->escape($password)), self::COMMAND_CAPABILITY | self::COMMAND_ANONYMIZED);

        // re-set capabilities list if untagged CAPABILITY response provided
        if (preg_match('/\* CAPABILITY (.+)/i', $response, $matches)) {
            $this->parseCapability($matches[1], true);
        }

        if ($code == self::ERROR_OK) {
            return $this->fp;
        }

        return $code;
    }

    /**
     * Connects to IMAP server and authenticates.
     *
     * @param string $host     Server hostname or IP
     * @param string $user     User name
     * @param string $password Password
     * @param array  $options  Connection and class options
     *
     * @return bool True on success, False on failure
     */
    public function connect($host, $user, $password, $options = array())
    {
        // configure
        $this->set_prefs($options);

        $this->host     = $host;
        $this->user     = $user;
        $this->logged   = false;
        $this->selected = null;

        // check input
        if (empty($host)) {
            $this->setError(self::ERROR_BAD, "Empty host");
            return false;
        }

        if (empty($user)) {
            $this->setError(self::ERROR_NO, "Empty user");
            return false;
        }

        if (empty($password) && empty($options['gssapi_cn'])) {
            $this->setError(self::ERROR_NO, "Empty password");
            return false;
        }

        // Connect
        if (!$this->_connect($host)) {
            return false;
        }

        // Send ID info
        if (!empty($this->prefs['ident']) && $this->getCapability('ID')) {
            $this->data['ID'] = $this->id($this->prefs['ident']);
        }

        $auth_method  = $this->prefs['auth_type'];
        $auth_methods = array();
        $result       = null;

        // check for supported auth methods
        if (!$auth_method || $auth_method == 'CHECK') {
            if ($auth_caps = $this->getCapability('AUTH')) {
                $auth_methods = $auth_caps;
            }

            // Use best (for security) supported authentication method
            $all_methods = array('DIGEST-MD5', 'CRAM-MD5', 'CRAM_MD5', 'PLAIN', 'LOGIN');

            if (!empty($this->prefs['gssapi_cn'])) {
                array_unshift($all_methods, 'GSSAPI');
            }

            foreach ($all_methods as $auth_method) {
                if (in_array($auth_method, $auth_methods)) {
                    break;
                }
            }

            // Prefer LOGIN over AUTHENTICATE LOGIN for performance reasons
            if ($auth_method == 'LOGIN' && !$this->getCapability('LOGINDISABLED')) {
                $auth_method = 'IMAP';
            }
        }

        // pre-login capabilities can be not complete
        $this->capability_readed = false;

        // Authenticate
        switch ($auth_method) {
            case 'CRAM_MD5':
                $auth_method = 'CRAM-MD5';
            case 'CRAM-MD5':
            case 'DIGEST-MD5':
            case 'GSSAPI':
            case 'PLAIN':
            case 'LOGIN':
                $result = $this->authenticate($user, $password, $auth_method);
                break;

            case 'IMAP':
                $result = $this->login($user, $password);
                break;

            default:
                $this->setError(self::ERROR_BAD, "Configuration error. Unknown auth method: $auth_method");
        }

        // Connected and authenticated
        if (is_resource($result)) {
            if ($this->prefs['force_caps']) {
                $this->clearCapability();
            }
            $this->logged = true;

            return true;
        }

        $this->closeConnection();

        return false;
    }

    /**
     * Connects to IMAP server.
     *
     * @param string $host Server hostname or IP
     *
     * @return bool True on success, False on failure
     */
    protected function _connect($host)
    {
        // initialize connection
        $this->error    = '';
        $this->errornum = self::ERROR_OK;

        if (!$this->prefs['port']) {
            $this->prefs['port'] = 143;
        }

        // check for SSL
        if ($this->prefs['ssl_mode'] && $this->prefs['ssl_mode'] != 'tls') {
            $host = $this->prefs['ssl_mode'] . '://' . $host;
        }

        if ($this->prefs['timeout'] <= 0) {
            $this->prefs['timeout'] = max(0, intval(ini_get('default_socket_timeout')));
        }

        if ($this->debug) {
            // set connection identifier for debug output
            $this->resourceid = strtoupper(substr(md5(microtime() . $host . $this->user), 0, 4));

            $_host = ($this->prefs['ssl_mode'] == 'tls' ? 'tls://' : '') . $host . ':' . $this->prefs['port'];
            $this->debug("Connecting to $_host...");
        }

        if (!empty($this->prefs['socket_options'])) {
            $context  = stream_context_create($this->prefs['socket_options']);
            $this->fp = stream_socket_client($host . ':' . $this->prefs['port'], $errno, $errstr,
                $this->prefs['timeout'], STREAM_CLIENT_CONNECT, $context);
        }
        else {
            $this->fp = @fsockopen($host, $this->prefs['port'], $errno, $errstr, $this->prefs['timeout']);
        }

        if (!$this->fp) {
            $this->setError(self::ERROR_BAD, sprintf("Could not connect to %s:%d: %s",
                $host, $this->prefs['port'], $errstr ?: "Unknown reason"));

            return false;
        }

        if ($this->prefs['timeout'] > 0) {
            stream_set_timeout($this->fp, $this->prefs['timeout']);
        }

        $line = trim(fgets($this->fp, 8192));

        if ($this->debug && $line) {
            $this->debug('S: '. $line);
        }

        // Connected to wrong port or connection error?
        if (!preg_match('/^\* (OK|PREAUTH)/i', $line)) {
            if ($line)
                $error = sprintf("Wrong startup greeting (%s:%d): %s", $host, $this->prefs['port'], $line);
            else
                $error = sprintf("Empty startup greeting (%s:%d)", $host, $this->prefs['port']);

            $this->setError(self::ERROR_BAD, $error);
            $this->closeConnection();
            return false;
        }

        $this->data['GREETING'] = trim(preg_replace('/\[[^\]]+\]\s*/', '', $line));

        // RFC3501 [7.1] optional CAPABILITY response
        if (preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)) {
            $this->parseCapability($matches[1], true);
        }

        // TLS connection
        if ($this->prefs['ssl_mode'] == 'tls' && $this->getCapability('STARTTLS')) {
            $res = $this->execute('STARTTLS');

            if ($res[0] != self::ERROR_OK) {
                $this->closeConnection();
                return false;
            }

            if (isset($this->prefs['socket_options']['ssl']['crypto_method'])) {
                $crypto_method = $this->prefs['socket_options']['ssl']['crypto_method'];
            }
            else {
                // There is no flag to enable all TLS methods. Net_SMTP
                // handles enabling TLS similarly.
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | @STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                    | @STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            if (!stream_socket_enable_crypto($this->fp, true, $crypto_method)) {
                $this->setError(self::ERROR_BAD, "Unable to negotiate TLS");
                $this->closeConnection();
                return false;
            }

            // Now we're secure, capabilities need to be reread
            $this->clearCapability();
        }

        return true;
    }

    /**
     * Initializes environment
     */
    protected function set_prefs($prefs)
    {
        // set preferences
        if (is_array($prefs)) {
            $this->prefs = $prefs;
        }

        // set auth method
        if (!empty($this->prefs['auth_type'])) {
            $this->prefs['auth_type'] = strtoupper($this->prefs['auth_type']);
        }
        else {
            $this->prefs['auth_type'] = 'CHECK';
        }

        // disabled capabilities
        if (!empty($this->prefs['disabled_caps'])) {
            $this->prefs['disabled_caps'] = array_map('strtoupper', (array)$this->prefs['disabled_caps']);
        }

        // additional message flags
        if (!empty($this->prefs['message_flags'])) {
            $this->flags = array_merge($this->flags, $this->prefs['message_flags']);
            unset($this->prefs['message_flags']);
        }
    }

    /**
     * Checks connection status
     *
     * @return bool True if connection is active and user is logged in, False otherwise.
     */
    public function connected()
    {
        return $this->fp && $this->logged;
    }

    /**
     * Closes connection with logout.
     */
    public function closeConnection()
    {
        if ($this->logged && $this->putLine($this->nextTag() . ' LOGOUT')) {
            $this->readReply();
        }

        $this->closeSocket();
    }

    /**
     * Executes SELECT command (if mailbox is already not in selected state)
     *
     * @param string $mailbox      Mailbox name
     * @param array  $qresync_data QRESYNC data (RFC5162)
     *
     * @return boolean True on success, false on error
     */
    public function select($mailbox, $qresync_data = null)
    {
        if (!strlen($mailbox)) {
            return false;
        }

        if ($this->selected === $mailbox) {
            return true;
        }

        $params = array($this->escape($mailbox));

        // QRESYNC data items
        //    0. the last known UIDVALIDITY,
        //    1. the last known modification sequence,
        //    2. the optional set of known UIDs, and
        //    3. an optional parenthesized list of known sequence ranges and their
        //       corresponding UIDs.
        if (!empty($qresync_data)) {
            if (!empty($qresync_data[2])) {
                $qresync_data[2] = self::compressMessageSet($qresync_data[2]);
            }

            $params[] = array('QRESYNC', $qresync_data);
        }

        list($code, $response) = $this->execute('SELECT', $params);

        if ($code == self::ERROR_OK) {
            $this->clear_mailbox_cache();

            $response = explode("\r\n", $response);
            foreach ($response as $line) {
                if (preg_match('/^\* OK \[/i', $line)) {
                    $pos   = strcspn($line, ' ]', 6);
                    $token = strtoupper(substr($line, 6, $pos));
                    $pos   += 7;

                    switch ($token) {
                    case 'UIDNEXT':
                    case 'UIDVALIDITY':
                    case 'UNSEEN':
                        if ($len = strspn($line, '0123456789', $pos)) {
                            $this->data[$token] = (int) substr($line, $pos, $len);
                        }
                        break;

                    case 'HIGHESTMODSEQ':
                        if ($len = strspn($line, '0123456789', $pos)) {
                            $this->data[$token] = (string) substr($line, $pos, $len);
                        }
                        break;

                    case 'NOMODSEQ':
                        $this->data[$token] = true;
                        break;

                    case 'PERMANENTFLAGS':
                        $start = strpos($line, '(', $pos);
                        $end   = strrpos($line, ')');
                        if ($start && $end) {
                            $flags = substr($line, $start + 1, $end - $start - 1);
                            $this->data[$token] = explode(' ', $flags);
                        }
                        break;
                    }
                }
                else if (preg_match('/^\* ([0-9]+) (EXISTS|RECENT|FETCH)/i', $line, $match)) {
                    $token = strtoupper($match[2]);
                    switch ($token) {
                    case 'EXISTS':
                    case 'RECENT':
                        $this->data[$token] = (int) $match[1];
                        break;

                    case 'FETCH':
                        // QRESYNC FETCH response (RFC5162)
                        $line       = substr($line, strlen($match[0]));
                        $fetch_data = $this->tokenizeResponse($line, 1);
                        $data       = array('id' => $match[1]);

                        for ($i=0, $size=count($fetch_data); $i<$size; $i+=2) {
                            $data[strtolower($fetch_data[$i])] = $fetch_data[$i+1];
                        }

                        $this->data['QRESYNC'][$data['uid']] = $data;
                        break;
                    }
                }
                // QRESYNC VANISHED response (RFC5162)
                else if (preg_match('/^\* VANISHED [()EARLIER]*/i', $line, $match)) {
                    $line   = substr($line, strlen($match[0]));
                    $v_data = $this->tokenizeResponse($line, 1);

                    $this->data['VANISHED'] = $v_data;
                }
            }

            $this->data['READ-WRITE'] = $this->resultcode != 'READ-ONLY';
            $this->selected = $mailbox;

            return true;
        }

        return false;
    }

    /**
     * Executes CLOSE command
     *
     * @return boolean True on success, False on error
     * @since 0.5
     */
    public function close()
    {
        $result = $this->execute('CLOSE', null, self::COMMAND_NORESPONSE);

        if ($result == self::ERROR_OK) {
            $this->selected = null;
            return true;
        }

        return false;
    }

    /**
     * Changes flag of the message(s)
     *
     * @param string       $mailbox  Mailbox name
     * @param string|array $messages Message UID(s)
     * @param string       $flag     Flag name
     * @param string       $mod      Modifier [+|-]. Default: "+".
     *
     * @return bool True on success, False on failure
     */
    protected function modFlag($mailbox, $messages, $flag, $mod = '+')
    {
        if (!$flag) {
            return false;
        }

        if (!$this->select($mailbox)) {
            return false;
        }

        if (!$this->data['READ-WRITE']) {
            $this->setError(self::ERROR_READONLY, "Mailbox is read-only");
            return false;
        }

        if ($this->flags[strtoupper($flag)]) {
            $flag = $this->flags[strtoupper($flag)];
        }

        // if PERMANENTFLAGS is not specified all flags are allowed
        if (!empty($this->data['PERMANENTFLAGS'])
            && !in_array($flag, (array) $this->data['PERMANENTFLAGS'])
            && !in_array('\\*', (array) $this->data['PERMANENTFLAGS'])
        ) {
            return false;
        }

        // Clear internal status cache
        if ($flag == 'SEEN') {
            unset($this->data['STATUS:'.$mailbox]['UNSEEN']);
        }

        if ($mod != '+' && $mod != '-') {
            $mod = '+';
        }

        $result = $this->execute('UID STORE', array(
            $this->compressMessageSet($messages), $mod . 'FLAGS.SILENT', "($flag)"),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Sends IMAP command and parses result
     *
     * @param string $command   IMAP command
     * @param array  $arguments Command arguments
     * @param int    $options   Execution options
     * @param string $filter    Line filter (regexp)
     *
     * @return mixed Response code or list of response code and data
     * @since 0.5-beta
     */
    public function execute($command, $arguments = array(), $options = 0, $filter = null)
    {
        $tag      = $this->nextTag();
        $query    = $tag . ' ' . $command;
        $noresp   = ($options & self::COMMAND_NORESPONSE);
        $response = $noresp ? null : '';

        if (!empty($arguments)) {
            foreach ($arguments as $arg) {
                $query .= ' ' . self::r_implode($arg);
            }
        }

        // Send command
        if (!$this->putLineC($query, true, ($options & self::COMMAND_ANONYMIZED))) {
            preg_match('/^[A-Z0-9]+ ((UID )?[A-Z]+)/', $query, $matches);
            $cmd = $matches[1] ?: 'UNKNOWN';
            $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");

            return $noresp ? self::ERROR_COMMAND : array(self::ERROR_COMMAND, '');
        }

        // Parse response
        do {
            $line = $this->readLine(4096);

            if ($response !== null) {
                // TODO: Better string literals handling with filter
                if (!$filter || preg_match($filter, $line)) {
                    $response .= $line;
                }
            }

            // parse untagged response for [COPYUID 1204196876 3456:3457 123:124] (RFC6851)
            if ($line && $command == 'UID MOVE') {
                if (preg_match("/^\* OK \[COPYUID [0-9]+ ([0-9,:]+) ([0-9,:]+)\]/i", $line, $m)) {
                    $this->data['COPYUID'] = array($m[1], $m[2]);
                }
            }
        }
        while (!$this->startsWith($line, $tag . ' ', true, true));

        $code = $this->parseResult($line, $command . ': ');

        // Remove last line from response
        if ($response) {
            if (!$filter) {
                $line_len = min(strlen($response), strlen($line));
                $response = substr($response, 0, -$line_len);
            }

            $response = rtrim($response, "\r\n");
        }

        // optional CAPABILITY response
        if (($options & self::COMMAND_CAPABILITY) && $code == self::ERROR_OK
            && preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)
        ) {
            $this->parseCapability($matches[1], true);
        }

        // return last line only (without command tag, result and response code)
        if ($line && ($options & self::COMMAND_LASTLINE)) {
            $response = preg_replace("/^$tag (OK|NO|BAD|BYE|PREAUTH)?\s*(\[[a-z-]+\])?\s*/i", '', trim($line));
        }

        return $noresp ? $code : array($code, $response);
    }

    /**
     * Splits IMAP response into string tokens
     *
     * @param string &$str The IMAP's server response
     * @param int    $num  Number of tokens to return
     *
     * @return mixed Tokens array or string if $num=1
     * @since 0.5-beta
     */
    public static function tokenizeResponse(&$str, $num=0)
    {
        $result = array();

        while (!$num || count($result) < $num) {
            // remove spaces from the beginning of the string
            $str = ltrim($str);

            switch ($str[0]) {

            // String literal
            case '{':
                if (($epos = strpos($str, "}\r\n", 1)) == false) {
                    // error
                }
                if (!is_numeric(($bytes = substr($str, 1, $epos - 1)))) {
                    // error
                }

                $result[] = $bytes ? substr($str, $epos + 3, $bytes) : '';
                $str      = substr($str, $epos + 3 + $bytes);
                break;

            // Quoted string
            case '"':
                $len = strlen($str);

                for ($pos=1; $pos<$len; $pos++) {
                    if ($str[$pos] == '"') {
                        break;
                    }
                    if ($str[$pos] == "\\") {
                        if ($str[$pos + 1] == '"' || $str[$pos + 1] == "\\") {
                            $pos++;
                        }
                    }
                }

                // we need to strip slashes for a quoted string
                $result[] = stripslashes(substr($str, 1, $pos - 1));
                $str      = substr($str, $pos + 1);
                break;

            // Parenthesized list
            case '(':
                $str      = substr($str, 1);
                $result[] = self::tokenizeResponse($str);
                break;

            case ')':
                $str = substr($str, 1);
                return $result;

            // String atom, number, astring, NIL, *, %
            default:
                // empty string
                if ($str === '' || $str === null) {
                    break 2;
                }

                // excluded chars: SP, CTL, ), DEL
                // we do not exclude [ and ] (#1489223)
                if (preg_match('/^([^\x00-\x20\x29\x7F]+)/', $str, $m)) {
                    $result[] = $m[1] == 'NIL' ? null : $m[1];
                    $str      = substr($str, strlen($m[1]));
                }
                break;
            }
        }

        return $num == 1 ? $result[0] : $result;
    }

    /**
     * Joins IMAP command line elements (recursively)
     */
    protected static function r_implode($element)
    {
        $string = '';

        if (is_array($element)) {
            reset($element);
            foreach ($element as $value) {
                $string .= ' ' . self::r_implode($value);
            }
        }
        else {
            return $element;
        }

        return '(' . trim($string) . ')';
    }

    /**
     * Converts message identifiers array into sequence-set syntax
     *
     * @param array $messages Message identifiers
     * @param bool  $force    Forces compression of any size
     *
     * @return string Compressed sequence-set
     */
    public static function compressMessageSet($messages, $force=false)
    {
        // given a comma delimited list of independent mid's,
        // compresses by grouping sequences together
        if (!is_array($messages)) {
            // if less than 255 bytes long, let's not bother
            if (!$force && strlen($messages) < 255) {
                return preg_match('/[^0-9:,*]/', $messages) ? 'INVALID' : $messages;
            }

            // see if it's already been compressed
            if (strpos($messages, ':') !== false) {
                return preg_match('/[^0-9:,*]/', $messages) ? 'INVALID' : $messages;
            }

            // separate, then sort
            $messages = explode(',', $messages);
        }

        sort($messages);

        $result = array();
        $start  = $prev = $messages[0];

        foreach ($messages as $id) {
            $incr = $id - $prev;
            if ($incr > 1) { // found a gap
                if ($start == $prev) {
                    $result[] = $prev; // push single id
                }
                else {
                    $result[] = $start . ':' . $prev; // push sequence as start_id:end_id
                }
                $start = $id; // start of new sequence
            }
            $prev = $id;
        }

        // handle the last sequence/id
        if ($start == $prev) {
            $result[] = $prev;
        }
        else {
            $result[] = $start.':'.$prev;
        }

        // return as comma separated string
        $result = implode(',', $result);

        return preg_match('/[^0-9:,*]/', $result) ? 'INVALID' : $result;
    }

    /**
     * Converts message sequence-set into array
     *
     * @param string $messages Message identifiers
     *
     * @return array List of message identifiers
     */
    public static function uncompressMessageSet($messages)
    {
        if (empty($messages)) {
            return array();
        }

        $result   = array();
        $messages = explode(',', $messages);

        foreach ($messages as $idx => $part) {
            $items = explode(':', $part);
            $max   = max($items[0], $items[1]);

            for ($x=$items[0]; $x<=$max; $x++) {
                $result[] = (int)$x;
            }
            unset($messages[$idx]);
        }

        return $result;
    }

    /**
     * Clear internal status cache
     */
    protected function clear_status_cache($mailbox)
    {
        unset($this->data['STATUS:' . $mailbox]);

        $keys = array('EXISTS', 'RECENT', 'UNSEEN', 'UID-MAP');

        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
    }

    /**
     * Clear internal cache of the current mailbox
     */
    protected function clear_mailbox_cache()
    {
        $this->clear_status_cache($this->selected);

        $keys = array('UIDNEXT', 'UIDVALIDITY', 'HIGHESTMODSEQ', 'NOMODSEQ',
            'PERMANENTFLAGS', 'QRESYNC', 'VANISHED', 'READ-WRITE');

        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
    }

    /**
     * Converts flags array into string for inclusion in IMAP command
     *
     * @param array $flags Flags (see self::flags)
     *
     * @return string Space-separated list of flags
     */
    protected function flagsToStr($flags)
    {
        foreach ((array)$flags as $idx => $flag) {
            if ($flag = $this->flags[strtoupper($flag)]) {
                $flags[$idx] = $flag;
            }
        }

        return implode(' ', (array)$flags);
    }

    /**
     * CAPABILITY response parser
     */
    protected function parseCapability($str, $trusted=false)
    {
        $str = preg_replace('/^\* CAPABILITY /i', '', $str);

        $this->capability = explode(' ', strtoupper($str));

        if (!empty($this->prefs['disabled_caps'])) {
            $this->capability = array_diff($this->capability, $this->prefs['disabled_caps']);
        }

        if (!isset($this->prefs['literal+']) && in_array('LITERAL+', $this->capability)) {
            $this->prefs['literal+'] = true;
        }

        if ($trusted) {
            $this->capability_readed = true;
        }
    }

    /**
     * Escapes a string when it contains special characters (RFC3501)
     *
     * @param string  $string       IMAP string
     * @param boolean $force_quotes Forces string quoting (for atoms)
     *
     * @return string String atom, quoted-string or string literal
     * @todo lists
     */
    public static function escape($string, $force_quotes=false)
    {
        if ($string === null) {
            return 'NIL';
        }

        if ($string === '') {
            return '""';
        }

        // atom-string (only safe characters)
        if (!$force_quotes && !preg_match('/[\x00-\x20\x22\x25\x28-\x2A\x5B-\x5D\x7B\x7D\x80-\xFF]/', $string)) {
            return $string;
        }

        // quoted-string
        if (!preg_match('/[\r\n\x00\x80-\xFF]/', $string)) {
            return '"' . addcslashes($string, '\\"') . '"';
        }

        // literal-string
        return sprintf("{%d}\r\n%s", strlen($string), $string);
    }

    /**
     * Write the given debug text to the current debug output handler.
     *
     * @param string $message Debug message text.
     *
     * @since 0.5-stable
     */
    protected function debug($message)
    {
        if (($len = strlen($message)) > self::DEBUG_LINE_LENGTH) {
            $diff    = $len - self::DEBUG_LINE_LENGTH;
            $message = substr($message, 0, self::DEBUG_LINE_LENGTH)
                . "... [truncated $diff bytes]";
        }

        if ($this->resourceid) {
            $message = sprintf('[%s] %s', $this->resourceid, $message);
        }

        if ($this->debug_handler) {
            call_user_func_array($this->debug_handler, array(&$this, $message));
        }
        else {
            echo "DEBUG: $message\n";
        }
    }
}
