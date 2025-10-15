<?php
/**
 * Copyright (c) 2021 Sebastian Sterk <sebastian@wiuwiu.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\UserExternal;

use OCP\Http\Client\IClientService;

/**
 * User authentication against a generic HTTP auth interface
 *
 * @category Apps
 * @package  UserExternal
 * @author   Sebastian Sterk https://wiuwiu.de/Imprint
 * @license  http://www.gnu.org/licenses/agpl AGPL
 */
class HTTP extends Base {
	private $hashAlgo;
	private $accessKey;
	private $authenticationEndpoint;
	private $httpClientService;

	/**
	 * Create new HTTP authentication provider
	 *
	 * @param string $authenticationEndpoint The HTTP endpoint URL for authentication
	 * @param string|false $hashAlgo Hash algorithm for password (false for plain text)
	 * @param string $accessKey Access key for additional security
	 */
	public function __construct($authenticationEndpoint, $hashAlgo = false, $accessKey = '') {
		parent::__construct($authenticationEndpoint);
		$this->authenticationEndpoint = $authenticationEndpoint;
		$this->hashAlgo = $hashAlgo;
		$this->accessKey = $accessKey;
		$this->httpClientService = \OC::$server->get(IClientService::class);
	}

	/**
	 * Send user credentials to HTTP endpoint
	 *
	 * @param string $user The username
	 * @param string $password The password
	 *
	 * @return bool True if authentication successful, false otherwise
	 */
	public function sendUserData($user, $password) {
		if ($this->hashAlgo !== false) {
			$password = $this->hashPassword($password);
		}

		try {
			$client = $this->httpClientService->newClient();
			
			$response = $client->post($this->authenticationEndpoint, [
				'form_params' => [
					'accessKey' => $this->accessKey,
					'user' => $user,
					'password' => $password
				],
				'timeout' => 10,
			]);

			$statusCode = $response->getStatusCode();
			
			if ($statusCode === 202) {
				return true;
			} else {
				return false;
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->error(
				'ERROR: Could not connect to HTTP auth endpoint: ' . $e->getMessage(),
				['app' => 'user_external']
			);
			return false;
		}
	}

	/**
	 * Hash password using configured algorithm
	 *
	 * @param string $password The plain text password
	 *
	 * @return string The hashed password
	 */
	private function hashPassword($password) {
		return hash($this->hashAlgo, $password);
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid      The username
	 * @param string $password The password
	 *
	 * @return string|false The username on success, false on failure
	 */
	public function checkPassword($uid, $password) {
		if (isset($uid) && isset($password)) {
			$authenticationStatus = $this->sendUserData($uid, $password);
			if ($authenticationStatus) {
				$uid = mb_strtolower($uid);
				$this->storeUser($uid);
				return $uid;
			} else {
				return false;
			}
		}
		return false;
	}
}
