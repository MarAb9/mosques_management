<?php

/** Legacy URL shim — dispatches to App\Controllers\QuranProgramController@edit/update. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('edit_quran_mosque.php');
