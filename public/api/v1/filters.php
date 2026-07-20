<?php

declare(strict_types=1);

$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->handle('api/v1/filters');
