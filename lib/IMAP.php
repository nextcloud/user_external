<?php

/**
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Jonas Sulzer <jonas@violoncello.ch>
 * @copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OCA\UserExternal;

use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * User authentication against an IMAP mail server
 *
 * @category Apps
 * @package  UserExternal
 * @author   Robin Appelman <icewind@owncloud.com>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */
class IMAP extends Base {
	private $mailbox;
	private $port;
	private $sslmode;
	private $domain;
	private $stripeDomain;
	private $groupDomain;
	private $loginOptions;

	/**
	 * Create new IMAP authentication provider
	 *
	 * @param string $mailbox IMAP server domain/IP
	 * @param int $port IMAP server $port
	 * @param string $sslmode
	 * @param string $domain If provided, loging will be restricted to this domain
	 * @param boolean $stripeDomain (whether to stripe the domain part from the username or not)
	 * @param boolean $groupDomain (whether to add the usere to a group corresponding to the domain of the address)
	 * @param string|null $loginOptions curl login options selecting the authentication mechanism
	 *                                  (for example AUTH=PLAIN, AUTH=DIGEST-MD5, AUTH=NTLM, AUTH=GSSAPI or AUTH=*)
	 */
	public function __construct(
		$mailbox,
		$port = null,
		$sslmode = null,
		$domain = null,
		$stripeDomain = true,
		$groupDomain = false,
		$loginOptions = null,
		?IDBConnection $db = null,
		?IUserManager $userManager = null,
		?IGroupManager $groupManager = null,
		?LoggerInterface $logger = null,
	) {
		parent::__construct($mailbox, $db, $userManager, $groupManager, $logger);
		$this->mailbox = $mailbox;
		$this->port = $port === null ? 143 : $port;
		$this->sslmode = $sslmode;
		$this->domain = $domain === null ? '' : $domain;
		$this->stripeDomain = $stripeDomain;
		$this->groupDomain = $groupDomain;
		$this->loginOptions = $loginOptions;
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 *
	 * @return true/false
	 */
	public function checkPassword($uid, $password) {
		$uid = $this->resolveUid($uid);

		// Replace escaped @ symbol in uid (which is a mail address)
		// but only if there is no @ symbol and if there is a %40 inside the uid
		if (!(strpos($uid, '@') !== false) && (strpos($uid, '%40') !== false)) {
			$uid = str_replace('%40', '@', $uid);
		}

		$pieces = explode('@', $uid);
		if ($this->domain !== '') {
			if (count($pieces) === 1) {
				$username = $uid . '@' . $this->domain;
			} elseif (count($pieces) === 2 && $pieces[1] === $this->domain) {
				$username = $uid;
				if ($this->stripeDomain) {
					$uid = $pieces[0];
				}
			} else {
				$this->logger->error(
					'ERROR: User has a wrong domain! Expecting: ' . $this->domain,
					['app' => 'user_external']
				);
				return false;
			}
		} else {
			$username = $uid;
		}

		$groups = [];
		if ((count($pieces) > 1) && $this->groupDomain && $pieces[1]) {
			$groups[] = $pieces[1];
		}

		$protocol = ($this->sslmode === 'ssl') ? 'imaps' : 'imap';
		$options = [
			CURLOPT_URL => "{$protocol}://{$this->mailbox}:{$this->port}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => $username . ':' . $password,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_CUSTOMREQUEST => 'CAPABILITY',
		];
		if ($this->sslmode === 'tls') {
			$options[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
		}
		// The admin UI stores an empty string when no login options are set
		if (is_string($this->loginOptions) && $this->loginOptions !== '') {
			$options[CURLOPT_LOGIN_OPTIONS] = $this->loginOptions;
		}

		$errorcode = $this->executeCurl($options);

		if ($errorcode === 0) {
			$uid = mb_strtolower($uid);
			$this->storeUser($uid, $groups);
			return $uid;
		} elseif ($errorcode === CURLE_COULDNT_CONNECT
			   || $errorcode === CURLE_SSL_CONNECT_ERROR
			   || $errorcode === 28) {
			# This is not defined in PHP-8.x
			# 28: CURLE_OPERATION_TIMEDOUT
			$this->logger->error(
				'ERROR: Could not connect to imap server via curl: ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		} elseif ($errorcode === 9
			   || $errorcode === 67
			   || $errorcode === 94) {
			# These are not defined in PHP-8.x
			# 9: CURLE_REMOTE_ACCESS_DENIED
			# 67: CURLE_LOGIN_DENIED
			# 94: CURLE_AUTH_ERROR)
			$this->logger->error(
				'ERROR: IMAP Login failed via curl: ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		} else {
			$this->logger->error(
				'ERROR: IMAP server returned an error: ' . $errorcode . ' / ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		}

		return false;
	}

	/**
	 * Run the IMAP request
	 *
	 * @param array<int, mixed> $options curl options
	 *
	 * @return int curl error code, 0 on success
	 */
	protected function executeCurl(array $options): int {
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		curl_exec($ch);
		return curl_errno($ch);
	}
}
