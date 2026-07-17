<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'trust_proxy_headers' => in_array(
        strtolower(Config::env('TRUST_PROXY_HEADERS', 'false')),
        ['1', 'true', 'on'],
        true
    ),
    'session' => [
        'name' => Config::env('SESSION_NAME', 'mosques_session'),
        'idle_timeout' => (int) Config::env('SESSION_IDLE_TIMEOUT', '1800'),
        'absolute_timeout' => (int) Config::env('SESSION_ABSOLUTE_TIMEOUT', '28800'),
        'regenerate_interval' => (int) Config::env('SESSION_REGENERATE_INTERVAL', '900'),
    ],
    'login' => [
        'max_attempts' => (int) Config::env('LOGIN_MAX_ATTEMPTS', '5'),
        'decay_seconds' => (int) Config::env('LOGIN_DECAY_SECONDS', '900'),
        'cache_path' => dirname(__DIR__) . '/storage/cache/login-throttle',
        'blocked_production_passwords' => ['admin123', 'password', '12345678'],
    ],
    'audit_log' => dirname(__DIR__) . '/storage/logs/audit.log',
];
