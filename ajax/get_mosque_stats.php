<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\MosqueAjaxController@stats. */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->handle('ajax/get_mosque_stats.php');
