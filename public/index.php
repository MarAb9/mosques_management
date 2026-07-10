<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Becomes the web entry point when the document root is flipped to
 * public/. Legacy URLs keep working either through the physical shim
 * files next to this one or through the .htaccess rewrite to this file.
 */

$bootstrap = is_file(__DIR__ . '/../bootstrap/app.php')
    ? __DIR__ . '/../bootstrap/app.php'   // repo layout: public/ + siblings
    : __DIR__ . '/bootstrap/app.php';     // flat shared-hosting layout

$app = require $bootstrap;

$app->handle();
