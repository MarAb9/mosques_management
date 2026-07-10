<?php

/** Legacy URL shim — dispatches to App\Controllers\QuranProgramController@destroy. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('delete_quran_mosque.php');
