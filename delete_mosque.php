<?php

/** Legacy URL shim — dispatches to App\Controllers\MosqueController@destroy. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('delete_mosque.php');
