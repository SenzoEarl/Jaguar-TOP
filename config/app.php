<?php

declare(strict_types=1);

return [
    'name' => (string) env_value('APP_NAME', 'Wildcrest Lodges & Camps'),
    'env' => (string) env_value('APP_ENV', 'production'),
    'debug' => env_bool('APP_DEBUG', false),
    'url' => rtrim((string) env_value('APP_URL', 'http://127.0.0.1:8000'), '/'),
    'base_path' => rtrim((string) env_value('APP_BASE_PATH', ''), '/'),
    'timezone' => (string) env_value('APP_TIMEZONE', 'UTC'),
    'locale' => (string) env_value('APP_LOCALE', 'en'),
    'contact_email' => (string) env_value('CONTACT_EMAIL', 'concierge@wildcrestlodges.example'),
    'contact_phone' => (string) env_value('CONTACT_PHONE', '+27 11 555 0194'),
    'business_location' => (string) env_value('BUSINESS_LOCATION', 'Limpopo & Okavango Conservation Circuit'),
    'booking_currency' => (string) env_value('BOOKING_CURRENCY', 'USD'),
    'booking_max_guests_default' => env_int('BOOKING_MAX_GUESTS_DEFAULT', 12),
    'catalog_per_page' => env_int('CATALOG_PER_PAGE', 9),
    'staff_tasks_per_page' => env_int('STAFF_TASKS_PER_PAGE', 15),
];
