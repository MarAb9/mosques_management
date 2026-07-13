<?php

/** Legacy URL shim — dispatches to App\Controllers\MosqueController@bulkDestroy. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('bulk_delete_mosques.php');
