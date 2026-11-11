<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Request;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BookingConflictException;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Catalog\Repositories\ProductRepository;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * End-to-end PDO transaction integration suite verifying atomic reservation creation,
 * server-authoritative pricing, idempotency replay guarantees, overlap rejection,
 * back-to-back check-in/check-out allowances, and rollback atomicity.
 */
final class BookingTransactionTest extends TestCase
{
    private Connection $connection;
    private BookingService $bookingService;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $config = Config::loadFromRoot($root);

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE
            );
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                location TEXT NOT NULL,
                short_summary TEXT NOT NULL,
                description TEXT NOT NULL,
                price_per_night REAL NOT NULL,
                max_guests INTEGER NOT NULL,
                bedrooms INTEGER NOT NULL,
                amenities_json TEXT NULL,
                image_path TEXT NOT NULL,
                image_alt TEXT NOT NULL,
                is_featured INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_reference TEXT NOT NULL UNIQUE,
                idempotency_key TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                check_in_date TEXT NOT NULL,
                check_out_date TEXT NOT NULL,
                guests_count INTEGER NOT NULL,
                nights_count INTEGER NOT NULL,
                total_price REAL NOT NULL,
                status TEXT NOT NULL,
                special_requests TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
            INSERT INTO users (id, username) VALUES (1, 'user0'), (2, 'otherguest');
            INSERT INTO products (
                id, slug, name, type, location, short_summary, description,
                price_per_night, max_guests, bedrooms, amenities_json, image_path, image_alt, is_featured, is_active
            ) VALUES (
                1, 'eagle-ridge-lodge', 'Eagle Ridge Sanctuary Lodge', 'lodge', 'Drakensberg',
                'Highland sanctuary.', 'Full details.', 350.00, 4, 2, '["Solar"]',
                '/assets/img/products/lodge-eagle-ridge.svg', 'Eagle Ridge', 1, 1
            );
        SQL);

        $this->connection = Connection::fromPdo($pdo);
        $logger = new Logger('test', [new NullHandler()]);

        $this->bookingService = new BookingService(
            $this->connection,
            new BookingRepository($this->connection),
            new ProductRepository($this->connection),
            new Validator(),
            new AuditLogger($this->connection, $logger),
            $config
        );
    }

    public function testAtomicCreateIdempotencyReplayAndOverlapRejection(): void
    {
        $request = $this->makeBookingRequest();
        $inDate = date('Y-m-d', strtotime('+10 days'));
        $outDate = date('Y-m-d', strtotime('+13 days'));
        $idempotencyKey = str_repeat('a1b2', 16);

        $first = $this->bookingService->createBooking(1, [
            'product_id' => 1,
            'check_in_date' => $inDate,
            'check_out_date' => $outDate,
            'guests_count' => 2,
            'idempotency_key' => $idempotencyKey,
            'special_requests' => 'Early check-in',
        ], $request);

        self::assertSame(3, $first->getNightsCount());
        self::assertEqualsWithDelta(1050.00, $first->getTotalPrice(), 0.01);

        // Exact retry with the same idempotency key returns the identical reservation without duplicating rows.
        $replay = $this->bookingService->createBooking(1, [
            'product_id' => 1,
            'check_in_date' => $inDate,
            'check_out_date' => $outDate,
            'guests_count' => 2,
            'idempotency_key' => $idempotencyKey,
        ], $request);

        self::assertSame($first->getId(), $replay->getId());
        self::assertSame($first->getBookingReference(), $replay->getBookingReference());

        $countRow = $this->connection->fetchOne('SELECT COUNT(*) AS cnt FROM bookings');
        self::assertSame(1, (int) ($countRow['cnt'] ?? 0));

        // Reusing another customer's idempotency key is rejected.
        try {
            $this->bookingService->createBooking(2, [
                'product_id' => 1,
                'check_in_date' => $inDate,
                'check_out_date' => $outDate,
                'guests_count' => 2,
                'idempotency_key' => $idempotencyKey,
            ], $request);
            self::fail('Expected AuthorizationException on foreign idempotency key.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        // Overlapping date range fails with BookingConflictException.
        try {
            $this->bookingService->createBooking(2, [
                'product_id' => 1,
                'check_in_date' => date('Y-m-d', strtotime('+11 days')),
                'check_out_date' => date('Y-m-d', strtotime('+15 days')),
                'guests_count' => 2,
                'idempotency_key' => str_repeat('c3d4', 16),
            ], $request);
            self::fail('Expected BookingConflictException on overlapping booking dates.');
        } catch (BookingConflictException) {
            self::assertTrue(true);
        }

        // Same-day turnaround starting on previous check-out succeeds cleanly.
        $turnaround = $this->bookingService->createBooking(2, [
            'product_id' => 1,
            'check_in_date' => $outDate,
            'check_out_date' => date('Y-m-d', strtotime('+16 days')),
            'guests_count' => 3,
            'idempotency_key' => str_repeat('e5f6', 16),
        ], $request);

        self::assertSame(3, $turnaround->getNightsCount());
        $finalCount = $this->connection->fetchOne('SELECT COUNT(*) AS cnt FROM bookings');
        self::assertSame(2, (int) ($finalCount['cnt'] ?? 0));
    }

    public function testTransactionRollsBackCompletelyWhenExceptionOccurs(): void
    {
        try {
            $this->connection->transaction(static function (Connection $conn): void {
                $conn->execute(
                    'INSERT INTO bookings (
                        booking_reference, idempotency_key, user_id, product_id,
                        check_in_date, check_out_date, guests_count, nights_count, total_price, status
                    ) VALUES ("TMP-1", "key-rollback-0001", 1, 1, "2027-01-10", "2027-01-12", 2, 2, 700, "confirmed")'
                );
                throw new RuntimeException('Simulated post-insert failure');
            });
            self::fail('Expected RuntimeException was not rethrown.');
        } catch (RuntimeException) {
            // Expected rollback.
        }

        $row = $this->connection->fetchOne(
            'SELECT id FROM bookings WHERE idempotency_key = :k',
            ['k' => 'key-rollback-0001']
        );
        self::assertNull($row);
    }

    private function makeBookingRequest(): Request
    {
        return new Request(
            'POST',
            '/bookings',
            '/bookings',
            [],
            [],
            [],
            [],
            [],
            ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_USER_AGENT' => 'PHPUnit'],
            'req-booking-tx'
        );
    }
}
