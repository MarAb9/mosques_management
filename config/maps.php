<?php

declare(strict_types=1);

use App\Core\Config;

$staticTiles = 'https://static-map-tiles-api.arcgis.com/arcgis/rest/services/static-basemap-tiles-service/v1';

return [
    'engine' => 'leaflet',
    'access_token' => Config::env('ARCGIS_ACCESS_TOKEN'),
    'street' => [
        'enabled' => true,
        'url' => Config::env('ARCGIS_STREET_TILE_URL', $staticTiles . '/arcgis/navigation/static/tile/{z}/{y}/{x}'),
        'max_zoom' => 22,
        'max_native_zoom' => 22,
        'tile_size' => 512,
        'attribution' => '<a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a>, OpenStreetMap contributors, GIS User Community',
    ],
    'satellite' => [
        'enabled' => true,
        'url' => Config::env('ARCGIS_SATELLITE_TILE_URL', 'https://ibasemaps-api.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'),
        'labels_url' => Config::env('ARCGIS_SATELLITE_LABELS_URL', $staticTiles . '/arcgis/imagery/labels/static/tile/{z}/{y}/{x}'),
        'max_zoom' => 22,
        'max_native_zoom' => 22,
        'attribution' => 'Imagery &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> and its data providers',
    ],
    'routing' => [
        'provider' => Config::env('MAP_ROUTING_PROVIDER', 'arcgis'),
        'url' => Config::env('ARCGIS_ROUTING_URL', 'https://route-api.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve'),
        'token' => Config::env('ARCGIS_ROUTING_TOKEN'),
        'rate_limit' => 12,
        'rate_window' => 60,
    ],
    'default_latitude' => Config::env('MAP_DEFAULT_LATITUDE', '34.6814'),
    'default_longitude' => Config::env('MAP_DEFAULT_LONGITUDE', '-1.9086'),
    'default_zoom' => Config::env('MAP_DEFAULT_ZOOM', '9'),
];
