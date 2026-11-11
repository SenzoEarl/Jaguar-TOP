<?php

declare(strict_types=1);

return [
    // [SECURITY:PASSWORD] Password hashing tuning read from .env
    'password' => [
        'algo' => strtolower((string) env_value('PASSWORD_HASH_ALGO', 'argon2id')),
        'argon2_memory_cost' => env_int('PASSWORD_ARGON2_MEMORY_COST', 65536),
        'argon2_time_cost' => env_int('PASSWORD_ARGON2_TIME_COST', 4),
        'argon2_threads' => env_int('PASSWORD_ARGON2_THREADS', 1),
        'bcrypt_cost' => env_int('PASSWORD_BCRYPT_COST', 12),
    ],

    // [SECURITY:BRUTE-FORCE] Login throttling thresholds read from .env
    'brute_force' => [
        'max_attempts_per_user' => env_int('AUTH_MAX_ATTEMPTS_PER_USER', 5),
        'max_attempts_per_ip' => env_int('AUTH_MAX_ATTEMPTS_PER_IP', 15),
        'lockout_seconds' => env_int('AUTH_LOCKOUT_SECONDS', 900),
        'backoff_base_seconds' => env_int('AUTH_BACKOFF_BASE_SECONDS', 2),
        'backoff_max_seconds' => env_int('AUTH_BACKOFF_MAX_SECONDS', 10),
    ],

    // [SECURITY:CSRF] Token field and header keys
    'csrf' => [
        'token_name' => (string) env_value('CSRF_TOKEN_NAME', '_csrf_token'),
        'header_name' => (string) env_value('CSRF_HEADER_NAME', 'X-CSRF-Token'),
    ],

    // [SECURITY:XSS] Central HTTP security headers
    'headers' => [
        'csp_policy' => (string) env_value(
            'CSP_POLICY',
            "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
            . "font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; "
            . "form-action 'self'; object-src 'none'"
        ),
        'referrer_policy' => (string) env_value('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => (string) env_value(
            'PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=()'
        ),
    ],

    // [SECURITY:ERRORS] Monolog file logging parameters
    'logging' => [
        'channel' => (string) env_value('LOG_CHANNEL', 'wildcrest'),
        'path' => (string) env_value('LOG_PATH', 'storage/logs/app.log'),
        'level' => strtolower((string) env_value('LOG_LEVEL', 'warning')),
        'max_files' => env_int('LOG_MAX_FILES', 14),
    ],
];
