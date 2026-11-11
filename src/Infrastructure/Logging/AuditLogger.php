<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Core\Request;
use App\Infrastructure\Database\Connection;
use Monolog\Logger;
use Throwable;

/**
 * Records security-sensitive and business-critical events in MariaDB `audit_log`.
 */
final class AuditLogger
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Logger $logger
    ) {
    }

    /**
     * Persist an audit event to `audit_log` with request IP and metadata context.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $action,
        ?int $userId,
        ?string $entityType,
        string|int|null $entityId,
        Request $request,
        array $metadata = []
    ): void {
        try {
            $encodedMeta = $metadata === []
                ? null
                : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $this->connection->execute(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata_json)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata_json)',
                [
                    'user_id' => $userId,
                    'action' => mb_substr($action, 0, 100),
                    'entity_type' => $entityType !== null ? mb_substr($entityType, 0, 60) : null,
                    'entity_id' => $entityId !== null ? mb_substr((string) $entityId, 0, 64) : null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata_json' => $encodedMeta,
                ]
            );
        } catch (Throwable $exception) {
            // Audit persistence failure must be logged loudly rather than silently swallowed.
            $this->logger->error('Failed to persist audit_log record.', [
                'action' => $action,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'request_id' => $request->requestId(),
            ]);
        }
    }
}
