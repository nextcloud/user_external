<?php

declare(strict_types=1);

/**
 * Copyright (c) 2015 Thomas Müller <thomas.mueller@tmit.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\UserExternal;

use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class WebDavAuth extends Base {
	private string $webDavAuthUrl;
	private string $authType;

	public function __construct(
		string $webDavAuthUrl,
		string $authType = 'basic',
		?IDBConnection $db = null,
		?IUserManager $userManager = null,
		?IGroupManager $groupManager = null,
		?LoggerInterface $logger = null,
	) {
		parent::__construct($webDavAuthUrl, $db, $userManager, $groupManager, $logger);
		$this->webDavAuthUrl = $webDavAuthUrl;
		$this->authType = $authType;
	}

	/**
	 * Check if the password is correct without logging in the user.
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return string|false The uid on success, false on failure
	 */
	public function checkPassword($uid, $password) {
		$uid = $this->resolveUid($uid);

		$parsed = parse_url($this->webDavAuthUrl);
		if ($parsed === false
			|| !isset($parsed['scheme'], $parsed['host'])
			|| !in_array($parsed['scheme'], ['http', 'https'], true)
			|| isset($parsed['user'])
		) {
			$this->logger->error('Invalid WebDAV URL: "' . $this->webDavAuthUrl . '"', ['app' => 'user_external']);
			return false;
		}
		$url = $this->webDavAuthUrl;

		switch ($this->authType) {
			case 'basic':
				$responseHeaders = $this->fetchWithBasicAuth($url, $uid, $password);
				break;
			case 'digest':
				$responseHeaders = $this->fetchWithDigestAuth($url, $uid, $password);
				break;
			default:
				$this->logger->error(
					'Invalid WebDAV auth type: "' . $this->authType . '". Expected "basic" or "digest".',
					['app' => 'user_external'],
				);
				return false;
		}

		if ($responseHeaders === null) {
			return false;
		}

		$returnCode = substr($responseHeaders[0], 9, 3);
		if (str_starts_with($returnCode, '2')) {
			$this->storeUser($uid);
			return $uid;
		}
		return false;
	}

	/**
	 * Perform a HEAD request with HTTP Basic authentication.
	 *
	 * @return string[]|null Response headers, or null on connection failure.
	 */
	protected function fetchWithBasicAuth(string $url, string $uid, string $password): ?array {
		$context = stream_context_create(['http' => [
			'method' => 'HEAD',
			'header' => 'Authorization: Basic ' . base64_encode($uid . ':' . $password),
			'ignore_errors' => true,
			'follow_location' => 0,
		]]);
		$responseHeaders = $this->fetchUrl($url, $context);
		if ($responseHeaders === null) {
			$this->logger->error('Not possible to connect to WebDAV URL: "' . $url . '"', ['app' => 'user_external']);
			return null;
		}

		$returnCode = substr($responseHeaders[0], 9, 3);
		if (str_starts_with($returnCode, '3')) {
			$this->logger->error(
				'WebDAV URL returned a redirect (' . $returnCode . '). Redirects are not followed for authenticated requests to prevent credential leaking.',
				['app' => 'user_external'],
			);
			return null;
		}

		return $responseHeaders;
	}

	/**
	 * Perform a two-step HEAD request with HTTP Digest authentication.
	 *
	 * @return string[]|null Response headers, or null on connection failure or missing challenge.
	 */
	protected function fetchWithDigestAuth(string $url, string $uid, string $password): ?array {
		// Step 1: unauthenticated request to receive the server challenge
		$challengeContext = stream_context_create(['http' => [
			'method' => 'HEAD',
			'ignore_errors' => true,
			'follow_location' => 0,
		]]);
		$challengeHeaders = $this->fetchUrl($url, $challengeContext);
		if ($challengeHeaders === null) {
			$this->logger->error('Not possible to connect to WebDAV URL: "' . $url . '"', ['app' => 'user_external']);
			return null;
		}

		$challengeCode = substr($challengeHeaders[0], 9, 3);
		if (str_starts_with($challengeCode, '3')) {
			$this->logger->error(
				'WebDAV Digest challenge returned a redirect (' . $challengeCode . '). Redirects are not followed to prevent sending credentials to an unintended host.',
				['app' => 'user_external'],
			);
			return null;
		}

		// Step 2: find the WWW-Authenticate: Digest header
		$authHeaderValue = null;
		foreach ($challengeHeaders as $header) {
			if (stripos($header, 'WWW-Authenticate: Digest ') === 0) {
				$authHeaderValue = substr($header, strlen('WWW-Authenticate: Digest '));
				break;
			}
		}

		if ($authHeaderValue === null) {
			$this->logger->error('No Digest challenge received from WebDAV URL: "' . $url . '"', ['app' => 'user_external']);
			return null;
		}

		// Step 3: parse the challenge parameters
		$params = [];
		preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|([^\s,]+))/', $authHeaderValue, $matches, PREG_SET_ORDER);
		foreach ($matches as $m) {
			$params[$m[1]] = $m[2] !== '' ? $m[2] : $m[3];
		}

		if (!isset($params['realm'], $params['nonce'])) {
			$this->logger->error('Invalid Digest challenge from WebDAV URL: "' . $url . '"', ['app' => 'user_external']);
			return null;
		}

		$algorithm = $params['algorithm'] ?? 'MD5';
		if ($algorithm !== 'MD5') {
			$this->logger->error(
				'Unsupported Digest algorithm: "' . $algorithm . '". Only MD5 is supported.',
				['app' => 'user_external'],
			);
			return null;
		}

		// Step 4: compute the digest response
		$parsedUrl = parse_url($url);
		$uri = $parsedUrl['path'] ?? '/';
		if (isset($parsedUrl['query'])) {
			$uri .= '?' . $parsedUrl['query'];
		}

		$qopTokens = isset($params['qop']) ? array_map('trim', explode(',', $params['qop'])) : [];
		$useQop = in_array('auth', $qopTokens, true);
		if (!empty($qopTokens) && !$useQop) {
			$this->logger->error(
				'Unsupported Digest qop: "' . $params['qop'] . '". Only "auth" is supported.',
				['app' => 'user_external'],
			);
			return null;
		}

		try {
			$A1 = md5($uid . ':' . $params['realm'] . ':' . $password);
			$A2 = md5('HEAD:' . $uri);

			if ($useQop) {
				$cnonce = bin2hex(random_bytes(8));
				$nc = '00000001';
				$response = md5($A1 . ':' . $params['nonce'] . ':' . $nc . ':' . $cnonce . ':auth:' . $A2);
			} else {
				$response = md5($A1 . ':' . $params['nonce'] . ':' . $A2);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Failed to compute Digest response: ' . $e->getMessage(), ['app' => 'user_external']);
			return null;
		}

		$digestHeader = sprintf(
			'Authorization: Digest username="%s", realm="%s", nonce="%s", uri="%s", response="%s"',
			$this->escapeDigestValue($uid),
			$this->escapeDigestValue($params['realm']),
			$this->escapeDigestValue($params['nonce']),
			$this->escapeDigestValue($uri),
			$response,
		);
		if ($useQop) {
			$digestHeader .= sprintf(', cnonce="%s", nc=%s, qop=auth', $cnonce, $nc);
		}
		if (isset($params['opaque'])) {
			$digestHeader .= sprintf(', opaque="%s"', $this->escapeDigestValue($params['opaque']));
		}

		// Step 5: send the authenticated request
		$context = stream_context_create(['http' => [
			'method' => 'HEAD',
			'header' => $digestHeader,
			'ignore_errors' => true,
			'follow_location' => 0,
		]]);
		$responseHeaders = $this->fetchUrl($url, $context);
		if ($responseHeaders === null) {
			$this->logger->error('Digest authenticated request failed for WebDAV URL: "' . $url . '"', ['app' => 'user_external']);
			return null;
		}

		$authCode = substr($responseHeaders[0], 9, 3);
		if (str_starts_with($authCode, '3')) {
			$this->logger->error(
				'WebDAV Digest authenticated request returned a redirect (' . $authCode . '). Redirects are not followed to prevent credential leaking.',
				['app' => 'user_external'],
			);
			return null;
		}

		return $responseHeaders;
	}

	private function escapeDigestValue(string $value): string {
		$value = str_replace(["\r", "\n"], '', $value);
		return addcslashes($value, '"\\');
	}

	/**
	 * Perform an HTTP request and return the response headers.
	 * Extracted so tests can stub network calls without hitting the wire.
	 *
	 * @return string[]|null Response headers, or null if the server is unreachable.
	 */
	protected function fetchUrl(string $url, mixed $context = null): ?array {
		$http_response_header = null;
		if ($context !== null) {
			$result = @file_get_contents($url, false, $context);
		} else {
			$result = @file_get_contents($url);
		}
		if ($result === false && $http_response_header === null) {
			return null;
		}
		return $http_response_header;
	}
}
