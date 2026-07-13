<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\QuranAjaxController@details. */
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->handle('ajax/get_quran_mosque_details.php');
