<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'provider' => Config::env('MAP_PROVIDER', 'maplibre'),
    'style_url' => Config::env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),
    'default_latitude' => Config::env('MAP_DEFAULT_LATITUDE', '34.6814'),
    'default_longitude' => Config::env('MAP_DEFAULT_LONGITUDE', '-1.9086'),
    'default_zoom' => Config::env('MAP_DEFAULT_ZOOM', '9'),
];
