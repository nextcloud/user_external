<?php
/**
 * Copyright (c) 2019 Sebastian Sterk <sebastian@wiuwiu.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * User authentication against a XMPP Prosody MySQL database
 *
 * @category Apps
 * @package  UserExternal
 * @author   Sebastian Sterk https://wiuwiu.de/Imprint
 * @license  http://www.gnu.org/licenses/agpl AGPL
 */
class OC_User_XMPP extends \OCA\user_external\Base {
	private $host;
	private $xmppDb;
	private $xmppDbUser;
	private $xmppDbPassword;
	private $xmppDomain;

	public function __construct($host, $xmppDb, $xmppDbUser, $xmppDbPassword, $xmppDomain) {
		parent::__construct($host);
		$this->host = $host;
		$this->xmppDb = $xmppDb;
		$this->xmppDbUser = $xmppDbUser;
		$this->xmppDbPassword = $xmppDbPassword;
		$this->xmppDomain = $xmppDomain;
	}

	public function hmacSha1($key, $data) {
                if (strlen($key) > 64) {
                        $key = str_pad(sha1($key, true), 64, chr(0));
		}
                if (strlen($key) < 64) {
                        $key = str_pad($key, 64, chr(0));
		}

                $oPad = str_repeat(chr(0x5C), 64);
                $iPad = str_repeat(chr(0x36), 64);

                for ($i = 0; $i < strlen($key); $i++) {
                        $oPad[$i] = $oPad[$i] ^ $key[$i];
                        $iPad[$i] = $iPad[$i] ^ $key[$i];
                }
                return sha1($oPad.sha1($iPad.$data, true));
        }
	
	public function checkPassword($uid, $password){
		$pdo = new PDO("mysql:host=$this->host;dbname=$this->xmppDb", $this->xmppDbUser, $this->xmppDbPassword);
		if(isset($uid) 
		   && isset($password)) {
        		if(!filter_var($uid, FILTER_VALIDATE_EMAIL) 
			   || !strpos($uid, $this->xmppDomain) 
			   || substr($uid, -strlen($this->xmppDomain)) !== $this->xmppDomain
			  ) {
				return false;
			}
			$user = explode("@", $uid);
        		$userName = strtolower($user[0]);
        		$submittedPassword = $password;
        		$statement = $pdo->prepare("SELECT * FROM prosody WHERE user = :user AND host = :xmppDomain AND store = 'accounts'");
        		$result = $statement->execute(array(
				'user' => $userName, 
				'xmppDomain' => $this->xmppDomain
			));
        		$user = $statement->fetchAll();
        		if(empty($user)) {
                		return false;
			}
        		foreach ($user as $key){
                        	if($key[3] === "salt") {
                        	        $internalSalt = $key['value'];
				}
                        	if($key[3] === "server_key") {
                        	        $internalServerKey = $key['value'];
				}
                        	if($key[3] === "stored_key") {
                        	        $internalStoredKey = $key['value'];
				}
		        }
	        	unset($user);
		        $internalIteration = '4096';

		        $newSaltedPassword = hash_pbkdf2('sha1', $submittedPassword, $internalSalt, $internalIteration, 0, true);
		        $newServerKey = $this->hmacSha1($newSaltedPassword, 'Server Key');
		        $newClientKey = $this->hmacSha1($newSaltedPassword, 'Client Key');
		        $newStoredKey = sha1(hex2bin($newClientKey));

		        if ($newServerKey === $internalServerKey 
			    && $newStoredKey === $internalStoredKey) {
				$uid = mb_strtolower($uid);
	                        $this->storeUser($uid);
				return $uid;
			} else {
				return false;
			}
		}
	}
}
