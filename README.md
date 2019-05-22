External user authentication
============================
**Authenticate user login against FTP, IMAP or SMB.**

Passwords are not stored locally; authentication always happens against
the remote server.

It stores users and their display name in its own database table
`users_external`.
When modifying the `user_backends` configuration, you need to
update the database table's `backend` field, or your users will lose
their configured display name.

If something does not work, check the log file at `nextcloud/data/nextcloud.log`.


FTP
---
Authenticate Nextcloud users against a FTP server.


### Configuration
You only need to supply the FTP host name or IP.

The second - optional - parameter determines if SSL should be used or not.

Add the following to `config.php`:

    'user_backends' => array(
        array(
            'class' => 'OC_User_FTP',
            'arguments' => array('127.0.0.1'),
        ),
    ),

To enable SSL connections via `ftps`, append a second parameter `true`:

    'user_backends' => array(
        array(
            'class' => 'OC_User_FTP',
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
            'class' => 'OC_User_IMAP',
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



Samba
-----
Utilizes the `smbclient` executable to authenticate against a windows
network machine via SMB.


### Configuration
The only supported parameter is the hostname of the remote machine.

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => 'OC_User_SMB',
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
            'class' => '\OCA\User_External\WebDAVAuth',
            'arguments' => array('https://example.com/webdav'),
        ),
    ),


BasicAuth
------

Authenticate users by an [HTTP Basic access authentication][BasicAuth_0]  call.
HTTP server of your choice to authenticate. It should return HTTP 2xx for correct credentials and an appropriate other error code for wrong ones or refused access.

### Configuration
The only supported parameter is the URL of the web server where the authentication happens.

Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => 'OC_User_BasicAuth',
            'arguments' => array('https://example.com/basic_auth'),
        ),
    ),


[BasicAuth_0]: https://en.wikipedia.org/wiki/Basic_access_authentication


XMPP (Prosody)
----
Authenticate Nextcloud users against a Prosody XMPP MySQL database.
Prosody user and password need to be given for the Nextcloud login


### Configuration
Add the following to your `config.php`:

    'user_backends' => array (
        0 => array (
            'class' => 'OC_User_XMPP',
                'arguments' => array (
                    0 => 'dbhost',
                    1 => 'prosodydb',
                    2 => 'dbuser',
                    3 => 'dbuserpassword',
                    4 => 'xmppdomain',
                ),
            ),
    ),


Alternatives
------------
Other extensions allow connecting to external user databases directly via SQL, which may be faster:

* [user_sql](https://github.com/nextcloud/user_sql)
* [user_backend_sql_raw](https://github.com/PanCakeConnaisseur/user_backend_sql_raw)
