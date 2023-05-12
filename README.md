External user authentication
============================

**⚠⚠ Warning:** As of Version 3.0 this app uses namespace \OCA\UserExternal now. You MUST change your config to adopt to this change. See examples below. ⚠⚠

**Authenticate user login against IMAP, SMB, FTP, WebDAV, HTTP BasicAuth, SSH and XMPP**

Passwords are not stored locally; authentication always happens against
the remote server.

It stores users and their display name in its own database table
`users_external`.
When modifying the `user_backends` configuration, you need to
update the database table's `backend` field, or your users will lose
their configured display name.

If something does not work, check the log file at `nextcloud/data/nextcloud.log`.

**⚠⚠ Warning:** If you are using more than one backend or especially one backend more often than once, make sure that you still have resp. get unique `uid`s in the database. ⚠⚠


FTP
---
Authenticate Nextcloud users against a FTP server.


### Configuration
You only need to supply the FTP host name or IP.

The second - optional - parameter determines if SSL should be used or not.

Add the following to `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\FTP',
            'arguments' => array('127.0.0.1'),
        ),
    ),

To enable SSL connections via `ftps`, append a second parameter `true`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\FTP',
            'arguments' => array('127.0.0.1', true),
        ),
    ),


### Dependencies
PHP automatically contains basic FTP support.

For SSL-secured FTP connections via ftps, the PHP [openssl extension][FTP_0]
needs to be activated.

[FTP_0]: http://php.net/openssl



IMAP
----
Authenticate Nextcloud users against an IMAP server.
IMAP user and password need to be given for the Nextcloud login.


### Configuration
The parameters are `host, port, sslmode, domain`.
Possible values for sslmode are `ssl` or `tls`.
Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\IMAP',
            'arguments' => array(
                '127.0.0.1', 993, 'ssl', 'example.com', true, false
            ),
        ),
    ),

This connects to the IMAP server on IP `127.0.0.1`.
The default port is 143. However, note that parameter order matters and if
you want to restrict the domain (4th parameter), you need to also specify
the port (2nd parameter) and sslmode (3rd parameter; set to `null` for
insecure connection).
If a domain name (e.g. example.com) is specified, then this makes sure that
only users from this domain will be allowed to login. If the fifth parameter
is set to true, after successfull login the domain part will be striped and
the rest used as username in Nextcloud. e.g. 'username@example.com' will be
'username' in Nextcloud. The sixth parameter toggles whether on creation of
the user, it is added to a group corresponding to the name of the domain part
of the address. 

**⚠⚠ Warning:** If you are [**upgrading** from versions **<0.6.0**](https://github.com/nextcloud/user_external/releases/tag/v0.6.0), beside adapting your `config.php` you also have to change the `backend` column in the `users_external` table of the database. In your pre 0.6.0 database it may look like `{127.0.0.1:993/imap/ssl/readonly}INBOX` or similar, but now it has to be just `127.0.0.1` for everything to work flawless again. ⚠⚠


Samba
-----
Utilizes the `smbclient` executable to authenticate against a windows
network machine via SMB.


### Configuration
The only supported parameter is the hostname of the remote machine.

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\SMB',
            'arguments' => array('127.0.0.1'),
        ),
    ),


### Dependencies
The `smbclient` executable needs to be installed and accessible within `$PATH`.


WebDAV
------

Authenticate users by a WebDAV call. You can use any WebDAV server, Nextcloud server or other web server to authenticate. It should return http 200 for right credentials and http 401 for wrong ones.

Attention: This app is not compatible with the LDAP user and group backend. This app is not the WebDAV interface of Nextcloud, if you don't understand what it does then do not enable it.

### Configuration
The only supported parameter is the URL of the web server.

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\WebDavAuth',
            'arguments' => array(
                'https://example.com/webdav',
                'basic',  // alternative: 'digest'
            ),
        ),
    ),


BasicAuth
------

Authenticate users by an [HTTP Basic access authentication][BasicAuth_0]  call.
HTTP server of your choice to authenticate. It should return HTTP 2xx for correct credentials and an appropriate other error code for wrong ones or refused access.
The HTTP server _must_ respond to any requests to the target URL with the "www-authenticate" header set. 
Otherwise BasicAuth considers itself to be misconfigured or the HTTP server unfit for authentication.

### Configuration
The only supported parameter is the URL of the web server where the authentication happens.

**⚠⚠ Warning:** make sure to use the URL of a correctly configured HTTP Basic authenticating server. If the server always responds with a HTTP 2xx response without validating the users, this would allow anyone to log in to your Nextcloud instance with **any username / password combination**. ⚠⚠

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\BasicAuth',
            'arguments' => array('https://example.com/basic_auth'),
        ),
    ),


[BasicAuth_0]: https://en.wikipedia.org/wiki/Basic_access_authentication


SSH
---

Authenticates users via SSH. You can use any SSH2 server, but it must accept password authentication.

### Configuration
The supported parameters are the hostname and the port (default `22`) of the remote machine.

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => '\OCA\UserExternal\SSH',
            'arguments' => array('127.0.0.1', '22'),
        ),
    ),


### Dependencies
Requires the php-ssh2 PECL module installed.


XMPP (Prosody)
----
Authenticate Nextcloud users against a Prosody XMPP MySQL database.
Prosody user and password need to be given for the Nextcloud login.


### Configuration
Add the following to your `config.php`:

    'user_backends' => array (
        0 => array (
            'class' => '\OCA\UserExternal\XMPP',
                'arguments' => array (
                    0 => 'dbhost',
                    1 => 'prosodydb',
                    2 => 'dbuser',
                    3 => 'dbuserpassword',
                    4 => 'xmppdomain',
                    5 => true,
                ),
            ),
    ),

0 - Database Host
1 - Prosody Database Name
2 - Database User
3 - Database User Password
4 - XMPP Domain
5 - Hashed Passwords in Database (true) / Plaintext Passwords in Database (false)

**⚠⚠ Warning:** If you need to set *5 (Hashed Password in Database)* to false, your Prosody Instance is storing passwords in plaintext. This is insecure and not recommended. We highly recommend that you change your Prosody configuration to protect the passwords of your Prosody users. ⚠⚠


Alternatives
------------
Other extensions allow connecting to external user databases directly via SQL, which may be faster:

* [user_sql](https://github.com/nextcloud/user_sql)
* [user_backend_sql_raw](https://github.com/PanCakeConnaisseur/user_backend_sql_raw)
