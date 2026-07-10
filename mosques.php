<?php

/** Legacy URL shim — dispatches to App\Controllers\MosqueController@index. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('mosques.php');
