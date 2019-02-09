<?php
/**
 * Copyright (c) 2019 Sebastian Sterk <sebastian@wiuwiu.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * User authentication against a XMPP Prosody MySQL database
 *
 * @category Apps
 * @package  UserExternal
 * @author   Sebastian Sterk https://wiuwiu.de/Imprint
 * @license  http://www.gnu.org/licenses/agpl AGPL
 */
class OC_User_XMPP extends \OCA\user_external\Base {
	private $host;
	private $xmppdb;
	private $xmppdbuser;
	private $xmppdbpassword;
	private $xmppdomain;

	public function __construct($host, $xmppdb, $xmppdbuser, $xmppdbpassword, $xmppdomain) {
		parent::__construct($host);
		$this->host = $host;
		$this->xmppdb = $xmppdb;
		$this->xmppdbuser = $xmppdbuser;
		$this->xmppdbpassword = $xmppdbpassword;
		$this->xmppdomain = $xmppdomain;
	}

	public function hmac_sha1($key, $data) {
                if (strlen($key) > 64)
                        $key = str_pad(sha1($key, true), 64, chr(0));
                if (strlen($key) < 64)
                        $key = str_pad($key, 64, chr(0));

                $opad = str_repeat(chr(0x5C), 64);
                $ipad = str_repeat(chr(0x36), 64);

                for ($i = 0; $i < strlen($key); $i++) {
                        $opad[$i] = $opad[$i] ^ $key[$i];
                        $ipad[$i] = $ipad[$i] ^ $key[$i];
                }
                return sha1($opad.sha1($ipad.$data, true));
        }
	
	public function checkPassword($uid, $password){
		$pdo = new PDO("mysql:host=$this->host;dbname=$this->xmppdb", $this->xmppdbuser, $this->xmppdbpassword);
		if(isset($uid) && isset($password)) {
        		if(!filter_var($uid, FILTER_VALIDATE_EMAIL) || !strpos($uid, $this->xmppdomain) || substr($uid, -strlen($this->xmppdomain)) != $this->xmppdomain)
				return false;
			$user = explode("@", $uid);
        		$username = strtolower($user[0]);
        		$submitted_password = $password;
        		$statement = $pdo->prepare("SELECT * FROM prosody WHERE user = :user AND host = :xmppdomain AND store = 'accounts'");
        		$result = $statement->execute(array('user' => $username, 'xmppdomain' => $this->xmppdomain));
        		$user = $statement->fetchAll();
        		if(empty($user))
                		return false;
        		foreach ($user as $key){
                        	if($key[3] == "salt")
                        	        $internal_salt = $key['value'];
                        	if($key[3] == "server_key")
                        	        $internal_server_key = $key['value'];
                        	if($key[3] == "stored_key")
                        	        $internal_stored_key = $key['value'];
		        }
	        	unset($user);
		        $internal_iteration = '4096';

		        $new_salted_password = hash_pbkdf2('sha1', $submitted_password, $internal_salt, $internal_iteration, 0, true);
		        $new_server_key = $this->hmac_sha1($new_salted_password, 'Server Key');
		        $new_client_key = $this->hmac_sha1($new_salted_password, 'Client Key');
		        $new_stored_key = sha1(hex2bin($new_client_key));

		        if ($new_server_key == $internal_server_key && $new_stored_key == $internal_stored_key){
				$uid = mb_strtolower($uid);
	                        $this->storeUser($uid);
				return $uid;
			} else
				return false;
		}
	}
}
