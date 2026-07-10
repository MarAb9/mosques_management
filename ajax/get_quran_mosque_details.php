<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\QuranAjaxController@details. */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->handle('ajax/get_quran_mosque_details.php');
