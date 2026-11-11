<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Config;
use App\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * MariaDB PDO connection wrapper enforcing prepared statements, ERRMODE_EXCEPTION,
 * utf8mb4 encoding, and atomic transaction rollback semantics.
 */
final class Connection
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4',
        private readonly string $collation = 'utf8mb4_unicode_ci'
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->string('database.host', '127.0.0.1'),
            $config->int('database.port', 3306),
            $config->string('database.database', 'wildcrest_lodges'),
            $config->string('database.username', 'root'),
            $config->string('database.password', ''),
            $config->string('database.charset', 'utf8mb4'),
            $config->string('database.collation', 'utf8mb4_unicode_ci')
        );
    }

    /**
     * Wrap a pre-configured PDO instance (for deterministic PDO integration tests).
     */
    public static function fromPdo(PDO $pdo): self
    {
        $instance = new self('localhost', 3306, '', '', '');
        $instance->pdo = $pdo;
        return $instance;
    }

    /**
     * Report whether the underlying PDO driver supports row-level `FOR UPDATE` locks.
     */
    public function supportsRowLocking(): bool
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    /**
     * Lazily establish and return the configured PDO handle.
     */
    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );

        try {
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec(sprintf('SET NAMES %s COLLATE %s', $this->charset, $this->collation));
            $this->pdo = $pdo;
            return $this->pdo;
        } catch (PDOException $exception) {
            throw new DatabaseException(
                'Database connection error: ' . $exception->getMessage(),
                'Unable to connect to the database service. Please try again later.',
                ['host' => $this->host, 'database' => $this->database],
                $exception
            );
        }
    }

    /**
     * Execute a prepared SQL query and return the executed statement.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $paramKey = is_int($key)
                    ? $key + 1
                    : (str_starts_with((string) $key, ':') ? (string) $key : ':' . $key);
                $paramType = match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_INT,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                $boundValue = is_bool($value) ? ($value ? 1 : 0) : $value;
                $statement->bindValue($paramKey, $boundValue, $paramType);
            }
            $statement->execute();
            return $statement;
        } catch (PDOException $exception) {
            throw new DatabaseException(
                'SQL execution error: ' . $exception->getMessage(),
                'A database operation failed. Please try again later.',
                ['sql_state' => $exception->getCode()],
                $exception
            );
        }
    }

    /**
     * Fetch a single row or null.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Fetch all matching rows.
     *
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->query($sql, $params)->fetchAll();
        return $rows;
    }

    /**
     * Execute INSERT/UPDATE/DELETE and return affected row count.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Execute `$callback` inside a MariaDB transaction with guaranteed rollback on error.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $startedRootTransaction = !$pdo->inTransaction();

        if ($startedRootTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($this);
            if ($startedRootTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($startedRootTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException) {
                throw new DatabaseException(
                    'Transaction failed: ' . $exception->getMessage(),
                    'A database transaction could not be completed.',
                    [],
                    $exception
                );
            }
            throw $exception;
        }
    }
}
