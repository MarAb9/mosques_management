<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Services\ArcGisRouteService;

require dirname(__DIR__) . '/vendor/autoload.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
};
$throws = static function (callable $callback, string $expected) use ($check): void {
    try {
        $callback();
        $check(false, "Expected {$expected}");
    } catch (Throwable $error) {
        $check($error->getMessage() === $expected, "Expected {$expected}, got {$error->getMessage()}");
    }
};

putenv('ARCGIS_ROUTING_TOKEN');
$config = new Config(dirname(__DIR__) . '/config');
$service = new ArcGisRouteService($config, new Session($config));

$throws(fn () => $service->route(91, 0, 34.7, -1.9, 'driving'), 'route_invalid_coordinates');
$throws(fn () => $service->route(34.6, -1.8, 34.7, -1.9, 'flying'), 'route_invalid_mode');
$throws(fn () => $service->route(34.6, -1.8, 34.7, -1.9, 'driving'), 'route_unavailable');

$normalize = new ReflectionMethod($service, 'normalize');
$route = $normalize->invoke($service, [
    'routes' => ['features' => [[
        'attributes' => ['Total_Kilometers' => 3.25, 'Total_TravelTime' => 8.4],
        'geometry' => ['paths' => [[[-1.8, 34.6], [-1.9, 34.7]]]],
    ]]],
    'directions' => [[
        'features' => [[
            'attributes' => ['text' => 'اتجه شمالاً', 'length' => 1.2, 'time' => 3.1],
        ]],
    ]],
]);
$check($route['geometry'] === [[34.6, -1.8], [34.7, -1.9]], 'ArcGIS coordinates are normalized for Leaflet');
$check($route['distance_km'] === 3.25 && $route['duration_minutes'] === 8, 'Summary is normalized');
$check($route['steps'][0]['text'] === 'اتجه شمالاً', 'Arabic direction text is preserved');
$throws(fn () => $normalize->invoke($service, []), 'route_not_found');

printf("Map route service: %d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
