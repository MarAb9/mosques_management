<?php

/** Legacy URL shim — dispatches to App\Controllers\MosqueController@edit/update. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('edit_mosque.php');
