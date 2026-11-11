<?php

declare(strict_types=1);

return [
    // [SECURITY:SESSION] All session cookie and lifetime parameters driven by .env
    'name' => (string) env_value('SESSION_NAME', 'wildcrest_sid'),
    'save_path' => (string) env_value('SESSION_SAVE_PATH', 'storage/sessions'),
    'idle_lifetime' => env_int('SESSION_LIFETIME_IDLE', 1800),
    'absolute_lifetime' => env_int('SESSION_LIFETIME_ABSOLUTE', 28800),
    'cookie_secure' => env_bool('SESSION_COOKIE_SECURE', false),
    'cookie_httponly' => env_bool('SESSION_COOKIE_HTTPONLY', true),
    'cookie_samesite' => (string) env_value('SESSION_COOKIE_SAMESITE', 'Lax'),
    'cookie_path' => (string) env_value('SESSION_COOKIE_PATH', '/'),
    'cookie_domain' => (string) env_value('SESSION_COOKIE_DOMAIN', ''),
];
