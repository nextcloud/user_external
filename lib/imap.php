<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * User authentication against an IMAP mail server
 *
 * @category Apps
 * @package  UserExternal
 * @author   Robin Appelman <icewind@owncloud.com>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */
class OC_User_IMAP extends \OCA\user_external\Base {
	private $mailbox;
	private $domain;

	/**
	 * Create new IMAP authentication provider
	 *
	 * @param string $mailbox PHP imap_open mailbox definition, e.g.
	 *                        {127.0.0.1:143/imap/readonly}
	 * @param string $domain  If provided, loging will be restricted to this domain
	 */
	public function __construct($mailbox, $domain = '') {
		parent::__construct($mailbox);
		$this->mailbox=$mailbox;
		$this->domain=$domain;
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
		if (!function_exists('imap_open')) {
			OCP\Util::writeLog('user_external', 'ERROR: PHP imap extension is not installed', OCP\Util::ERROR);
			return false;
		}

		// Replace escaped @ symbol in uid (which is a mail address)
		// but only if there is no @ symbol and if there is a %40 inside the uid
		if (!(strpos($uid, '@') !== false) && (strpos($uid, '%40') !== false)) {
			$uid = str_replace("%40","@",$uid);
		}

                $result = OC_DB::executeAudited(
                        'SELECT `userid` FROM `*PREFIX*preferences` WHERE `appid`=? AND `configkey`=? AND `configvalue`=?',
                        array('settings','email',$uid)
                );

		$users = array();
		while ($row = $result->fetchRow()) {
			$users[] = $row['userid'];
		}

		if(count($users) === 1) {
			$username = $uid;
			$uid = $users[0];
 		// Check if we only want logins from ONE domain and strip the domain part from UID		
		}elseif($this->domain != '') {
 			$pieces = explode('@', $uid);
 			if(count($pieces) == 1) {
 				$username = $uid . "@" . $this->domain;
 			}elseif((count($pieces) == 2) and ($pieces[1] == $this->domain)) {
 				$username = $uid;
 				$uid = $pieces[0];
 			}else{
 				return false;
 			}
 		}else{
 			$username = $uid;
 		}
 
 		$mbox = @imap_open($this->mailbox, $username, $password, OP_HALFOPEN, 1);
		imap_errors();
		imap_alerts();
		if($mbox !== FALSE) {
			imap_close($mbox);
			$uid = mb_strtolower($uid);
			$this->storeUser($uid);
			return $uid;
		}else{
			return false;
		}
	}
}
