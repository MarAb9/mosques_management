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

    // ── Mosque list + AJAX ───────────────────────────────────────────────
    $router->get('mosques.php', [\App\Controllers\MosqueController::class, 'index'], [
        \App\Middleware\Authenticate::class,
    ]);
    $router->get('ajax/search_mosques.php', [\App\Controllers\Ajax\MosqueAjaxController::class, 'search'], [
        \App\Middleware\Authenticate::class,
    ]);
    $router->get('ajax/get_mosque_details.php', [\App\Controllers\Ajax\MosqueAjaxController::class, 'details'], [
        \App\Middleware\Authenticate::class,
    ]);
    $router->get('ajax/get_mosque_stats.php', [\App\Controllers\Ajax\MosqueAjaxController::class, 'stats'], [
        \App\Middleware\Authenticate::class,
    ]);
};
