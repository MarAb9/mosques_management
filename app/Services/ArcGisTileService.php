<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class ArcGisTileService
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @return array{body: string, content_type: string} */
    public function tile(string $layer, mixed $zoom, mixed $x, mixed $y, string $referer = ''): array
    {
        $template = match ($layer) {
            'street' => (string) $this->config->get('maps.street.url', ''),
            'imagery' => (string) $this->config->get('maps.satellite.url', ''),
            'labels' => (string) $this->config->get('maps.satellite.labels_url', ''),
            default => throw new \DomainException('tile_invalid_request'),
        };
        $coordinates = $this->coordinates($zoom, $x, $y);
        $token = (string) $this->config->get('maps.access_token', '');
        if ($token === '' || !$this->isAllowedUrl($template)) {
            throw new \RuntimeException('tile_unavailable');
        }

        $url = strtr($template, [
            '{z}' => (string) $coordinates['z'],
            '{x}' => (string) $coordinates['x'],
            '{y}' => (string) $coordinates['y'],
        ]);
        $parameters = ['language' => 'ar'];
        $headers = ['Accept: image/*'];
        if ($layer === 'imagery') {
            $parameters['token'] = $token;
        } else {
            $headers[] = 'X-Esri-Authorization: Bearer ' . $token;
        }
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('tile_unavailable');
        }
        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_REFERER => $this->origin($referer),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE)));
        curl_close($handle);

        if ($status !== 200 || !is_string($body) || $body === '' || !str_starts_with($contentType, 'image/')) {
            throw new \RuntimeException($status === 429 ? 'tile_quota' : 'tile_unavailable');
        }

        return ['body' => $body, 'content_type' => $contentType];
    }

    /** @return array{z: int, x: int, y: int} */
    private function coordinates(mixed $zoom, mixed $x, mixed $y): array
    {
        foreach ([$zoom, $x, $y] as $value) {
            if (!is_int($value) && (!is_string($value) || preg_match('/^\d+$/D', $value) !== 1)) {
                throw new \DomainException('tile_invalid_request');
            }
        }
        $z = (int) $zoom;
        $tileX = (int) $x;
        $tileY = (int) $y;
        $maximum = $z >= 0 && $z <= 22 ? (2 ** $z) - 1 : -1;
        if ($maximum < 0 || $tileX < 0 || $tileY < 0 || $tileX > $maximum || $tileY > $maximum) {
            throw new \DomainException('tile_invalid_request');
        }

        return ['z' => $z, 'x' => $tileX, 'y' => $tileY];
    }

    private function origin(string $referer): string
    {
        foreach ([$referer, (string) $this->config->get('app.url', '')] as $candidate) {
            $parts = parse_url($candidate);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));
            if (in_array($scheme, ['http', 'https'], true) && $host !== '') {
                $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

                return $scheme . '://' . $host . $port . '/';
            }
        }

        return '';
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), [
                'static-map-tiles-api.arcgis.com',
                'ibasemaps-api.arcgis.com',
            ], true);
    }
}
