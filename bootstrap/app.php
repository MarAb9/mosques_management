<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Usage from a legacy-URL shim:
 *     $app = require __DIR__ . '/bootstrap/app.php';
 *     $app->handle('mosques.php');
 *
 * Usage from public/index.php (front controller):
 *     $app = require dirname(__DIR__) . '/bootstrap/app.php';
 *     $app->handle();
 */

$basePath = dirname(__DIR__);

require_once $basePath . '/vendor/autoload.php';

return App\Core\App::boot($basePath);
