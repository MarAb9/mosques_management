<?php

/** Legacy URL shim — dispatches to App\Controllers\Ajax\MapAjaxController@mosques. */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->handle('ajax/get_mosques_for_map.php');
