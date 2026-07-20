# Public Mosque API v1

The API exposes a read-only, privacy-limited view of mosque records for another website. The production base URL is the configured `APP_URL`; examples below use `https://api.example.com`.

All responses use `application/json; charset=utf-8`. Arabic text is emitted as UTF-8 JSON. Errors have this shape:

```json
{"error":{"code":"invalid_request","message":"معامل طلب غير صالح"}}
```

## Access modes

- `API_ACCESS_MODE=public`: browser-safe read-only access with exact-origin CORS and rate limiting.
- `API_ACCESS_MODE=key`: requires `X-API-Key`. Use this only server-to-server; a key embedded in browser JavaScript is not secret.

The configured `API_KEY_HASH` is a `password_hash()` result, never a raw key. Public GET responses use ETags and short cache headers. Key-mode responses are `private, no-store`.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/mosques` | Paginated mosque collection |
| GET | `/api/v1/mosques/{id}` | Lookup by registration number or national code |
| GET | `/api/v1/mosques.geojson` | Valid coordinate-bearing mosques as GeoJSON |
| GET | `/api/v1/filters` | Public communities, statuses and Friday-prayer values |
| GET | `/api/v1/health` | Returns only `{"status":"ok"}` |
| OPTIONS | the same paths | CORS preflight |

POST, PUT, PATCH and DELETE are not registered and return `405`.

### Collection parameters

| Parameter | Rules |
|---|---|
| `q` | Name, address, community or national-code search; maximum 200 characters |
| `community`, `status` | Exact public-field filters |
| `friday_prayer`, `has_coordinates` | `true` or `false`; `1`/`0` and Arabic `نعم`/`لا` are accepted |
| `national_code` | Digits only, exact match |
| `page` | Positive integer, default 1 |
| `per_page` | Positive integer, default 20, capped at 100 |
| `sort` | `name`, `national_code`, `community`, `status`, `construction_year` |
| `order` | `asc` or `desc` |

Unknown, repeated, array-valued or malformed parameters return `400`.

The collection response contains `data`, pagination `meta`, and normalized `links` for self, first, last, previous and next pages.

### Public mosque fields

`id`, `national_code`, `name`, `address`, `community`, `administrative_type`, `status`, `friday_prayer`, `construction_year`, `latitude`, `longitude`, `has_coordinates`, `image_url`, `quran_memorization`, `literacy_program`, and `guidance_program`.

Missing values are `null`; program flags are booleans; years and coordinates are JSON numbers. `image_url` is absolute and falls back to the institutional mosque image when the stored upload is absent or outside `uploads/mosques`.

### GeoJSON

`/api/v1/mosques.geojson` accepts `q`, `community`, `status`, `friday_prayer`, and `national_code`. It returns a `FeatureCollection` containing only valid coordinates. Point coordinates are always `[longitude, latitude]`.

## Client examples

Browser JavaScript in public mode:

```js
const response = await fetch(
  'https://api.example.com/api/v1/mosques?community=' + encodeURIComponent('بركان')
);
if (!response.ok) throw new Error(`API error: ${response.status}`);
const result = await response.json();
console.log(result.data);
```

PHP:

```php
<?php
$url = 'https://api.example.com/api/v1/mosques?per_page=25';
$context = stream_context_create([
    'http' => ['header' => "Accept: application/json\r\n"],
]);
$result = json_decode(file_get_contents($url, false, $context), true, 512, JSON_THROW_ON_ERROR);
```

Server-side key mode:

```bash
curl -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  'https://api.example.com/api/v1/mosques?page=1'
```

## CORS, rate limits and caching

Only exact origins in `API_ALLOWED_ORIGINS` receive CORS headers. Wildcard origins are never emitted. The default rate is 120 requests per 60 seconds per direct client IP. Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining` and `X-RateLimit-Reset`; a `429` also includes `Retry-After`.

Public collection/resource/GeoJSON responses use `Cache-Control: public, max-age=60, stale-while-revalidate=300`; filters use five minutes. Send `If-None-Match` with the returned ETag to receive `304`.

## Deployment and fallback URLs

Set:

```dotenv
APP_URL=https://api.example.com
API_ACCESS_MODE=key
API_ALLOWED_ORIGINS=https://other-website.example
API_RATE_LIMIT=120
API_RATE_WINDOW=60
API_KEY_HASH=
```

Apache must allow the supplied `public/.htaccess` and `mod_rewrite` for clean URLs. On hosts without rewrite support, use:

- `/api/v1/mosques.php`
- `/api/v1/mosque.php?id=462`
- `/api/v1/mosques.geojson.php`
- `/api/v1/filters.php`
- `/api/v1/health.php`

The machine-readable contract is [openapi.json](../public/openapi.json). Run `php tests/public_api_http_test.php` inside the app container for the API/privacy regression suite.
