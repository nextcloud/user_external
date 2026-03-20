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

use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

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
	protected string $backend = '';
	protected readonly LoggerInterface $logger;
	private readonly IDBConnection $db;
	private readonly IUserManager $userManager;
	private readonly IGroupManager $groupManager;

	/**
	 * Create new instance, set backend name.
	 *
	 * Dependencies are optional to allow backends to be instantiated with just
	 * a backend string (as before), while still being injectable for testing.
	 *
	 * @param string $backend Identifier of the backend
	 */
	public function __construct(
		string $backend,
		?IDBConnection $db = null,
		?IUserManager $userManager = null,
		?IGroupManager $groupManager = null,
		?LoggerInterface $logger = null,
	) {
		$this->backend = $backend;
		$this->logger = $logger ?? Server::get(LoggerInterface::class);
		$this->db = $db ?? Server::get(IDBConnection::class);
		$this->userManager = $userManager ?? Server::get(IUserManager::class);
		$this->groupManager = $groupManager ?? Server::get(IGroupManager::class);
	}

	/**
	 * Delete a user
	 *
	 * @param string $uid The username of the user to delete
	 *
	 * @return bool
	 */
	public function deleteUser($uid) {
		$query = $this->db->getQueryBuilder();
		$query->delete('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->executeStatement();
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
		$query = $this->db->getQueryBuilder();
		$query->select('displayname')
			->from('users_external')
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->executeQuery();
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
		$query = $this->db->getQueryBuilder();
		$query->select('uid', 'displayname')
			->from('users_external')
			->where($query->expr()->iLike('displayname', $query->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')))
			->orWhere($query->expr()->iLike('uid', $query->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->executeQuery();

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
		$query = $this->db->getQueryBuilder();
		$query->select('uid')
			->from('users_external')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($this->db->escapeLikeParameter($search) . '%')))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		if ($limit) {
			$query->setMaxResults($limit);
		}
		if ($offset) {
			$query->setFirstResult($offset);
		}
		$result = $query->executeQuery();

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
	 * @param string $uid The username
	 * @param string $displayName The new display name
	 *
	 * @return true/false
	 */
	public function setDisplayName($uid, $displayName) {
		if (!$this->userExists($uid)) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('users_external')
			->set('displayname', $query->createNamedParameter($displayName))
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$query->executeStatement();

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
	protected function storeUser($uid, $groups = []) {
		if (!$this->userExists($uid)) {
			$query = $this->db->getQueryBuilder();
			$query->insert('users_external')
				->values([
					'uid' => $query->createNamedParameter($uid),
					'backend' => $query->createNamedParameter($this->backend),
				]);
			$query->executeStatement();

			if ($groups) {
				$createduser = $this->userManager->get($uid);
				foreach ($groups as $group) {
					$this->groupManager->createGroup($group)->addUser($createduser);
				}
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
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_users'))
			->from('users_external')
			->where($query->expr()->iLike('uid', $query->createNamedParameter($this->db->escapeLikeParameter($uid))))
			->andWhere($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->executeQuery();
		$users = $result->fetchOne();
		$result->closeCursor();

		return $users > 0;
	}

	/**
	 * Count the number of users.
	 *
	 * @return int the number of users
	 */
	public function countUsers() {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_users'))
			->from('users_external')
			->where($query->expr()->eq('backend', $query->createNamedParameter($this->backend)));
		$result = $query->executeQuery();
		$users = $result->fetchOne();
		$result->closeCursor();

		return $users;
	}

	/**
	 * Resolve a login name to a uid stored in this backend.
	 *
	 * Since Nextcloud 32, when a user logs in with their account email address,
	 * the email is passed directly to checkPassword() instead of the uid.
	 * This method maps the email back to the uid stored in users_external so
	 * that backends can authenticate correctly regardless of how the user logged in.
	 *
	 * @param string $loginName The login name (uid or email address)
	 * @return string The resolved uid, or the original loginName if no match is found
	 */
	protected function resolveUid(string $loginName): string {
		if (strpos($loginName, '@') === false) {
			return $loginName;
		}

		$users = $this->userManager->getByEmail($loginName);
		foreach ($users as $user) {
			$uid = $user->getUID();
			if ($this->userExists($uid)) {
				return $uid;
			}
		}

		return $loginName;
	}
}
