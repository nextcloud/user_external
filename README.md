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
Add the following to your `config.php`:

    'user_backends' => array(
        array(
            'class' => 'OC_User_IMAP',
            'arguments' => array(
                '127.0.0.1', 993, ssl, 'example.com'
            ),
        ),
    ),

This connects to the IMAP server on IP `127.0.0.1`.
The default port is 143; however if you want to restrict the domain, you need to specify the port and set sslmode to `null`.
If a domain name (e.g. example.com) is specified, then this makes sure that
only users from this domain will be allowed to login. After successfull
login the domain part will be striped and the rest used as username in
NextCloud. e.g. 'username@example.com' will be 'username' in NextCloud.



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

Alternatives
------------
Other extensions allow connecting to external user databases directly via SQL, which may be faster:

* [user_sql](https://github.com/nextcloud/user_sql)
* [user_backend_sql_raw](https://github.com/PanCakeConnaisseur/user_backend_sql_raw)
