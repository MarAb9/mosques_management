<?php

/** Legacy URL shim — dispatches to App\Controllers\DashboardController@index. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('index.php');
