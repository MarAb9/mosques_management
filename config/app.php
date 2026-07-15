<?php

declare(strict_types=1);

use App\Core\Config;

return [
    // Application environment: 'production' or 'development'.
    'env' => Config::env('APP_ENV', 'production'),

    // Debug mode shows full exception details. NEVER enable in production.
    'debug' => in_array(strtolower(Config::env('APP_DEBUG', '')), ['1', 'true', 'on'], true),

    // Application display name (user-facing, Arabic).
    'name' => 'نظام إدارة مساجد إقليم بركان',

    // All persisted timestamps should be UTC; presentation may localize them.
    'timezone' => Config::env('APP_TIMEZONE', 'Africa/Casablanca'),
];
