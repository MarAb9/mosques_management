<?php

declare(strict_types=1);

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->handle('ajax/map_tile.php');
