<?php

declare(strict_types=1);

use App\Core\Config;

/*
 * Same environment variables and local defaults as the legacy
 * includes/db.php, so Docker and shared-hosting setups keep working
 * without configuration changes.
 */
return [
    'host' => Config::env('DB_HOST', '127.0.0.1'),
    'port' => Config::env('DB_PORT', '3306'),
    'user' => Config::env('DB_USER', 'mosques'),
    'pass' => Config::env('DB_PASS', 'mosques_password'),
    'name' => Config::env('DB_NAME', 'mosques_management'),
];
