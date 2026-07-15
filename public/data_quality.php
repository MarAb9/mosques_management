<?php

/** Legacy URL shim. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('data_quality.php');
