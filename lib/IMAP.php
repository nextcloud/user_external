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

use OCP\ILogger;

/**
 * User authentication against an IMAP mail server
 */
class IMAP extends Base {
	private string $mailbox;
	private int $port;
	private ?string $sslmode;
	private string $domain;
	private bool $stripeDomain;
	private bool $groupDomain;

	/**
	 * @param string $mailbox IMAP server domain/IP
	 * @param int|null $port IMAP server port
	 * @param string|null $sslmode ssl|tls|null
	 * @param string|null $domain If provided, login will be restricted to this domain
	 * @param bool $stripeDomain Whether to strip the domain part from the username
	 * @param bool $groupDomain Whether to add the user to a group matching the email domain
	 */
	public function __construct(
		string $mailbox,
		?int $port = null,
		?string $sslmode = null,
		?string $domain = null,
		bool $stripeDomain = true,
		bool $groupDomain = false
	) {
		parent::__construct($mailbox);
		$this->mailbox = $mailbox;
		$this->port = $port ?? 143;
		$this->sslmode = $sslmode;
		$this->domain = $domain ?? '';
		$this->stripeDomain = $stripeDomain;
		$this->groupDomain = $groupDomain;
	}

	private function logger(): ILogger {
		/** @var ILogger $logger */
		$logger = \OC::$server->get(ILogger::class);
		return $logger;
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return string|false
	 */
	public function checkPassword($uid, $password) {
		if ($password === '') {
			return false;
		}

		// Replace escaped @ symbol in uid (which is a mail address)
		if (strpos($uid, '@') === false && strpos($uid, '%40') !== false) {
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
				$this->logger()->error(
					'User has a wrong domain. Expected: ' . $this->domain,
					['app' => 'user_external']
				);
				return false;
			}
		} else {
			$username = $uid;
		}

		$groups = [];
		if (count($pieces) > 1 && $this->groupDomain && !empty($pieces[1])) {
			$groups[] = $pieces[1];
		}

		$protocol = ($this->sslmode === 'ssl') ? 'imaps' : 'imap';
		$url = sprintf('%s://%s:%d', $protocol, $this->mailbox, $this->port);

		$ch = curl_init();
		if ($ch === false) {
			$this->logger()->error(
				'Could not initialize curl for IMAP authentication.',
				['app' => 'user_external']
			);
			return false;
		}

		if ($this->sslmode === 'tls') {
			curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'CAPABILITY');

		curl_exec($ch);
		$errorcode = curl_errno($ch);

		if ($errorcode === 0) {
			curl_close($ch);
			$uid = mb_strtolower($uid);
			$this->storeUser($uid, $groups);
			return $uid;
		}

		if (
			$errorcode === CURLE_COULDNT_CONNECT ||
			$errorcode === CURLE_SSL_CONNECT_ERROR ||
			$errorcode === CURLE_OPERATION_TIMEDOUT
		) {
			$this->logger()->error(
				'Could not connect to IMAP server via curl: ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		} elseif (
			$errorcode === CURLE_REMOTE_ACCESS_DENIED ||
			$errorcode === CURLE_LOGIN_DENIED ||
			(defined('CURLE_AUTH_ERROR') && $errorcode === CURLE_AUTH_ERROR)
		) {
			$this->logger()->warning(
				'IMAP login failed via curl: ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		} else {
			$this->logger()->error(
				'IMAP server returned an error: ' . $errorcode . ' / ' . curl_strerror($errorcode),
				['app' => 'user_external']
			);
		}

		curl_close($ch);
		return false;
	}
}
