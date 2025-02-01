<?php
/**
 * @author Jonas Sulzer <jonas@violoncello.ch>
 * @author Christian Weiske <cweiske@cweiske.de>
 * @copyright (c) 2014 Christian Weiske <cweiske@cweiske.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OCA\UserExternal;

/**
 * Base class for external auth implementations that stores users
 * on their first login in a local table.
 * This is required for making many of the user-related ownCloud functions
 * work, including sharing files with them.
 *
 * @category Apps
 * @package  UserExternal
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  http://www.gnu.org/licenses/agpl AGPL
 * @link     http://github.com/owncloud/apps
 */
abstract class Base extends \OC\User\Backend {
	protected $backend = '';

	/**
	 * Create new instance, set backend name
	 *
	 * @param string $backend Identifier of the backend
	 */
	public function __construct($backend) {
		$this->backend = $backend;
	}

	/**
	 * Delete a user
	 *
	 * @param string $uid The username of the user to delete
	 *
	 * @return bool
	 */
	public function deleteUser($uid) {
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->execute();
		return true;
	}

	/**
	 * Get display name of the user
	 *
	 * @param string $uid user ID of the user
	 *
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->select('displayname')
			->from('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->execute();
		$user = $result->fetch();
		$result->closeCursor();

		$displayName = trim($user['displayname'] ?? '', ' ');
		if (!empty($displayName)) {
			return $displayName;
		} else {
			return $uid;
		}
	}

	/**
	 * Get a list of all display names and user ids.
	 *
	 * @return array with all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select('uid', 'displayname')
			->from('users_external')
			->where($query->expr()->iLike('displayname', $query->createNamedParameter('%' . $connection->escapeLikeParameter($search) . '%')))
			->orWhere($query->expr()->iLike('uid', $query->createNamedParameter('%' . $connection->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->execute();

		$displayNames = [];
		while ($row = $result->fetch()) {
			$displayNames[$row['uid']] = $row['displayname'];
		}
		$result->closeCursor();

		return $displayNames;
	}

	/**
	 * Get a list of all users
	 *
	 * @return array with all uids
	 */
	public function getUsers($search = '', $limit = null, $offset = null) {
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select('uid')
			->from('users_external')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($connection->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->execute();

		$users = [];
		while ($row = $result->fetch()) {
			$users[] = $row['uid'];
		}
		$result->closeCursor();

		return $users;
	}

	/**
	 * Determines if the backend can enlist users
	 *
	 * @return bool
	 */
	public function hasUserListings() {
		return true;
	}

	/**
	 * Change the display name of a user
	 *
	 * @param string $uid         The username
	 * @param string $displayName The new display name
	 *
	 * @return true/false
	 */
	public function setDisplayName($uid, $displayName) {
		if (!$this->userExists($uid)) {
			return false;
		}

		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->update('users_external')
			->set('displayname', $query->createNamedParameter($displayName))
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->execute();

		return true;
	}

	/**
	 * Create user record in database
	 *
	 * @param string $uid The username
	 * @param array $groups Groups to add the user to on creation
	 *
	 * @return void
	 */
	protected function storeUser($uid, $groups = [], $email = '') {
		if (!$this->userExists($uid)) {
			$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
			$query->insert('users_external')
				->values([
					'uid' => $query->createNamedParameter($uid),
					'backend' => $query->createNamedParameter($this->backend),
				]);
			$query->execute();

			if ($groups) {
				$createduser = \OC::$server->getUserManager()->get($uid);
				foreach ($groups as $group) {
					\OC::$server->getGroupManager()->createGroup($group)->addUser($createduser);
				}
			}
			
			if ($email) {
				$config = \OC::$server->getConfig();
				$config->setUserValue( $uid, 'settings', 'email', $email);
			}
		}
	}

	/**
	 * Check if a user exists
	 *
	 * @param string $uid the username
	 *
	 * @return boolean
	 */
	public function userExists($uid) {
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_users'))
			->from('users_external')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($connection->escapeLikeParameter($uid))))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->execute();
		$users = $result->fetchColumn();
		$result->closeCursor();

		return $users > 0;
	}

	/**
	 * Count the number of users.
	 *
	 * @return int the number of users
	 */
	public function countUsers() {
		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_users'))
			->from('users_external')
			->where($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->execute();
		$users = $result->fetchColumn();
		$result->closeCursor();

		return $users;
	}
}
