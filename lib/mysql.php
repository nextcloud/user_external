<?php
	/**
	 * User authentication against a MySQL/MariaDB server
	 *
	 * @category Apps
	 * @package  UserExternal
	 * @author   Oscar Krause <oscar.krause@collinwebdesigns.de>
	 * @license  http://www.gnu.org/licenses/agpl AGPL
	 * @link     http://github.com/owncloud/apps
	 */
	class OC_User_MySQL extends \OCA\user_external\Base{
		private $host;
		private $user;
		private $pass;
		private $db;
		private $table;
		private $col_user;
		private $col_pass;
		private $pdo;
		/**
		 * Create new MySQL authentication provider
		 *
		 * @param string  $host     Hostname or IP of MySQL server
		 * @param string  $user     Username to login
		 * @param string  $pass     Password to login
		 * @param string  $db       Database
		 * @param string  $table    Table with the credentials
		 * @param string  $col_user Coloumn with the Username/UID
		 * @param string  $col_pass Coloumn with the Password
		 */
		public function __construct($host,$user,$pass,$db,$table,$col_user,$col_pass) {
			$this->host=$host;
			$this->user=$user;
			$this->pass=$pass;
			$this->db=$db;
			$this->table=$table;
			$this->col_user=$col_user;
			$this->col_pass=$col_pass;
			$this->pdo = new PDO("mysql:host=".$this->host.";dbname=".$this->db.";charset=utf8",$this->user,$this->pass);
			parent::__construct('mysql://' . $this->host);
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
			$sth = $this->pdo->prepare('SELECT count(*) as cnt FROM ' . $this->table . ' WHERE ' . $this->col_user . ' = :user AND ' . $this->col_pass . ' = :pass');
			$sth->bindParam(':user', $uid);
			$sth->bindParam(':pass', $password);
			$sth->execute();
			$result = $sth->fetchAll();
			if(count($result) == 1) {
				$this->storeUser($uid);
				return $uid;
			}else{
				return false;
			}
		}
	}