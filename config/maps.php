<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'provider' => Config::env('MAP_PROVIDER', 'maplibre'),
    'style_url' => Config::env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),
    'satellite' => [
        'enabled' => true,
        'tileUrl' => 'https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless-2025_3857/default/g/{z}/{y}/{x}.jpg',
        'minZoom' => 0,
        'maxZoom' => 14,
        'attribution' => 'EOxCloudless https://cloudless.eox.at by EOX IT Services GmbH (Contains modified Copernicus Sentinel data 2025)',
    ],
    'default_latitude' => Config::env('MAP_DEFAULT_LATITUDE', '34.6814'),
    'default_longitude' => Config::env('MAP_DEFAULT_LONGITUDE', '-1.9086'),
    'default_zoom' => Config::env('MAP_DEFAULT_ZOOM', '9'),
];
