<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;

final class ArcGisRouteService
{
    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
    ) {
    }

    /**
     * @return array{distance_km: float, duration_minutes: int, geometry: list<array{0: float, 1: float}>, steps: list<array{text: string, distance_km: float, duration_minutes: int}>}
     */
    public function route(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude, string $mode): array
    {
        foreach ([[$originLatitude, -90, 90], [$destinationLatitude, -90, 90], [$originLongitude, -180, 180], [$destinationLongitude, -180, 180]] as [$value, $minimum, $maximum]) {
            if (!is_finite($value) || $value < $minimum || $value > $maximum) {
                throw new \DomainException('route_invalid_coordinates');
            }
        }
        if (!in_array($mode, ['driving', 'walking'], true)) {
            throw new \DomainException('route_invalid_mode');
        }

        $token = (string) $this->config->get('maps.routing.token', '');
        $url = (string) $this->config->get('maps.routing.url', '');
        if ($token === '' || !$this->isAllowedUrl($url)) {
            throw new \RuntimeException('route_unavailable');
        }

        $this->throttle();
        $travelMode = $this->travelMode($url, $token, $mode);
        $payload = $this->request($url, $token, [
            'f' => 'json',
            'stops' => "{$originLongitude},{$originLatitude};{$destinationLongitude},{$destinationLatitude}",
            'travelMode' => json_encode($travelMode, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'returnDirections' => 'true',
            'directionsLanguage' => 'ar',
            'directionsLengthUnits' => 'esriNAUKilometers',
            'directionsOutputType' => 'esriDOTComplete',
            'returnRoutes' => 'true',
            'returnStops' => 'false',
            'outputLines' => 'esriNAOutputLineTrueShape',
            'outSR' => '4326',
        ]);

        return $this->normalize($payload);
    }

    private function throttle(): void
    {
        $limit = max(1, (int) $this->config->get('maps.routing.rate_limit', 12));
        $window = max(1, (int) $this->config->get('maps.routing.rate_window', 60));
        $now = time();
        $state = $this->session->get('_map_route_rate', []);
        if (!is_array($state) || $now >= (int) ($state['started_at'] ?? 0) + $window) {
            $state = ['started_at' => $now, 'count' => 0];
        }
        $state['count'] = (int) $state['count'] + 1;
        $this->session->set('_map_route_rate', $state);
        if ($state['count'] > $limit) {
            throw new \RuntimeException('route_rate_limit');
        }
    }

    /** @return array<string, mixed> */
    private function travelMode(string $solveUrl, string $token, string $mode): array
    {
        $cache = $this->session->get('_map_route_modes', []);
        if (!is_array($cache) || (int) ($cache['expires_at'] ?? 0) < time()) {
            $modesUrl = preg_replace('~/solve/?$~', '/retrieveTravelModes', $solveUrl);
            if (!is_string($modesUrl) || $modesUrl === $solveUrl) {
                throw new \RuntimeException('route_unavailable');
            }
            $response = $this->request($modesUrl, $token, ['f' => 'json']);
            $cache = ['expires_at' => time() + 86400, 'modes' => $response['supportedTravelModes'] ?? []];
            $this->session->set('_map_route_modes', $cache);
        }

        $wanted = $mode === 'walking' ? 'WALK' : 'AUTOMOBILE';
        foreach (($cache['modes'] ?? []) as $travelMode) {
            if (is_array($travelMode) && strtoupper((string) ($travelMode['type'] ?? '')) === $wanted) {
                return $travelMode;
            }
        }

        throw new \RuntimeException('route_unavailable');
    }

    /**
     * @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function request(string $url, string $token, array $parameters): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('route_unavailable');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'X-Esri-Authorization: Bearer ' . $token,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '') {
            throw new \RuntimeException('route_unavailable' . ($curlError === '' ? '' : ': ' . $curlError));
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('route_unavailable');
        }
        $arcError = is_array($payload['error'] ?? null) ? $payload['error'] : null;
        $arcCode = (int) ($arcError['code'] ?? 0);
        if ($status === 429 || $arcCode === 429) {
            throw new \RuntimeException('route_quota');
        }
        if ($status >= 400 || $arcError !== null) {
            throw new \RuntimeException(in_array($arcCode, [498, 499], true) ? 'route_unavailable' : 'route_failed');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{distance_km: float, duration_minutes: int, geometry: list<array{0: float, 1: float}>, steps: list<array{text: string, distance_km: float, duration_minutes: int}>}
     */
    private function normalize(array $payload): array
    {
        $route = $payload['routes']['features'][0] ?? null;
        $directions = $payload['directions'][0] ?? null;
        if (!is_array($route)) {
            throw new \RuntimeException('route_not_found');
        }

        $geometry = [];
        foreach (($route['geometry']['paths'] ?? []) as $path) {
            if (!is_array($path)) {
                continue;
            }
            foreach ($path as $point) {
                $longitude = (float) ($point[0] ?? NAN);
                $latitude = (float) ($point[1] ?? NAN);
                if (is_finite($latitude) && is_finite($longitude)) {
                    $geometry[] = [$latitude, $longitude];
                }
            }
        }
        if (count($geometry) < 2) {
            throw new \RuntimeException('route_not_found');
        }

        $attributes = is_array($route['attributes'] ?? null) ? $route['attributes'] : [];
        $distance = (float) ($attributes['Total_Kilometers'] ?? $attributes['Total_Length'] ?? 0);
        $minutes = (float) ($attributes['Total_TravelTime'] ?? $attributes['Total_Minutes'] ?? 0);
        $steps = [];
        foreach (($directions['features'] ?? []) as $feature) {
            $step = is_array($feature['attributes'] ?? null) ? $feature['attributes'] : [];
            $text = trim((string) ($step['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $steps[] = [
                'text' => $text,
                'distance_km' => round((float) ($step['length'] ?? 0), 2),
                'duration_minutes' => max(0, (int) round((float) ($step['time'] ?? 0))),
            ];
        }

        return [
            'distance_km' => round(max(0, $distance), 2),
            'duration_minutes' => max(0, (int) round($minutes)),
            'geometry' => $geometry,
            'steps' => $steps,
        ];
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? '') === 'https'
            && ($host === 'arcgis.com' || str_ends_with($host, '.arcgis.com'));
    }
}
