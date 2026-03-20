<?php

declare(strict_types=1);

namespace OCA\UserExternal\Tests\Integration;

use OCA\UserExternal\Base;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Server;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Minimal concrete implementation of Base for integration testing.
 */
class ConcreteBase extends Base {
	public function checkPassword($uid, $password): bool|string {
		return false;
	}
}

/**
 * Integration tests for Base — verifies real DB operations and the NC32
 * email-to-uid resolution against a live Nextcloud database.
 *
 * @group DB
 */
class BaseIntegrationTest extends TestCase {
	use UserTrait;

	private ConcreteBase $backend;
	private IDBConnection $db;
	private IConfig $config;

	/** Unique backend name per test run to avoid cross-test pollution. */
	private string $backendName;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpUserTrait();

		$this->backendName = 'integration-test-' . uniqid();
		$this->backend = new ConcreteBase($this->backendName);
		$this->db = Server::get(IDBConnection::class);
		$this->config = Server::get(IConfig::class);
	}

	protected function tearDown(): void {
		$this->cleanupBackend();
		$this->tearDownUserTrait();
		parent::tearDown();
	}

	private function cleanupBackend(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('users_external')
			->where($qb->expr()->eq('backend', $qb->createNamedParameter($this->backendName)));
		$qb->executeStatement();
	}

	private function storeUser(string $uid, array $groups = []): void {
		$method = new \ReflectionMethod(Base::class, 'storeUser');
		$method->invoke($this->backend, $uid, $groups);
	}

	private function resolveUid(string $loginName): string {
		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		return $method->invoke($this->backend, $loginName);
	}

	// -------------------------------------------------------------------------
	// storeUser / userExists
	// -------------------------------------------------------------------------

	public function testStoreUserCreatesRecord(): void {
		$this->assertFalse($this->backend->userExists('alice'));

		$this->storeUser('alice');

		$this->assertTrue($this->backend->userExists('alice'));
	}

	public function testStoreUserIsIdempotent(): void {
		$this->storeUser('alice');
		$this->storeUser('alice'); // second call must not throw or duplicate

		$this->assertSame(1, $this->backend->countUsers());
	}

	// -------------------------------------------------------------------------
	// deleteUser
	// -------------------------------------------------------------------------

	public function testDeleteUserRemovesRecord(): void {
		$this->storeUser('alice');
		$this->assertTrue($this->backend->userExists('alice'));

		$this->backend->deleteUser('alice');

		$this->assertFalse($this->backend->userExists('alice'));
	}

	public function testDeleteUserReturnsTrueEvenIfUserMissing(): void {
		$this->assertTrue($this->backend->deleteUser('nonexistent'));
	}

	// -------------------------------------------------------------------------
	// countUsers
	// -------------------------------------------------------------------------

	public function testCountUsersReflectsStoredUsers(): void {
		$this->assertSame(0, $this->backend->countUsers());

		$this->storeUser('alice');
		$this->assertSame(1, $this->backend->countUsers());

		$this->storeUser('bob');
		$this->assertSame(2, $this->backend->countUsers());

		$this->backend->deleteUser('alice');
		$this->assertSame(1, $this->backend->countUsers());
	}

	// -------------------------------------------------------------------------
	// getDisplayName / setDisplayName
	// -------------------------------------------------------------------------

	public function testGetDisplayNameFallsBackToUidBeforeSet(): void {
		$this->storeUser('alice');

		$this->assertSame('alice', $this->backend->getDisplayName('alice'));
	}

	public function testSetAndGetDisplayName(): void {
		$this->storeUser('alice');

		$this->backend->setDisplayName('alice', 'Alice Wonderland');

		$this->assertSame('Alice Wonderland', $this->backend->getDisplayName('alice'));
	}

	public function testSetDisplayNameReturnsFalseForUnknownUser(): void {
		$this->assertFalse($this->backend->setDisplayName('ghost', 'Ghost'));
	}

	// -------------------------------------------------------------------------
	// getUsers
	// -------------------------------------------------------------------------

	public function testGetUsersReturnsStoredUids(): void {
		$this->storeUser('alice');
		$this->storeUser('bob');

		$users = $this->backend->getUsers();
		sort($users);

		$this->assertSame(['alice', 'bob'], $users);
	}

	public function testGetUsersSearchFilters(): void {
		$this->storeUser('alice');
		$this->storeUser('alex');
		$this->storeUser('bob');

		$users = $this->backend->getUsers('al');
		sort($users);

		$this->assertSame(['alex', 'alice'], $users);
	}

	public function testGetUsersRespectsLimit(): void {
		$this->storeUser('alice');
		$this->storeUser('bob');
		$this->storeUser('charlie');

		$users = $this->backend->getUsers('', 2);

		$this->assertCount(2, $users);
	}

	// -------------------------------------------------------------------------
	// getDisplayNames
	// -------------------------------------------------------------------------

	public function testGetDisplayNamesReturnsMap(): void {
		$this->storeUser('alice');
		$this->storeUser('bob');
		$this->backend->setDisplayName('alice', 'Alice A');
		$this->backend->setDisplayName('bob', 'Bob B');

		$names = $this->backend->getDisplayNames();

		$this->assertSame('Alice A', $names['alice']);
		$this->assertSame('Bob B', $names['bob']);
	}

	// -------------------------------------------------------------------------
	// resolveUid — NC32 email login handling
	// -------------------------------------------------------------------------

	public function testResolveUidReturnsPlainUsernameUnchanged(): void {
		$this->assertSame('alice', $this->resolveUid('alice'));
	}

	public function testResolveUidReturnsEmailWhenNoMatchFound(): void {
		$result = $this->resolveUid('unknown@example.com');

		$this->assertSame('unknown@example.com', $result);
	}

	public function testResolveUidResolvesEmailToUidForKnownUser(): void {
		// Create a real NC user via UserTrait's dummy backend
		$this->createUser('alice', 'password');
		// Set their NC account email (what IUserManager::getByEmail() searches)
		$this->config->setUserValue('alice', 'settings', 'email', 'alice@example.com');
		// Register alice in this external backend
		$this->storeUser('alice');

		$result = $this->resolveUid('alice@example.com');

		$this->assertSame('alice', $result);
	}

	public function testResolveUidReturnsEmailWhenUserNotInThisBackend(): void {
		// NC user exists with that email, but they are NOT in our users_external backend
		$this->createUser('alice', 'password');
		$this->config->setUserValue('alice', 'settings', 'email', 'alice@example.com');
		// Note: no storeUser() call

		$result = $this->resolveUid('alice@example.com');

		// Falls back to the email since alice isn't in this backend
		$this->assertSame('alice@example.com', $result);
	}

	public function testResolveUidPicksCorrectUserWhenMultipleShareEmail(): void {
		// Two NC users with the same email (edge case); only bob is in this backend
		$this->createUser('alice', 'password');
		$this->createUser('bob', 'password');
		$this->config->setUserValue('alice', 'settings', 'email', 'shared@example.com');
		$this->config->setUserValue('bob', 'settings', 'email', 'shared@example.com');
		$this->storeUser('bob');

		$result = $this->resolveUid('shared@example.com');

		$this->assertSame('bob', $result);
	}
}
