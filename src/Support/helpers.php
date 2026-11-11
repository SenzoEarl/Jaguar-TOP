<?php

/**
 * Pure utility helpers autoloaded via composer.json.
 *
 * Deliberately contains ZERO mutable global state or superglobal access so
 * functions remain deterministic and safe in both HTTP and PHPUnit contexts.
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape output safely for HTML text nodes and attributes.
     *
     * // [SECURITY:XSS] Canonical HTML escaping helper.
     */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('env_value')) {
    /**
     * Read an environment value populated by vlucas/phpdotenv without mutating state.
     */
    function env_value(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $val = getenv($key);
        return $val === false ? $default : $val;
    }
}

if (!function_exists('env_bool')) {
    /**
     * Parse a boolean configuration flag from environment variables.
     */
    function env_bool(string $key, bool $default = false): bool
    {
        $raw = env_value($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        return match (strtolower(trim((string) $raw))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}

if (!function_exists('env_int')) {
    /**
     * Parse an integer configuration value from environment variables.
     */
    function env_int(string $key, int $default = 0): int
    {
        $raw = env_value($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return is_numeric($raw) ? (int) $raw : $default;
    }
}

if (!function_exists('env_csv')) {
    /**
     * Parse a comma-separated configuration value into a trimmed list of strings.
     *
     * @param list<string> $default
     * @return list<string>
     */
    function env_csv(string $key, array $default = []): array
    {
        $raw = env_value($key);
        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }

        $parts = array_map(
            static fn (string $item): string => trim($item),
            explode(',', (string) $raw)
        );

        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }
}

if (!function_exists('app_url')) {
    /**
     * Build a path relative to an optional subdirectory base path (e.g. /TOP/public).
     */
    function app_url(string $path = '/', string $basePath = ''): string
    {
        $cleanBase = rtrim($basePath, '/');
        $cleanPath = '/' . ltrim($path, '/');

        if ($cleanPath === '/') {
            return $cleanBase !== '' ? $cleanBase . '/' : '/';
        }

        return $cleanBase . $cleanPath;
    }
}

if (!function_exists('format_money')) {
    /**
     * Format a monetary amount using explicit currency symbol/code.
     */
    function format_money(float|int|string $amount, string $currency = 'USD'): string
    {
        $numeric = (float) $amount;
        $prefix = match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R',
            default => strtoupper($currency) . ' ',
        };

        return $prefix . number_format($numeric, 2);
    }
}
