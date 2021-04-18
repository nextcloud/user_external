<?php
/**
 * Copyright (c) 2021 Mark Costlow  <cheeks@swcp.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * User authentication against a RADIUS server
 *
 * @category Apps
 * @package  UserExternal
 * @author   Mark Costlow  <cheeks@swcp.com>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */
class OC_User_RADIUS extends \OCA\user_external\Base {
	private $radius;
	private $radserver;
	private $port;
	private $secret;
	private $authmethod;
	private $domain;
	private $stripDomain;
	private $groupDomain;

	/**
	 * Create new IMAP authentication provider
	 *
	 * @param string $radserver RADIUS server domain/IP
	 * @param int $port RADIUS server $port
	 * @param string $secret   RADIUS client secret
	 * @param string $authmethod   PAP, CHAP, etc
	 * @param string $domain  If provided, logins will be restricted to this domain
	 * @param boolean $stripDomain (whether to strip the domain part from the username or not)
	 * @param boolean $groupDomain (whether to add the usere to a group corresponding to the domain of the address)
	 */
	public function __construct($radserver, $port = null, $secret = null, $authmethod = null, $domain = null, $stripDomain = true, $groupDomain = false) {
		parent::__construct($radserver);
		$this->radserver = $radserver;
		$this->port = $port === null ? 1645 : $port;
		$this->secret = $secret;
		$this->authmethod = $authmethod === null ? 'PAP' : $authmethod;
		$this->domain = $domain === null ? '' : $domain;
		$this->stripDomain = $stripDomain;
		$this->groupDomain = $groupDomain;
	}

	/**
	 * Authenticate the user against the RADIUS server
	 *
	 * @param string $uid      The username
	 * @param string $password The password
	 *
	 * @return true/false
	 */
	public function checkPassword($uid, $password) {
		// Replace escaped @ symbol in uid (which is a mail address)
		// but only if there is no @ symbol and if there is a %40 inside the uid
		if (!(strpos($uid, '@') !== false) && (strpos($uid, '%40') !== false)) {
			$uid = str_replace("%40","@",$uid);
		}

		$pieces = explode('@', $uid);
		if ($this->domain !== '') {
			if (count($pieces) === 1) {
				$username = $uid . '@' . $this->domain;
			} else if(count($pieces) === 2 && $pieces[1] === $this->domain) {
				$username = $uid;
				if ($this->stripDomain) {
					$uid = $pieces[0];
				}
			} else {
				OC::$server->getLogger()->error(
					'ERROR: User has a wrong domain! Expecting: '.$this->domain,
					['app' => 'user_external']
				);
				return false;
			}
		} else {
			$username = $uid;
 		}

		$groups = [];
		if ($this->groupDomain && $pieces[1]) {
			$groups[] = $pieces[1];
		}

		$this->radius = radius_auth_open();
		if (! radius_add_server($this->radius, $this->radserver,
					$this->port, $this->secret, 3,3)) {
			OC::$server->getLogger()->error(
				'ERROR: RADIUS: ' .  radius_strerror($this->radius),
				['app' => 'user_external']
			);
			return false;
		}

		// create a radius request, then put attributes in it
		if (!radius_create_request($this->radius, RADIUS_ACCESS_REQUEST)) {
			OC::$server->getLogger()->error(
				'ERROR: RADIUS: ' .  radius_strerror($this->radius),
				['app' => 'user_external']
			);
			return false;
		}

		if (! radius_put_attr($this->radius, RADIUS_USER_NAME, $username)) {
			OC::$server->getLogger()->error(
				'ERROR: RADIUS: put_attr failed for RADIUS_USER_NAME(' . $username . ') ' . radius_strerror($this->radius),
				['app' => 'user_external']
			);
			return false;
		}
		if (! radius_put_attr($this->radius, RADIUS_USER_PASSWORD, $password)) {
			OC::$server->getLogger()->error(
				'ERROR: RADIUS: put_attr failed for RADIUS_USER_PASSWORD' . radius_strerror($this->radius),
				['app' => 'user_external']
			);
			return false;
		}

		$rad_response = radius_send_request($this->radius);

		switch ($rad_response) {
			case RADIUS_ACCESS_ACCEPT:
				$uid = mb_strtolower($uid);
				$this->storeUser($uid, $groups);
				return $uid;
				break;
			case RADIUS_ACCESS_REJECT:
				OC::$server->getLogger()->error(
					'ERROR: RADIUS: access denied for ' . $username,
					['app' => 'user_external']
				);
				break;
			case RADIUS_ACCESS_CHALLENGE:
				OC::$server->getLogger()->error(
					'ERROR: RADIUS: challenge requested, but not supported yet',
					['app' => 'user_external']
				);
				break;
			default:
				OC::$server->getLogger()->error(
					'ERROR: RADIUS: unknown response: ' . radius_strerror($this->radius),
					['app' => 'user_external']
				);
		}

		// if we fall through, we did not auth successfully
		return false;
	}
}
