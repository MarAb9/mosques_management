<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\MosqueAjaxController@search. */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->handle('ajax/search_mosques.php');
