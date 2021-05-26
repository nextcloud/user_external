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

use OCP\AppFramework\Http;

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
	 * @return string $uid      The username
	 */
	public function checkPassword($uid, $password) {
		$postdata = json_encode(array("user"=>array("id"=>$uid,"password"=>$password)));

		$client = \OC::$server->getHTTPClientService()->newClient();
		try {
		    $response = $client->post($this->authUrl."/_nextcloud/user_external/v1/authenticate", [
		       'body' => $postdata,
               'timeout' => 10,
               'connect_timeout' => 10,
            ]);
            $result = json_decode($response->getBody(),true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
		    if($e->getCode() === Http::STATUS_UNAUTHORIZED || $e->getCode() === Http::STATUS_FORBIDDEN) {
                OC::$server->getLogger()->error(
                    'ERROR: Resource on REST server is forbidden on: '.$this->authUrl,
                    ['app' => 'user_external']
                );
            } elseif($e->getCode() === Http::STATUS_NOT_FOUND) {
                OC::$server->getLogger()->error(
                    'ERROR: REST Server sent 404 on: '.$this->authUrl,
                    ['app' => 'user_external']
                );
            } else {
                OC::$server->getLogger()->error(
                    'ERROR: Unknown request error on: '.$this->authUrl,
                    ['app' => 'user_external']
                );
            }
		    $result = false;
        }

        if(is_array($result)) {
            if($result['auth']['success']===true) {
                $user_exists = $this->userExists($result['auth']['id']);
                $uid = $this->storeUser($result['auth']['id'],$result['auth']['groups']);
                if(key_exists("displayName",$result['auth']) && (($user_exists && $this->alwaysAssignDisplayname) || (!$user_exists))) {
                    $this->setDisplayName($result['auth']['id'],$result['auth']['displayName']);
                }
                //return $result['auth']['id'];
                return $uid;
            } else {
                return false;
            }
        } elseif($result===false) {
            OC::$server->getLogger()->error(
                'ERROR: Not possible to connect to REST Url: '.$this->authUrl,
                ['app' => 'user_external']
            );
        }
        return false;
	}
}
