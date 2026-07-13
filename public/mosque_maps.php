<?php

/** Legacy URL shim — dispatches to App\Controllers\MapController@index. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('mosque_maps.php');
