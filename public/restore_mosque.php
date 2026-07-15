<?php

/** Legacy URL shim. */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle('restore_mosque.php');
