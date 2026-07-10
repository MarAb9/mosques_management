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
    // Routes are registered per migrated module.
};
