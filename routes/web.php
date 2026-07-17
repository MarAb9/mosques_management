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
        \App\Middleware\VerifyCsrf::class,
    ]);
    $router->post('logout.php', [\App\Controllers\Auth\LoginController::class, 'logout'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\VerifyCsrf::class,
    ]);

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
    $router->get('check_national_code.php', [\App\Controllers\MosqueController::class, 'checkNationalCode'], [
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

    // ── Import / Export ──────────────────────────────────────────────────
    // GET serves both the page and ?export=1 downloads; POST is the Excel
    // import (permission + CSRF checked inside with legacy ordering).
    $router->get('import_export.php', [\App\Controllers\ImportExportController::class, 'handle'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanUseImportExport::class,
    ]);
    $router->post('import_export.php', [\App\Controllers\ImportExportController::class, 'import'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanUseImportExport::class,
    ]);


    // ── Administration / accountability ─────────────────────────────────
    $router->get('data_quality.php', [\App\Controllers\AdministrationController::class, 'dataQuality'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\RequireAdmin::class,
    ]);
    $router->get('backup.php', [\App\Controllers\AdministrationController::class, 'backup'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\RequireAdmin::class,
    ]);
    // ── Quran programs ───────────────────────────────────────────────────
    $router->get('quran_mosques.php', [\App\Controllers\QuranProgramController::class, 'index'], [
        \App\Middleware\Authenticate::class,
    ]);
    $router->get('add_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'create'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanCreateQuranProgram::class,
    ]);
    $router->post('add_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'store'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanCreateQuranProgram::class,
        \App\Middleware\VerifyCsrfQuranAdd::class,
    ]);
    $router->get('edit_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'edit'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanEditQuranProgram::class,
    ]);
    // CSRF for the edit POST is checked in the controller: the legacy
    // failure redirect includes the dynamic ?id= of the edited program.
    $router->post('edit_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'update'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanEditQuranProgram::class,
    ]);
    $router->post('delete_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'destroy'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanDeleteQuranProgram::class,
        \App\Middleware\VerifyCsrfQuranList::class,
    ]);
    $router->get('delete_quran_mosque.php', [\App\Controllers\QuranProgramController::class, 'destroyMethodNotAllowed'], [
        \App\Middleware\Authenticate::class,
        \App\Middleware\CanDeleteQuranProgram::class,
    ]);
    $router->get('ajax/get_quran_mosque_details.php', [\App\Controllers\Ajax\QuranAjaxController::class, 'details'], [
        \App\Middleware\Authenticate::class,
    ]);

    // ── Map ──────────────────────────────────────────────────────────────
    $router->get('mosque_maps.php', [\App\Controllers\MapController::class, 'index'], [
        \App\Middleware\Authenticate::class,
    ]);
    $router->get('ajax/get_mosques_for_map.php', [\App\Controllers\Ajax\MapAjaxController::class, 'mosques'], [
        \App\Middleware\Authenticate::class,
    ]);

    // Optional extensionless routes through public/index.php. Physical .php
    // shims remain the compatibility contract for hosts without mod_rewrite.
    foreach ([
        'index.php',
        'login.php',
        'logout.php',
        'mosques.php',
        'add_mosque.php',
        'edit_mosque.php',
        'delete_mosque.php',
        'bulk_delete_mosques.php',
        'import_export.php',
        'data_quality.php',
        'backup.php',
        'quran_mosques.php',
        'add_quran_mosque.php',
        'edit_quran_mosque.php',
        'delete_quran_mosque.php',
        'mosque_maps.php',
        'ajax/search_mosques.php',
        'ajax/get_mosque_details.php',
        'ajax/get_mosque_stats.php',
        'ajax/get_quran_mosque_details.php',
        'ajax/get_mosques_for_map.php',
    ] as $legacyPath) {
        $router->alias(substr($legacyPath, 0, -4), $legacyPath);
    }
};
