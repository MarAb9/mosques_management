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

    // ── Dashboard ────────────────────────────────────────────────────────
    $router->get('index.php', [\App\Controllers\DashboardController::class, 'index'], [
        \App\Middleware\Authenticate::class,
    ]);

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

    // ── Mosque create/edit/delete ────────────────────────────────────────
    $router->get('add_mosque.php', [\App\Controllers\MosqueController::class, 'create'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanCreateMosque::class,
    ]);
    $router->post('add_mosque.php', [\App\Controllers\MosqueController::class, 'store'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanCreateMosque::class,
        \App\Middleware\VerifyCsrf::class,
    ]);
    $router->get('edit_mosque.php', [\App\Controllers\MosqueController::class, 'edit'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanEditMosque::class,
    ]);
    $router->post('edit_mosque.php', [\App\Controllers\MosqueController::class, 'update'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanEditMosque::class,
        \App\Middleware\VerifyCsrf::class,
    ]);
    $router->post('delete_mosque.php', [\App\Controllers\MosqueController::class, 'destroy'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanDeleteMosque::class,
        \App\Middleware\VerifyCsrfMosqueList::class,
    ]);
    // Legacy behavior: a GET to the delete endpoints bounces back with a flash error.
    $router->get('delete_mosque.php', [\App\Controllers\MosqueController::class, 'destroyMethodNotAllowed'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanDeleteMosque::class,
    ]);
    $router->post('bulk_delete_mosques.php', [\App\Controllers\MosqueController::class, 'bulkDestroy'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanBulkDeleteMosques::class,
        \App\Middleware\VerifyCsrfMosqueList::class,
    ]);
    $router->get('bulk_delete_mosques.php', [\App\Controllers\MosqueController::class, 'bulkDestroyMethodNotAllowed'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanBulkDeleteMosques::class,
    ]);
};
