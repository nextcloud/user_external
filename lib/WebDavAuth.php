<?php
/**
 * Copyright (c) 2015 Thomas Müller <thomas.mueller@tmit.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\UserExternal;

class WebDavAuth extends Base {
	private $webDavAuthUrl;

	public function __construct($webDavAuthUrl, $authType = 'basic') {
		$this->webDavAuthUrl = $webDavAuthUrl;
		$this->authType = $authType;
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid      The username
	 * @param string $password The password
	 *
	 * @return true/false
	 */
	public function checkPassword($uid, $password) {
		$arr = explode('://', $this->webDavAuthUrl, 2);
		if (! isset($arr) or count($arr) !== 2) {
			\OC::$server->getLogger()->error('ERROR: Invalid WebdavUrl: "'.$this->webDavAuthUrl.'" ', ['app' => 'user_external']);
			return false;
		}
		list($protocol, $path) = $arr;
		$url = $protocol.'://'.$path;

		switch ($this->authType) {
			case 'digest':
				// Initial unauthenticated request
				@file_get_contents($url);
				if (!isset($http_response_header)) {
					\OC::$server->getLogger()->error('ERROR: Not possible to connect to WebDAV Url: "'.$protocol.'://'.$path.'" ', ['app' => 'user_external']);
					return false;
				}

				// Find the WWW-Authenticate header
				foreach ($http_response_header as $header) {
					if (strpos($header, 'WWW-Authenticate: Digest') === 0) {
						$auth_header = substr($header, strlen('WWW-Authenticate: Digest '));
						break;
					}
				}

				// Parse the header to get the parameters
				$auth_params = array();
				foreach (explode(',', $auth_header) as $param) {
					list($key, $value) = explode('=', $param, 2);
					$auth_params[trim($key)] = trim($value, ' "');
				}

				// Generate a cnonce (client nonce) value
				$cnonce = bin2hex(openssl_random_pseudo_bytes(8));

				// Generate the response value
				$A1 = md5($uid . ':' . $auth_params['realm'] . ':' . $password);
				$A2 = md5('GET:' . $url);
				$response = md5($A1 . ':' . $auth_params['nonce'] . ':00000001:' . $cnonce . ':auth:' . $A2);

				// Construct the Authorization header
				$auth_header = 'Authorization: Digest username="' . $uid . '", realm="' . $auth_params['realm'] .
					'", nonce="' . $auth_params['nonce'] . '", uri="' . $url . '", cnonce="' . $cnonce .
					'", nc=00000001, qop=auth, response="' . $response . '", opaque="' . $auth_params['opaque'] . '"';

				// Make the authenticated request
				$context = stream_context_create(array(
					'http' => array(
						'method' => 'GET',
						'header' => $auth_header
					)
				));
				break;
			case 'basic':
				$url = $protocol.'://'.urlencode($uid).':'.urlencode($password).'@'.$path;
				$context = stream_context_create(array(
					'http' => array(
						'method' => 'GET',
						'header' => 'Authorization: Basic ' . base64_encode($uid . ':' . $password)
					)
				));
				break;
			default:
				\OC::$server->getLogger()->error('ERROR: Invalid authentication type: "'.$this->authType.'". Expected "basic" or "digest".', ['app' => 'user_external']);
				return false;
		}

		$result = @file_get_contents($url, false, $context);
		if ($result === false) {
			\OC::$server->getLogger()->error('ERROR: Not possible to connect to WebDAV Url: "'.$url.'" ', ['app' => 'user_external']);
			return false;
		}

		$headers = $http_response_header;
		$returnCode = substr($headers[0], 9, 3);

		if (substr($returnCode, 0, 1) === '2') {
			$this->storeUser($uid);
			return $uid;
		} else {
			return false;
		}
	}
}
