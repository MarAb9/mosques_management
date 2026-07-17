<?php

declare(strict_types=1);

use App\Controllers\Api\V1\ApiMetadataController;
use App\Controllers\Api\V1\MosqueApiController;
use App\Core\Router;
use App\Middleware\ApiCors;
use App\Middleware\ApiKeyAuth;
use App\Middleware\ApiRateLimit;

return function (Router $router): void {
    $middleware = [ApiCors::class, ApiRateLimit::class, ApiKeyAuth::class];
    $routes = [
        'api/v1/mosques' => [MosqueApiController::class, 'index'],
        'api/v1/mosques.geojson' => [MosqueApiController::class, 'geoJson'],
        'api/v1/filters' => [ApiMetadataController::class, 'filters'],
        'api/v1/health' => [ApiMetadataController::class, 'health'],
        'api/v1/mosques/{id}' => [MosqueApiController::class, 'show'],
    ];

    foreach ($routes as $path => $action) {
        $router->get($path, $action, $middleware);
        $router->add('OPTIONS', $path, $action, $middleware);
    }
};
