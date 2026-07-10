<?php

/** Legacy URL shim — dispatches to App\Controllers\MosqueController@create/store. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('add_mosque.php');
