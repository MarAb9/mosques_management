<?php

declare(strict_types=1);

use App\Core\Config;

/* Environment variables take priority; database.local.php can override them. */
$config = [
    'host' => Config::env('DB_HOST', '127.0.0.1'),
    'port' => Config::env('DB_PORT', '3306'),
    'user' => Config::env('DB_USER', 'mosques'),
    'pass' => Config::env('DB_PASS', 'mosques_password'),
    'name' => Config::env('DB_NAME', 'mosques_management'),
];

$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
