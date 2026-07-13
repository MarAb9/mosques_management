<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\MosqueAjaxController@stats. */
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->handle('ajax/get_mosque_stats.php');
