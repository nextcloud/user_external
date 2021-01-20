<?php
/**
 * Copyright (c) 2019 Sebastian Sterk <sebastian@wiuwiu.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * User authentication against a generic HTTP auth interface
 *
 * @category Apps
 * @package  UserExternal
 * @author   Sebastian Sterk https://wiuwiu.de/Imprint
 * @license  http://www.gnu.org/licenses/agpl AGPL
 */
class OC_User_HTTP extends \OCA\user_external\Base {
	private $hashAlgo;
	private $accessKey;
	private $authenticationEndpoint;

	public function __construct($authenticationEndpoint, $hashAlgo = false, $accessKey = '') {
		parent::__construct($authenticationEndpoint);
		$this->authenticationEndpoint = $authenticationEndpoint;
		$this->hashAlgo = $hashAlgo;
		$this->accessKey = $accessKey;
	}

	public function sendUserData($user, $password){
		if($this->hashAlgo !== false){
			$password = $this->hashPassword($password);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->authenticationEndpoint);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 
			http_build_query(array(
				'accessKey' => $this->accessKey,
				'user' => $user,
				'password' => $password
				)
			)
		);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if($httpCode == 202){
			return true;
		} else{
			return false;
		}

	}

	public function hashPassword($password){
		return hash($this->hashAlgo, $password);
	}

	public function checkPassword($uid, $password){
		if(isset($uid) 
		   && isset($password)) {

			$authenticationStatus = $this->sendUserData($uid, $password);
			if ($authenticationStatus) {
				$uid = mb_strtolower($uid);
                $this->storeUser($uid);
				return $uid;
			} else {
				return false;
			}
		}
	}
}
