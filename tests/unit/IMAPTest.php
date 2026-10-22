<?php

declare(strict_types=1);

namespace OCA\UserExternal\Tests\Unit;

use OCA\UserExternal\IMAP;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Testable subclass that stubs executeCurl() so no network calls are made.
 */
class TestableIMAP extends IMAP {
	public int $curlErrorCode = 0;
	/** @var array<int, mixed>|null Captured curl options from the last executeCurl() call */
	public ?array $capturedOptions = null;

	protected function executeCurl(array $options): int {
		$this->capturedOptions = $options;
		return $this->curlErrorCode;
	}
}

class IMAPTest extends TestCase {
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
	}

	private function makeBackend(?string $sslmode = null, ?string $loginOptions = null): TestableIMAP {
		return new TestableIMAP(
			'imap.example.com',
			993,
			$sslmode,
			null,
			true,
			false,
			$loginOptions,
			$this->db,
			$this->userManager,
			$this->groupManager,
			$this->logger,
		);
	}

	private function mockQueryBuilder(int $existingUserCount = 0): MockObject&IQueryBuilder {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('iLike')->willReturn('1=1');

		$queryFunction = $this->createMock(IQueryFunction::class);
		$funcBuilder = $this->createMock(IFunctionBuilder::class);
		$funcBuilder->method('count')->willReturn($queryFunction);

		$countResult = $this->createMock(IResult::class);
		$countResult->method('fetchOne')->willReturn($existingUserCount);
		$countResult->method('closeCursor')->willReturn(true);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('func')->willReturn($funcBuilder);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnArgument(0);
		$qb->method('executeQuery')->willReturn($countResult);

		return $qb;
	}

	// -------------------------------------------------------------------------
	// Login options
	// -------------------------------------------------------------------------

	public function testLoginOptionsArePassedToCurl(): void {
		$backend = $this->makeBackend(null, 'AUTH=DIGEST-MD5');
		$backend->curlErrorCode = 67;

		$backend->checkPassword('user', 'pass');

		$this->assertSame('AUTH=DIGEST-MD5', $backend->capturedOptions[CURLOPT_LOGIN_OPTIONS]);
	}

	public function testNullLoginOptionsAreNotPassedToCurl(): void {
		$backend = $this->makeBackend(null, null);
		$backend->curlErrorCode = 67;

		$backend->checkPassword('user', 'pass');

		$this->assertArrayNotHasKey(CURLOPT_LOGIN_OPTIONS, $backend->capturedOptions);
	}

	public function testEmptyLoginOptionsAreNotPassedToCurl(): void {
		$backend = $this->makeBackend(null, '');
		$backend->curlErrorCode = 67;

		$backend->checkPassword('user', 'pass');

		$this->assertArrayNotHasKey(CURLOPT_LOGIN_OPTIONS, $backend->capturedOptions);
	}

	// -------------------------------------------------------------------------
	// SSL modes
	// -------------------------------------------------------------------------

	public function testSslModeUsesImapsScheme(): void {
		$backend = $this->makeBackend('ssl');
		$backend->curlErrorCode = 67;

		$backend->checkPassword('user', 'pass');

		$this->assertSame('imaps://imap.example.com:993', $backend->capturedOptions[CURLOPT_URL]);
		$this->assertArrayNotHasKey(CURLOPT_USE_SSL, $backend->capturedOptions);
	}

	public function testTlsModeRequiresStartTls(): void {
		$backend = $this->makeBackend('tls');
		$backend->curlErrorCode = 67;

		$backend->checkPassword('user', 'pass');

		$this->assertSame('imap://imap.example.com:993', $backend->capturedOptions[CURLOPT_URL]);
		$this->assertSame(CURLUSESSL_ALL, $backend->capturedOptions[CURLOPT_USE_SSL]);
	}

	// -------------------------------------------------------------------------
	// Result handling
	// -------------------------------------------------------------------------

	public function testSuccessStoresAndReturnsLowercasedUid(): void {
		$backend = $this->makeBackend();

		$qb = $this->mockQueryBuilder(0); // new user
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user', $backend->checkPassword('User', 'pass'));
		$this->assertSame('User:pass', $backend->capturedOptions[CURLOPT_USERPWD]);
	}

	public function testLoginDeniedReturnsFalseAndLogsError(): void {
		$backend = $this->makeBackend();
		$backend->curlErrorCode = 67; // CURLE_LOGIN_DENIED

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('IMAP Login failed'));

		$this->assertFalse($backend->checkPassword('user', 'wrongpass'));
	}
}
