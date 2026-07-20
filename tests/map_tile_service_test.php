<?php

declare(strict_types=1);

use App\Core\Config;
use App\Services\ArcGisTileService;

require dirname(__DIR__) . '/vendor/autoload.php';

$passed = 0;
$failed = 0;
$throws = static function (callable $callback, string $expected) use (&$passed, &$failed): void {
    try {
        $callback();
        $failed++;
        fwrite(STDERR, 'FAIL: Expected ' . $expected . PHP_EOL);
    } catch (Throwable $error) {
        if ($error->getMessage() === $expected) {
            $passed++;
        } else {
            $failed++;
            fwrite(STDERR, 'FAIL: Expected ' . $expected . ', got ' . $error->getMessage() . PHP_EOL);
        }
    }
};

putenv('ARCGIS_ACCESS_TOKEN');
$service = new ArcGisTileService(new Config(dirname(__DIR__) . '/config'));

$throws(fn () => $service->tile('other', '10', '504', '405'), 'tile_invalid_request');
$throws(fn () => $service->tile('street', '-1', '0', '0'), 'tile_invalid_request');
$throws(fn () => $service->tile('street', '2', '4', '0'), 'tile_invalid_request');
$throws(fn () => $service->tile('street', '10', '504', '405'), 'tile_unavailable');

printf('Map tile service: %d passed, %d failed' . PHP_EOL, $passed, $failed);
exit($failed === 0 ? 0 : 1);
