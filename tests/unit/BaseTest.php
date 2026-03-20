<?php

declare(strict_types=1);

namespace OCA\UserExternal\Tests\Unit;

use OCA\UserExternal\Base;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal concrete implementation of Base for testing.
 */
class ConcreteBase extends Base {
	public function checkPassword($uid, $password): bool|string {
		return false;
	}
}

class BaseTest extends TestCase {
	private ConcreteBase $backend;
	private MockObject&IDBConnection $db;
	private MockObject&IUserManager $userManager;
	private MockObject&IGroupManager $groupManager;
	private MockObject&LoggerInterface $logger;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->db->method('escapeLikeParameter')->willReturnArgument(0);

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->backend = new ConcreteBase(
			'test-backend',
			$this->db,
			$this->userManager,
			$this->groupManager,
			$this->logger,
		);
	}

	/**
	 * Returns a fully configured fluent IQueryBuilder mock.
	 * All fluent methods return $this; executeQuery/executeStatement must be configured per-test.
	 */
	private function mockQueryBuilder(): MockObject&IQueryBuilder {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('iLike')->willReturn('1=1');

		$queryFunction = $this->createMock(IQueryFunction::class);
		$funcBuilder = $this->createMock(IFunctionBuilder::class);
		$funcBuilder->method('count')->willReturn($queryFunction);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('func')->willReturn($funcBuilder);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('orWhere')->willReturnSelf();
		$qb->method('delete')->willReturnSelf();
		$qb->method('update')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('setMaxResults')->willReturnSelf();
		$qb->method('setFirstResult')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnArgument(0);

		return $qb;
	}

	/** Returns a result mock that reports a given user count. */
	private function mockCountResult(int $count): MockObject&IResult {
		$result = $this->createMock(IResult::class);
		$result->method('fetchOne')->willReturn($count);
		$result->method('closeCursor')->willReturn(true);
		return $result;
	}

	// -------------------------------------------------------------------------
	// resolveUid
	// -------------------------------------------------------------------------

	public function testResolveUidPlainUsernameIsReturnedUnchanged(): void {
		$this->userManager->expects($this->never())->method('getByEmail');

		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		$result = $method->invoke($this->backend, 'john');

		$this->assertSame('john', $result);
	}

	public function testResolveUidEmailNotFoundInNcReturnsEmail(): void {
		$this->userManager->method('getByEmail')->with('john@example.com')->willReturn([]);

		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		$result = $method->invoke($this->backend, 'john@example.com');

		$this->assertSame('john@example.com', $result);
	}

	public function testResolveUidEmailFoundInBackendReturnsUid(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');
		$this->userManager->method('getByEmail')->with('john@example.com')->willReturn([$user]);

		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(1));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		$result = $method->invoke($this->backend, 'john@example.com');

		$this->assertSame('john', $result);
	}

	public function testResolveUidEmailFoundInNcButNotThisBackendReturnsEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john');
		$this->userManager->method('getByEmail')->with('john@example.com')->willReturn([$user]);

		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(0));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		$result = $method->invoke($this->backend, 'john@example.com');

		$this->assertSame('john@example.com', $result);
	}

	public function testResolveUidPicksFirstMatchInThisBackend(): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('alice');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('bob');
		$this->userManager->method('getByEmail')->willReturn([$user1, $user2]);

		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturnOnConsecutiveCalls(
			$this->mockCountResult(0), // alice → not found
			$this->mockCountResult(1), // bob → found
		);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$method = new \ReflectionMethod(Base::class, 'resolveUid');
		$result = $method->invoke($this->backend, 'shared@example.com');

		$this->assertSame('bob', $result);
	}

	// -------------------------------------------------------------------------
	// userExists
	// -------------------------------------------------------------------------

	public function testUserExistsReturnsTrueWhenFound(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(1));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertTrue($this->backend->userExists('john'));
	}

	public function testUserExistsReturnsFalseWhenNotFound(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(0));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertFalse($this->backend->userExists('john'));
	}

	// -------------------------------------------------------------------------
	// deleteUser
	// -------------------------------------------------------------------------

	public function testDeleteUserExecutesDeleteAndReturnsTrue(): void {
		$qb = $this->mockQueryBuilder();
		$qb->expects($this->once())->method('delete')->with('users_external')->willReturnSelf();
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertTrue($this->backend->deleteUser('john'));
	}

	// -------------------------------------------------------------------------
	// countUsers
	// -------------------------------------------------------------------------

	public function testCountUsersReturnsCount(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetchOne')->willReturn(42);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame(42, $this->backend->countUsers());
	}

	// -------------------------------------------------------------------------
	// getDisplayName
	// -------------------------------------------------------------------------

	public function testGetDisplayNameReturnsStoredDisplayName(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(['displayname' => 'John Doe']);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('John Doe', $this->backend->getDisplayName('john'));
	}

	public function testGetDisplayNameFallsBackToUidWhenEmpty(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(['displayname' => '']);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('john', $this->backend->getDisplayName('john'));
	}

	public function testGetDisplayNameFallsBackToUidWhenNull(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(['displayname' => null]);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('john', $this->backend->getDisplayName('john'));
	}

	// -------------------------------------------------------------------------
	// setDisplayName
	// -------------------------------------------------------------------------

	public function testSetDisplayNameReturnsFalseForUnknownUser(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(0));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertFalse($this->backend->setDisplayName('john', 'John Doe'));
	}

	public function testSetDisplayNameUpdatesAndReturnsTrue(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(1));
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertTrue($this->backend->setDisplayName('john', 'John Doe'));
	}

	// -------------------------------------------------------------------------
	// storeUser
	// -------------------------------------------------------------------------

	public function testStoreUserInsertsWhenUserIsNew(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(0));
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$method = new \ReflectionMethod(Base::class, 'storeUser');
		$method->invoke($this->backend, 'john');
	}

	public function testStoreUserSkipsInsertWhenUserAlreadyExists(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(1));
		$qb->expects($this->never())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$method = new \ReflectionMethod(Base::class, 'storeUser');
		$method->invoke($this->backend, 'john');
	}

	public function testStoreUserAddsGroupsOnFirstLogin(): void {
		$qb = $this->mockQueryBuilder();
		$qb->method('executeQuery')->willReturn($this->mockCountResult(0));
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())->method('get')->with('john')->willReturn($user);

		$group = $this->createMock(\OCP\IGroup::class);
		$group->expects($this->once())->method('addUser')->with($user);
		$this->groupManager->expects($this->once())->method('createGroup')->with('editors')->willReturn($group);

		$method = new \ReflectionMethod(Base::class, 'storeUser');
		$method->invoke($this->backend, 'john', ['editors']);
	}

	// -------------------------------------------------------------------------
	// getUsers
	// -------------------------------------------------------------------------

	public function testGetUsersReturnsUidList(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['uid' => 'alice'],
			['uid' => 'bob'],
			false,
		);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame(['alice', 'bob'], $this->backend->getUsers());
	}

	// -------------------------------------------------------------------------
	// getDisplayNames
	// -------------------------------------------------------------------------

	public function testGetDisplayNamesReturnsMap(): void {
		$qb = $this->mockQueryBuilder();
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['uid' => 'alice', 'displayname' => 'Alice A'],
			['uid' => 'bob', 'displayname' => 'Bob B'],
			false,
		);
		$result->expects($this->once())->method('closeCursor');
		$qb->method('executeQuery')->willReturn($result);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame(
			['alice' => 'Alice A', 'bob' => 'Bob B'],
			$this->backend->getDisplayNames(),
		);
	}
}
