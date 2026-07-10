<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Central route table.
 *
 * Paths are the legacy public URLs relative to the web root. Routes are
 * added here module by module as controllers are migrated; a URL not in
 * this table is still served by its legacy PHP file.
 */
return function (Router $router): void {
    // ── Authentication ───────────────────────────────────────────────────
    $router->get('login.php', [\App\Controllers\Auth\LoginController::class, 'show'], [
        \App\Middleware\Guest::class,
    ]);
    $router->post('login.php', [\App\Controllers\Auth\LoginController::class, 'login'], [
        \App\Middleware\Guest::class,
    ]);
    $router->get('logout.php', [\App\Controllers\Auth\LoginController::class, 'logout']);
};
