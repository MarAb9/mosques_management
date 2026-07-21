<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'engine' => 'leaflet',
    'street_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'default_latitude' => Config::env('MAP_DEFAULT_LATITUDE', '34.6814'),
    'default_longitude' => Config::env('MAP_DEFAULT_LONGITUDE', '-1.9086'),
    'default_zoom' => Config::env('MAP_DEFAULT_ZOOM', '9'),
];
