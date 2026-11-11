<?php

declare(strict_types=1);

return [
    'storage_path' => (string) env_value('UPLOAD_STORAGE_PATH', 'storage/departments'),
    'max_bytes' => env_int('UPLOAD_MAX_BYTES', 5242880),
    'allowed_extensions' => env_csv(
        'UPLOAD_ALLOWED_EXTENSIONS',
        ['pdf', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'docx', 'xlsx']
    ),
    'allowed_mimes' => env_csv(
        'UPLOAD_ALLOWED_MIMES',
        [
            'application/pdf',
            'text/plain',
            'text/csv',
            'image/png',
            'image/jpeg',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]
    ),
];
