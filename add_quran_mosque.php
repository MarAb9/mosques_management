<?php

/** Legacy URL shim — dispatches to App\Controllers\QuranProgramController@create/store. */
$app = require __DIR__ . '/bootstrap/app.php';
$app->handle('add_quran_mosque.php');
