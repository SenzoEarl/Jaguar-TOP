<?php

declare(strict_types=1);

return [
    // [CONFIG:BLADEONE] BladeOne path and compilation mode read from .env
    'path' => (string) env_value('VIEW_PATH', 'resources/views'),
    'cache_path' => (string) env_value('VIEW_CACHE_PATH', 'storage/cache/blade'),
    'mode' => strtolower((string) env_value('VIEW_MODE', 'auto')),
];
