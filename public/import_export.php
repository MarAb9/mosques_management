<?php

/** Legacy URL shim — dispatches to App\Controllers\ImportExportController. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('import_export.php');
