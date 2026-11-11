<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Immutable 12-factor configuration repository loaded from `.env` and `config/*.php`.
 *
 * Guarantees a single source of truth for all runtime parameters across
 * database, security, session, BladeOne, upload, and domain modules.
 */
final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly string $rootPath,
        private readonly array $items
    ) {
    }

    /**
     * Load `.env` via vlucas/phpdotenv and compile every PHP file under `config/`.
     */
    public static function loadFromRoot(string $rootPath): self
    {
        $normalizedRoot = rtrim($rootPath, '/\\');
        $envFile = $normalizedRoot . DIRECTORY_SEPARATOR . '.env';
        if (class_exists(Dotenv::class) && is_file($envFile)) {
            Dotenv::createImmutable($normalizedRoot)->safeLoad();
        }

        $configDir = $normalizedRoot . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configDir)) {
            throw new RuntimeException('Configuration directory not found: ' . $configDir);
        }

        $items = [];
        $files = glob($configDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $namespace = basename($file, '.php');
            /** @var mixed $loaded */
            $loaded = require $file;
            if (is_array($loaded)) {
                $items[$namespace] = $loaded;
            }
        }

        return new self($normalizedRoot, $items);
    }

    /**
     * Return a cloned Config with runtime HTTP mount defaults applied when not explicitly pinned.
     */
    public function withWebRuntimeDefaults(string $detectedBasePath, ?string $detectedAppUrl): self
    {
        $items = $this->items;
        $app = isset($items['app']) && is_array($items['app']) ? $items['app'] : [];

        $explicitBase = env_value('APP_BASE_PATH');
        $normalizedBase = rtrim(str_replace('\\', '/', $detectedBasePath), '/');
        if (($explicitBase === null || trim((string) $explicitBase) === '') && ($app['base_path'] ?? '') === '') {
            $app['base_path'] = $normalizedBase;
        }

        $configuredUrl = trim((string) ($app['url'] ?? ''));
        $isDefaultUrl = $configuredUrl === '' || $configuredUrl === 'http://127.0.0.1:8000';
        $explicitUrl = env_value('APP_URL');
        $effectiveBase = (string) ($app['base_path'] ?? '');
        $shouldOverrideUrl = $isDefaultUrl
            && ($explicitUrl === null
                || trim((string) $explicitUrl) === ''
                || ($effectiveBase !== '' && $configuredUrl === 'http://127.0.0.1:8000'));

        if ($shouldOverrideUrl && $detectedAppUrl !== null) {
            $app['url'] = rtrim($detectedAppUrl, '/');
        }

        $items['app'] = $app;
        return new self($this->rootPath, $items);
    }

    /**
     * Project root directory path (parent of `public/`, `src/`, `config/`).
     */
    public function rootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * Resolve a relative path against the project root unless already absolute.
     */
    public function resolvePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return $this->rootPath;
        }

        if (
            str_starts_with($trimmed, '/')
            || str_starts_with($trimmed, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $trimmed) === 1
        ) {
            return $trimmed;
        }

        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed);
    }

    /**
     * Retrieve a dot-delimited config key (e.g. `database.host`, `security.csrf.token_name`).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Retrieve a string configuration value.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Retrieve an integer configuration value.
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Retrieve a boolean configuration value.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $default;
    }

    /**
     * Retrieve an array configuration value.
     *
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }
}
