<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    $ok ? $pass++ : $fail++;
};

$config = new Config(dirname(__DIR__) . '/config');
$maps = $config->get('maps');

$check('Leaflet remains the map engine', $maps['engine'] === 'leaflet');
$check('OpenStreetMap raster is configured', $maps['street_tile_url'] === 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
$check('No tile-provider credential is configured', !isset($maps['api_key']) && !isset($maps['satellite_tile_url']));

$package = json_decode((string) file_get_contents(dirname(__DIR__) . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
$dependencies = $package['dependencies'] ?? [];
$check('Only local Leaflet map dependencies remain', array_keys($dependencies) === ['leaflet', 'leaflet.markercluster']);

$runtimeFiles = ['app', 'config', 'resources', 'routes', 'public/ajax'];
$forbidden = '/arcgis|esri|maplibre|maptiler|openfreemap|eox|google\.com\/maps/i';
$matches = [];
foreach ($runtimeFiles as $runtimePath) {
    $directory = new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $runtimePath, FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($directory) as $file) {
        if ($file->isFile() && preg_match('/\.(?:php|js|css|json|ya?ml)$/i', $file->getFilename()) === 1
            && preg_match($forbidden, (string) file_get_contents($file->getPathname())) === 1) {
            $matches[] = $file->getPathname();
        }
    }
}
$check('Rejected providers have no active runtime reference', $matches === []);

$mapScript = (string) file_get_contents(dirname(__DIR__) . '/resources/js/pages/maps.js');
$formScript = (string) file_get_contents(dirname(__DIR__) . '/resources/js/pages/mosque-form-map.js');
$check('Both maps use plain Leaflet raster layers', str_contains($mapScript, 'L.tileLayer(') && str_contains($formScript, 'L.tileLayer('));
$check('The workspace has one raster layer path', substr_count($mapScript, 'L.tileLayer(') === 1);

$view = (string) file_get_contents(dirname(__DIR__) . '/resources/views/maps/index.php');
$check('Routing and basemap controls are absent', !str_contains($view, 'routeToMosque')
    && !str_contains($view, 'routePanel')
    && !str_contains($view, 'data-basemap-mode'));

echo "Leaflet raster configuration: {$pass} passed, {$fail} failed" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
