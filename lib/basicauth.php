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
		/*
		 * Connect without user/name password to make sure
		 * URL is indeed authenticating or not...
		 */
		$context = stream_context_create(array(
		  'http' => array(
		    'method' => "GET",
		    'follow_location' => 0
		  ))
		);
		$canary = get_headers($this->authUrl, 1, $context);
		if(!$canary) {
			OC::$server->getLogger()->error(
				'ERROR: Not possible to connect to BasicAuth Url: '.$this->authUrl,
				['app' => 'user_external']
			);
			return false;
		}
		if (!isset(array_change_key_case($canary, CASE_LOWER)['www-authenticate'])) {
			OC::$server->getLogger()->error(
				'ERROR: Mis-configured BasicAuth Url: '.$this->authUrl.', provided URL does not do authentication!',
				['app' => 'user_external']
			);
			return false;
		}

		$context = stream_context_create(array(
		  'http' => array(
		    'method' => "GET",
		    'header' => "authorization: Basic " . base64_encode("$uid:$password"),
		    'follow_location' => 0
		  ))
		);
		$headers = get_headers($this->authUrl, 1, $context);

		if(!$headers) {
			OC::$server->getLogger()->error(
				'ERROR: Not possible to connect to BasicAuth Url: '.$this->authUrl, 
				['app' => 'user_external']
			);
			return false;
		}
		/* get_headers() follows redirects up to a maximum (default: 20)
		 * the response code of the last request is stored in the numerically greatest item
		 * $headers[0] is always present
		 */
		$responseIdx = 0;
		foreach (array_keys($headers) as $key) {
			if (gettype($key) === "integer" && $responseIdx < $key) {
				$responseIdx = $key;
			}
		}
		switch (substr($headers[$responseIdx], 9, 1)) {
			case "2":
				return $this->storeUser($uid);
			case "3":
				OC::$server->getLogger()->error(
					'ERROR: Too many redirects from BasicAuth Url: '.$this->authUrl, 
					['app' => 'user_external']
				);
				return false;
		}
		return false;
	}
}
