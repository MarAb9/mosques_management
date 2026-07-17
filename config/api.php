<?php

declare(strict_types=1);

use App\Core\Config;

$mode = strtolower(Config::env('API_ACCESS_MODE', 'key'));
$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', Config::env('API_ALLOWED_ORIGINS'))
), static function (string $origin): bool {
    $parts = parse_url($origin);

    return is_array($parts)
        && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        && isset($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !isset($parts['query'])
        && !isset($parts['fragment'])
        && !isset($parts['path']);
}));

return [
    'access_mode' => in_array($mode, ['public', 'key'], true) ? $mode : 'key',
    'allowed_origins' => $origins,
    'rate_limit' => min(100000, max(1, (int) Config::env('API_RATE_LIMIT', '120'))),
    'rate_window' => min(86400, max(1, (int) Config::env('API_RATE_WINDOW', '60'))),
    'key_hash' => Config::env('API_KEY_HASH'),
    'rate_cache_path' => dirname(__DIR__) . '/storage/cache/api-rate-limits',
    'default_image' => 'assets/images/institutional/mosque-building-3d.svg',
];
