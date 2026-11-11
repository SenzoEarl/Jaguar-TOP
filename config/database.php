<?php

declare(strict_types=1);

return [
    'host' => (string) env_value('DB_HOST', '127.0.0.1'),
    'port' => env_int('DB_PORT', 3306),
    'database' => (string) env_value('DB_DATABASE', 'jaguar_safari'),
    'username' => (string) env_value('DB_USERNAME', 'root'),
    'password' => (string) env_value('DB_PASSWORD', ''),
    'charset' => (string) env_value('DB_CHARSET', 'utf8mb4'),
    'collation' => (string) env_value('DB_COLLATION', 'utf8mb4_unicode_ci'),
];
