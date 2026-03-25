<?php

declare(strict_types=1);

namespace OCA\UserExternal\Tests\Unit;

use OCA\UserExternal\WebDavAuth;
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
 * Testable subclass that stubs fetchUrl() so no network calls are made.
 * Each entry in $fetchResponses is returned in order on successive fetchUrl() calls.
 */
class TestableWebDavAuth extends WebDavAuth {
	/** @var list<string[]|null> */
	public array $fetchResponses = [];
	/** @var list<mixed> Captured stream contexts from fetchUrl() calls */
	public array $capturedContexts = [];

	protected function fetchUrl(string $url, mixed $context = null): ?array {
		$this->capturedContexts[] = $context;
		return array_shift($this->fetchResponses);
	}
}

class WebDavAuthTest extends TestCase {
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

	private function makeBackend(string $url = 'https://example.com/dav', string $authType = 'basic'): TestableWebDavAuth {
		return new TestableWebDavAuth(
			$url,
			$authType,
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
	// URL validation
	// -------------------------------------------------------------------------

	public function testInvalidUrlReturnsFalse(): void {
		$backend = $this->makeBackend('not-a-valid-url');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testNonHttpSchemeReturnsFalse(): void {
		$backend = $this->makeBackend('ftp://example.com/dav');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testUrlWithUserinfoReturnsFalse(): void {
		$backend = $this->makeBackend('https://user:pass@example.com/dav');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	// -------------------------------------------------------------------------
	// Invalid auth type
	// -------------------------------------------------------------------------

	public function testInvalidAuthTypeReturnsFalseAndLogsError(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'kerberos');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	// -------------------------------------------------------------------------
	// Basic auth
	// -------------------------------------------------------------------------

	public function testBasicAuthSuccessStoresAndReturnsUid(): void {
		$backend = $this->makeBackend();
		$backend->fetchResponses = [['HTTP/1.1 200 OK']];

		$qb = $this->mockQueryBuilder(0); // new user
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user', $backend->checkPassword('user', 'pass'));
	}

	public function testBasicAuthSuccessDoesNotInsertExistingUser(): void {
		$backend = $this->makeBackend();
		$backend->fetchResponses = [['HTTP/1.1 200 OK']];

		$qb = $this->mockQueryBuilder(1); // already exists
		$qb->expects($this->never())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user', $backend->checkPassword('user', 'pass'));
	}

	public function testBasicAuthWrongPasswordReturnsFalse(): void {
		$backend = $this->makeBackend();
		$backend->fetchResponses = [['HTTP/1.1 401 Unauthorized']];

		$this->assertFalse($backend->checkPassword('user', 'wrongpass'));
	}

	public function testBasicAuthConnectionFailureReturnsFalse(): void {
		$backend = $this->makeBackend();
		$backend->fetchResponses = [null];
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	// -------------------------------------------------------------------------
	// Digest auth
	// -------------------------------------------------------------------------

	private function digestChallenge(string $realm = 'example', string $nonce = 'abc123', string $opaque = 'xyz'): array {
		return [
			'HTTP/1.1 401 Unauthorized',
			"WWW-Authenticate: Digest realm=\"{$realm}\", nonce=\"{$nonce}\", qop=\"auth\", opaque=\"{$opaque}\"",
		];
	}

	public function testDigestAuthSuccessStoresAndReturnsUid(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge(),   // challenge request
			['HTTP/1.1 200 OK'],        // authenticated request
		];

		$qb = $this->mockQueryBuilder(0);
		$qb->expects($this->once())->method('executeStatement');
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user', $backend->checkPassword('user', 'pass'));
	}

	public function testDigestAuthWrongPasswordReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge(),
			['HTTP/1.1 401 Unauthorized'],
		];

		$this->assertFalse($backend->checkPassword('user', 'wrongpass'));
	}

	public function testDigestAuthConnectionFailureOnChallengeReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [null]; // server unreachable
		$this->logger->expects($this->atLeast(1))->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testDigestAuthNoChallengeHeaderReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [['HTTP/1.1 200 OK']]; // no WWW-Authenticate header
		$this->logger->expects($this->atLeast(1))->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testDigestAuthConnectionFailureOnAuthRequestReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge(),
			null, // authenticated request fails
		];
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testDigestAuthComputesCorrectResponseHashWithQop(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge('myrealm', 'mynonce'),
			['HTTP/1.1 200 OK'],
		];

		$qb = $this->mockQueryBuilder(1);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('alice', $backend->checkPassword('alice', 's3cr3t'));

		$authContext = $backend->capturedContexts[1];
		$opts = stream_context_get_options($authContext);
		$header = $opts['http']['header'];

		$this->assertStringContainsString('uri="/dav"', $header);
		$this->assertStringContainsString('qop=auth', $header);

		$this->assertSame(1, preg_match('/response="([^"]+)"/', $header, $m));
		$this->assertSame(1, preg_match('/cnonce="([^"]+)"/', $header, $cm));
		$cnonce = $cm[1];

		$A1 = md5('alice:myrealm:s3cr3t');
		$A2 = md5('HEAD:/dav');
		$expected = md5($A1 . ':mynonce:00000001:' . $cnonce . ':auth:' . $A2);
		$this->assertSame($expected, $m[1]);
	}

	public function testDigestAuthComputesCorrectResponseHashWithoutQop(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			['HTTP/1.1 401 Unauthorized', 'WWW-Authenticate: Digest realm="myrealm", nonce="mynonce"'],
			['HTTP/1.1 200 OK'],
		];

		$qb = $this->mockQueryBuilder(1);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('alice', $backend->checkPassword('alice', 's3cr3t'));

		$authContext = $backend->capturedContexts[1];
		$opts = stream_context_get_options($authContext);
		$header = $opts['http']['header'];

		$this->assertStringContainsString('uri="/dav"', $header);
		$this->assertStringNotContainsString('qop=', $header);
		$this->assertStringNotContainsString('cnonce=', $header);

		$this->assertSame(1, preg_match('/response="([^"]+)"/', $header, $m));

		$A1 = md5('alice:myrealm:s3cr3t');
		$A2 = md5('HEAD:/dav');
		$expected = md5($A1 . ':mynonce:' . $A2);
		$this->assertSame($expected, $m[1]);
	}

	public function testDigestAuthWithOpaqueIncludedInHeader(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge('realm', 'nonce', 'opaquevalue'),
			['HTTP/1.1 200 OK'],
		];

		$qb = $this->mockQueryBuilder(1);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user', $backend->checkPassword('user', 'pass'));

		$authContext = $backend->capturedContexts[1];
		$opts = stream_context_get_options($authContext);
		$header = $opts['http']['header'];
		$this->assertStringContainsString('opaque="opaquevalue"', $header);
	}

	public function testDigestAuthUnsupportedAlgorithmReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			['HTTP/1.1 401 Unauthorized', 'WWW-Authenticate: Digest realm="r", nonce="n", algorithm="SHA-256"'],
		];
		$this->logger->expects($this->atLeast(1))->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testDigestAuthEscapesSpecialCharactersInUsername(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			$this->digestChallenge(),
			['HTTP/1.1 200 OK'],
		];

		$qb = $this->mockQueryBuilder(1);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->assertSame('user"evil', $backend->checkPassword('user"evil', 'pass'));

		$authContext = $backend->capturedContexts[1];
		$opts = stream_context_get_options($authContext);
		$header = $opts['http']['header'];
		$this->assertStringContainsString('username="user\\"evil"', $header);
		$this->assertStringNotContainsString("\r", $header);
		$this->assertStringNotContainsString("\n", $header);
	}

	public function testDigestAuthAuthIntOnlyReturnsFalse(): void {
		$backend = $this->makeBackend('https://example.com/dav', 'digest');
		$backend->fetchResponses = [
			['HTTP/1.1 401 Unauthorized', 'WWW-Authenticate: Digest realm="r", nonce="n", qop="auth-int"'],
		];
		$this->logger->expects($this->atLeast(1))->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}

	public function testBasicAuthRedirectLogsError(): void {
		$backend = $this->makeBackend();
		$backend->fetchResponses = [['HTTP/1.1 302 Found']];
		$this->logger->expects($this->atLeast(1))->method('error');

		$this->assertFalse($backend->checkPassword('user', 'pass'));
	}
}
