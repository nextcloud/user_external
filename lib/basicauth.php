<?php
/**
 * Copyright (c) 2019 Lutz Freitag <lutz.freitag@gottliebtfreitag.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class OC_User_BasicAuth extends \OCA\user_external\Base {

	private $authUrl;

	public function __construct($authUrl) {
		parent::__construct($authUrl);
		$this->authUrl =$authUrl;
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
		stream_context_set_default(array(
		  'http'=>array(
		    'method'=>"GET",
		    'header' => "authorization: Basic " . base64_encode("$uid:$password")
		  ))
		);
		$headers = get_headers($this->authUrl);

		if($headers === false) {
			OC::$server->getLogger()->error(
				'ERROR: Not possible to connect to BasicAuth Url: "'.$this->authUrl.'"', 
				['app' => 'user_external']
			);
			return false;
		}

		$returnCode= substr($headers[0], 9, 3);
		if(substr($returnCode, 0, 1) === '2') {
			$this->storeUser($uid);
			return $uid;
		} else {
			return false;
		}
	}
}
