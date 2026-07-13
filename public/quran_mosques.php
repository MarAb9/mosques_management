<?php

/** Legacy URL shim — dispatches to App\Controllers\QuranProgramController@index. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('quran_mosques.php');
