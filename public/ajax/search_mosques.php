<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\MosqueAjaxController@search. */
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->handle('ajax/search_mosques.php');
