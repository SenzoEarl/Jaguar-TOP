<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Core\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Configures Monolog file logging driven exclusively by `.env` configuration.
 *
 * // [SECURITY:ERRORS] Captures detailed internal diagnostics in non-web logs
 * // while keeping client-facing HTTP error responses generic.
 */
final class LoggerFactory
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function create(): Logger
    {
        $channel = $this->config->string('security.logging.channel', 'jaguar_safari');
        $rawPath = $this->config->string('security.logging.path', 'storage/logs/app.log');
        $logPath = $this->config->resolvePath($rawPath);
        $maxFiles = max(1, $this->config->int('security.logging.max_files', 14));
        $levelName = strtolower($this->config->string('security.logging.level', 'warning'));

        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $level = match ($levelName) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Warning,
        };

        $handler = new RotatingFileHandler($logPath, $maxFiles, $level);
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );
        $handler->setFormatter($formatter);

        $logger = new Logger($channel);
        $logger->pushHandler($handler);

        return $logger;
    }
}
