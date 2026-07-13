<?php

/** Legacy URL shim — dispatches to App\Controllers\Auth\LoginController. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('login.php');
