<?php
/**
 * Copyright (c) 2020 Michael Schindler <mich.schindl@gmail.com>
 * Copyright (c) 2019 Lutz Freitag <lutz.freitag@gottliebtfreitag.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


/**
 * This script perfoms an authentication via a POST JSON REST Request.
 * To use this method, you need to implement a single REST endpoint:
 * Path: /_nextcloud/user_external/v1/authenticate
 * Method: POST
 * Body as JSON UTF-8:
 *
 * {
 *      "user": {
 *          "id": "UserID",
 *          "password": "enteredPassword"
 *      }
 * }
 *
 * If the transmitted credentials are correct, your JSON answer should be:
 *
 * {
 *      "auth": {
 *          "success": true,
 *          "id": "UserID",
 *          "displayName": "Display Name to Store",
 *          "groups": ["group1","group2"]
 *      }
 * }
 *
 * If the Authentication fails, your JSON answer should be:
 *
 * {
 *      "auth": {
 *          "success": false
 *      }
 * }
 *
 */

class OC_User_REST extends \OCA\user_external\Base {

	private $authUrl;
	private $alwaysAssignDisplayname;

	public function __construct($authUrl,$alwaysAssignDisplayname) {
		parent::__construct($authUrl,$alwaysAssignDisplayname);
		$this->authUrl = $authUrl;
		$this->alwaysAssignDisplayname = $alwaysAssignDisplayname;
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
		$postdata = json_encode(array("user"=>array("id"=>$uid,"password"=>$password)));
		$ch = curl_init();
		$headers = ['Content-Type: application/json'];
		curl_setopt($ch,CURLOPT_URL,$this->authUrl."/_nextcloud/user_external/v1/authenticate");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
		$result = curl_exec($ch);
		$statusCode = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);

        $result = json_decode($result,true);
        if($statusCode=="404") {
            OC::$server->getLogger()->error(
                'ERROR: REST Server sent 404 on: '.$this->authUrl,
                ['app' => 'user_external']
            );
        } elseif(is_array($result)) {
            if($result['auth']['success']===true) {
                $user_exists = $this->userExists($result['auth']['id']);
                $this->storeUser($result['auth']['id'],$result['auth']['groups']);
                if(($user_exists && $this->alwaysAssignDisplayname) || (!$user_exists)) {
                    $this->setDisplayName($result['auth']['id'],$result['auth']['displayName']);
                }
                return $result['auth']['id'];
            } else {
                return false;
            }
        } elseif($result===false) {
            OC::$server->getLogger()->error(
                'ERROR: Not possible to connect to REST Url: '.$this->authUrl,
                ['app' => 'user_external']
            );
        }
	}
}
