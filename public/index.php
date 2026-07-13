<?php

declare(strict_types=1);

/** Public front controller; application code lives above the web root. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->handle();
