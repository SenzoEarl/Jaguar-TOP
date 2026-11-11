<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\RateLimitException;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Auth\Repositories\LoginAttemptRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\RateLimiterService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Argon2id password security, transparent rehash upgrades, generic
 * authentication failures, DB-backed brute-force lockouts, and hardened sessions.
 */
final class AuthSecurityTest extends TestCase
{
    private Connection $connection;
    private Config $config;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->config = Config::loadFromRoot($root);

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('
            CREATE TABLE departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                full_name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                role_level INTEGER NOT NULL DEFAULT 0,
                department_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                last_login_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                was_successful INTEGER NOT NULL DEFAULT 0,
                user_agent TEXT NOT NULL DEFAULT "",
                attempted_at TEXT NOT NULL
            );
            CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity_type TEXT NULL,
                entity_id TEXT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NOT NULL,
                metadata_json TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->connection = Connection::fromPdo($pdo);
    }

    public function testArgon2idHashingAndTransparentRehashUpgrade(): void
    {
        $session = Session::createInMemory();
        $session->start();
        $auth = $this->buildAuthService($session);

        $hash = $auth->hashPassword('StrongPass!2026');
        self::assertTrue(password_verify('StrongPass!2026', $hash));
        self::assertFalse(password_verify('WrongPass!2026', $hash));

        // Seed an account with a legacy lower-cost bcrypt hash to force rehash on login.
        $legacyHash = password_hash('LegacyPass123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->connection->execute(
            'INSERT INTO users (username, email, full_name, password_hash, role_level, is_active)
             VALUES (:u, :e, :f, :h, 0, 1)',
            ['u' => 'legacyguest', 'e' => 'legacy@user.com', 'f' => 'Legacy Guest', 'h' => $legacyHash]
        );

        $request = $this->makePostRequest('/account/login', '203.0.113.10');
        $user = $auth->attemptLogin('legacyguest', 'LegacyPass123!', $request);
        self::assertSame('legacyguest', $user->getUsername());

        $updatedRow = $this->connection->fetchOne(
            'SELECT password_hash FROM users WHERE id = :id',
            ['id' => $user->getId()]
        );
        self::assertNotNull($updatedRow);
        self::assertNotSame($legacyHash, $updatedRow['password_hash']);
    }

    public function testGenericFailurePreventsUsernameEnumerationAndTriggersLockout(): void
    {
        $session = Session::createInMemory();
        $session->start();
        $auth = $this->buildAuthService($session);

        $this->connection->execute(
            'INSERT INTO users (username, email, full_name, password_hash, role_level, is_active)
             VALUES (:u, :e, :f, :h, 0, 1)',
            [
                'u' => 'user0',
                'e' => 'user0@user.com',
                'f' => 'Demo Guest',
                'h' => password_hash('user0@user.com', PASSWORD_BCRYPT, ['cost' => 4]),
            ]
        );

        $request = $this->makePostRequest('/account/login', '203.0.113.25');

        $unknownMessage = '';
        try {
            $auth->attemptLogin('nonexistent_account', 'badpass', $request);
        } catch (AuthenticationException $e) {
            $unknownMessage = $e->getPublicMessage();
        }

        $knownMessage = '';
        try {
            $auth->attemptLogin('user0', 'wrongpass', $request);
        } catch (AuthenticationException $e) {
            $knownMessage = $e->getPublicMessage();
        }

        self::assertNotSame('', $unknownMessage);
        self::assertSame($unknownMessage, $knownMessage);

        // Exhaust the default per-username threshold (5 failures) using test attempts.
        for ($i = 0; $i < 4; $i++) {
            try {
                $auth->attemptLogin('user0', 'wrongpass', $request);
            } catch (AuthenticationException) {
                // Expected until threshold is reached.
            }
        }

        $this->expectException(RateLimitException::class);
        $auth->attemptLogin('user0', 'user0@user.com', $request);
    }

    public function testSessionTimeoutsRegenerationAndDestroyClearState(): void
    {
        $now = time();
        $session = Session::createInMemory(idleLifetime: 1800, absoluteLifetime: 3600);
        $session->set('auth_user_id', 99);

        // Regenerate keeps data intact and does not throw in memory driver.
        $session->regenerate();
        self::assertSame(99, $session->get('auth_user_id'));

        // Idle expiry clears data cleanly when next request arrives beyond idleLifetime.
        $session->start($now + 1801);
        self::assertNull($session->get('auth_user_id'));

        // Absolute expiry clears active keys even when touched within idleLifetime.
        $freshSession = Session::createInMemory(idleLifetime: 1800, absoluteLifetime: 3600);
        $freshSession->set('auth_user_id', 100);
        $freshSession->start($now + 1200);
        self::assertSame(100, $freshSession->get('auth_user_id'));
        $freshSession->start($now + 2400);
        self::assertSame(100, $freshSession->get('auth_user_id'));
        $freshSession->start($now + 3605);
        self::assertNull($freshSession->get('auth_user_id'));

        // Explicit logout destroy wipes everything.
        $freshSession->set('auth_user_id', 101);
        $freshSession->destroy();
        $freshSession->start($now + 4000);
        self::assertNull($freshSession->get('auth_user_id'));
    }

    private function buildAuthService(Session $session): AuthService
    {
        $logger = new Logger('test', [new NullHandler()]);
        $auditLogger = new AuditLogger($this->connection, $logger);
        $attempts = new LoginAttemptRepository($this->connection);
        $rateLimiter = new RateLimiterService(
            $attempts,
            $this->config,
            $logger,
            $auditLogger,
            static function (int $seconds): void {
                // Zero-delay sleeper in automated test execution.
            }
        );

        return new AuthService(
            new UserRepository($this->connection),
            $rateLimiter,
            $session,
            new Csrf($session),
            $this->config,
            $auditLogger
        );
    }

    private function makePostRequest(string $path, string $ip): Request
    {
        return new Request(
            'POST',
            $path,
            $path,
            [],
            [],
            [],
            [],
            [],
            ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'PHPUnit'],
            'req-auth-test'
        );
    }
}
