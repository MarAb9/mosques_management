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

putenv('MAPTILER_API_KEY=maptiler-test-key');
$config = new Config(dirname(__DIR__) . '/config');
$maps = $config->get('maps');

$check('Leaflet remains the map engine', $maps['engine'] === 'leaflet');
$check('Legacy OpenStreetMap raster is configured', $maps['street_tile_url'] === 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
$check('Satellite uses the simple MapTiler raster endpoint', $maps['satellite_tile_url'] === 'https://api.maptiler.com/tiles/satellite-v2/{z}/{x}/{y}.jpg?key={key}');
$check('Satellite key resolves from the environment', $maps['api_key'] === 'maptiler-test-key');

$package = json_decode((string) file_get_contents(dirname(__DIR__) . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
$dependencies = $package['dependencies'] ?? [];
$check('Only local Leaflet map dependencies remain', array_keys($dependencies) === ['leaflet', 'leaflet.markercluster']);

$runtimeFiles = ['app', 'config', 'resources', 'routes', 'public/ajax'];
$forbidden = '/arcgis|esri|maplibre|openfreemap|eox|google\.com\/maps/i';
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
$check('Tile errors cannot switch back to street mode', !preg_match('/recordLayerFailure[\s\S]*switchBasemap\([\'"]street[\'"]\)/', $mapScript));

$view = (string) file_get_contents(dirname(__DIR__) . '/resources/views/maps/index.php');
$check('Routing and dead hybrid controls are absent', !str_contains($view, 'routeToMosque')
    && !str_contains($view, 'routePanel')
    && !str_contains($view, 'data-basemap-mode="hybrid"'));

echo "Leaflet raster configuration: {$pass} passed, {$fail} failed" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
