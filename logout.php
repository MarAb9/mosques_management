<?php

/** Legacy URL shim — dispatches to App\Controllers\Auth\LoginController. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('logout.php');
