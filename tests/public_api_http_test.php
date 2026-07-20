<?php

declare(strict_types=1);

/**
 * Public mosque API regression test.
 * Run inside the app container:
 *   docker compose exec -T app php tests/public_api_http_test.php
 */

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\ApiKeyAuth;
use App\Middleware\ApiRateLimit;
use App\Transformers\MosquePublicTransformer;

require dirname(__DIR__) . '/vendor/autoload.php';

const API_BASE = 'http://localhost';
const APPROVED_ORIGIN = 'https://other-website.example';

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($extra === '' ? '' : ' (' . $extra . ')') . PHP_EOL;
    $ok ? $pass++ : $fail++;
}

/** @param list<string> $headers
 *  @return array{status: int, body: string, headers: array<string, string>}
 */
function apiRequest(string $path, array $headers = [], string $method = 'GET'): array
{
    $curl = curl_init(API_BASE . '/' . ltrim($path, '/'));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = (string) curl_exec($curl);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    $parsedHeaders = [];
    foreach (preg_split('/\r?\n/', substr($response, 0, $headerSize)) ?: [] as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $parsedHeaders[strtolower(trim($name))] = trim($value);
    }

    return [
        'status' => $status,
        'body' => substr($response, $headerSize),
        'headers' => $parsedHeaders,
    ];
}

/** @return array<string, mixed> */
function payload(array $response): array
{
    $decoded = json_decode($response['body'], true);

    return is_array($decoded) ? $decoded : [];
}

/** @param list<string> $forbidden */
function containsForbiddenKey(mixed $value, array $forbidden): bool
{
    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $child) {
        if (is_string($key) && in_array($key, $forbidden, true)) {
            return true;
        }
        if (containsForbiddenKey($child, $forbidden)) {
            return true;
        }
    }

    return false;
}

$rateFile = dirname(__DIR__) . '/storage/cache/api-rate-limits/' . hash('sha256', '127.0.0.1') . '.json';
@unlink($rateFile);

$pdo = new PDO('mysql:host=db;dbname=mosques_management;charset=utf8mb4', 'mosques', 'mosques_password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sample = $pdo->query(
    'SELECT registration_number, national_code, mosque_name, community, status, friday_prayer
     FROM mosques ORDER BY registration_number LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
$coordinateSample = $pdo->query(
    'SELECT registration_number, national_code, longitude, latitude
     FROM mosques
     WHERE latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180
     ORDER BY registration_number LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

check('Database has an API sample mosque', is_array($sample));
check('Database has a GeoJSON sample mosque', is_array($coordinateSample));
$openApi = json_decode((string) file_get_contents(dirname(__DIR__) . '/public/openapi.json'), true);
check('OpenAPI document is valid and lists all endpoints', is_array($openApi)
    && count($openApi['paths'] ?? []) === 5);

// Collection, pagination, filters, sorting, privacy and cache semantics.
$response = apiRequest('api/v1/mosques');
$collection = payload($response);
$first = $collection['data'][0] ?? [];
$safeFields = [
    'id',
    'national_code',
    'name',
    'address',
    'community',
    'administrative_type',
    'status',
    'friday_prayer',
    'construction_year',
    'latitude',
    'longitude',
    'has_coordinates',
    'image_url',
    'quran_memorization',
    'literacy_program',
    'guidance_program',
];
$forbidden = [
    'imam_name',
    'imam_phone',
    'imam_registration',
    'preacher_name',
    'preacher_phone',
    'preacher_registration',
    'muezzin_name',
    'muezzin_phone',
    'muezzin_registration',
    'administrative_attachment',
    'notes',
];

check('Collection is public and returns 200', $response['status'] === 200);
check('Collection uses UTF-8 JSON', str_starts_with(
    strtolower($response['headers']['content-type'] ?? ''),
    'application/json; charset=utf-8'
));
check('Collection has default pagination', ($collection['meta']['current_page'] ?? null) === 1
    && ($collection['meta']['per_page'] ?? null) === 20
    && ($collection['meta']['total'] ?? 0) > 0);
check('Collection exposes the exact safe field list', array_keys($first) === $safeFields);
check('Privacy fields never appear anywhere', !containsForbiddenKey($collection, $forbidden));
check('Arabic JSON remains valid UTF-8', json_last_error() === JSON_ERROR_NONE
    && preg_match('//u', $response['body']) === 1);
check('Boolean fields are normalized', is_bool($first['friday_prayer'] ?? null)
    && (is_bool($first['quran_memorization'] ?? null) || ($first['quran_memorization'] ?? null) === null));
check('Numeric fields use JSON numbers or null', (is_int($first['construction_year'] ?? null)
        || ($first['construction_year'] ?? null) === null)
    && (is_float($first['latitude'] ?? null)
        || is_int($first['latitude'] ?? null)
        || ($first['latitude'] ?? null) === null));
check('Image URL is absolute and public', filter_var($first['image_url'] ?? '', FILTER_VALIDATE_URL) !== false);
check('Collection sends cache and rate headers', str_contains(
    $response['headers']['cache-control'] ?? '',
    'public'
) && isset($response['headers']['etag'], $response['headers']['x-ratelimit-limit']));
check('Public API does not start a session', !isset($response['headers']['set-cookie']));

$legacyAjax = apiRequest('ajax/search_mosques.php?q=test');
check('Existing AJAX endpoints remain session-protected', $legacyAjax['status'] === 302
    && str_contains($legacyAjax['headers']['location'] ?? '', 'login.php'));

$response = apiRequest('api/v1/mosques?per_page=1000&page=2');
$page = payload($response);
check('Page size is capped at 100', $response['status'] === 200
    && ($page['meta']['per_page'] ?? null) === 100);

$query = rawurlencode(mb_substr((string) $sample['mosque_name'], 0, 3, 'UTF-8'));
$response = apiRequest('api/v1/mosques?q=' . $query);
$search = payload($response);
check('Public search finds mosque names', $response['status'] === 200
    && ($search['meta']['total'] ?? 0) > 0);

foreach ([
    'community' => $sample['community'],
    'status' => $sample['status'],
    'friday_prayer' => $sample['friday_prayer'] === 'نعم' ? 'true' : 'false',
    'has_coordinates' => 'true',
    'national_code' => $sample['national_code'],
] as $filter => $value) {
    $response = apiRequest('api/v1/mosques?' . $filter . '=' . rawurlencode((string) $value));
    check('Collection filter works: ' . $filter, $response['status'] === 200
        && (payload($response)['meta']['total'] ?? 0) > 0);
}

$response = apiRequest('api/v1/mosques?sort=construction_year&order=desc');
check('Allowed sorting works', $response['status'] === 200);
$response = apiRequest('api/v1/mosques?sort=imam_phone');
check('Invalid sorting is rejected', $response['status'] === 400
    && (payload($response)['error']['code'] ?? '') === 'invalid_request');
$response = apiRequest('api/v1/mosques?q=a&q=b');
check('Duplicate query parameters are rejected', $response['status'] === 400);

$etag = $collection === [] ? '' : ($response = apiRequest('api/v1/mosques'))['headers']['etag'] ?? '';
$response = apiRequest('api/v1/mosques', ['If-None-Match: ' . $etag]);
check('Matching ETag returns 304', $etag !== '' && $response['status'] === 304 && $response['body'] === '');

// Single resource lookup and identifier validation.
$response = apiRequest('api/v1/mosques/' . $sample['registration_number']);
$byRegistration = payload($response);
check('Single mosque lookup by registration number works', $response['status'] === 200
    && ($byRegistration['data']['id'] ?? '') === (string) $sample['registration_number']);
check('Single mosque stays private-field free', !containsForbiddenKey($byRegistration, $forbidden));

$response = apiRequest('api/v1/mosques/' . $sample['national_code']);
$byNationalCode = payload($response);
check('Single mosque lookup by national code works', $response['status'] === 200
    && ($byNationalCode['data']['id'] ?? '') === (string) $sample['registration_number']);

$response = apiRequest('api/v1/mosques/999999999999');
check('Missing mosque returns a JSON 404', $response['status'] === 404
    && (payload($response)['error']['code'] ?? '') === 'not_found');
$response = apiRequest('api/v1/mosques/not-valid');
check('Malformed mosque identifier returns 400', $response['status'] === 400
    && (payload($response)['error']['code'] ?? '') === 'invalid_request');

// GeoJSON, filter metadata and health.
$response = apiRequest('api/v1/mosques.geojson');
$geoJson = payload($response);
$feature = $geoJson['features'][0] ?? [];
$coordinates = $feature['geometry']['coordinates'] ?? [];
check('GeoJSON is a valid FeatureCollection', $response['status'] === 200
    && ($geoJson['type'] ?? '') === 'FeatureCollection'
    && ($feature['type'] ?? '') === 'Feature');
check('GeoJSON coordinates are longitude then latitude', count($coordinates) === 2
    && $coordinates[0] >= -180 && $coordinates[0] <= 180
    && $coordinates[1] >= -90 && $coordinates[1] <= 90);
check('GeoJSON omits all private fields', !containsForbiddenKey($geoJson, $forbidden));
$response = apiRequest('api/v1/mosques.geojson?community=' . rawurlencode((string) $sample['community']));
check('GeoJSON supports safe collection filters', $response['status'] === 200
    && count(payload($response)['features'] ?? []) > 0);
$response = apiRequest('api/v1/mosques.geojson?has_coordinates=false');
check('GeoJSON rejects filters that cannot apply to a valid FeatureCollection', $response['status'] === 400);

$response = apiRequest('api/v1/filters');
$filters = payload($response);
check('Filter metadata returns public values', $response['status'] === 200
    && is_array($filters['data']['communities'] ?? null)
    && is_array($filters['data']['statuses'] ?? null)
    && ($filters['data']['friday_prayer'][0]['value'] ?? null) === true);

$response = apiRequest('api/v1/health');
check('Health endpoint returns only status ok', $response['status'] === 200
    && payload($response) === ['status' => 'ok']);
check('GET API needs no CSRF token or session', $response['status'] === 200
    && !isset($response['headers']['set-cookie']));

// Exact CORS allow-list, preflight and read-only routing.
$response = apiRequest('api/v1/health', ['Origin: ' . APPROVED_ORIGIN]);
check('Approved CORS origin is reflected exactly', ($response['headers']['access-control-allow-origin'] ?? '') === APPROVED_ORIGIN
    && ($response['headers']['vary'] ?? '') === 'Origin');
$response = apiRequest('api/v1/health', ['Origin: https://unapproved.example']);
check('Unknown CORS origin receives no allow header', !isset($response['headers']['access-control-allow-origin']));
$response = apiRequest('api/v1/health', [
    'Origin: ' . APPROVED_ORIGIN,
    'Access-Control-Request-Method: GET',
], 'OPTIONS');
check('OPTIONS preflight is handled without authentication', $response['status'] === 204
    && ($response['headers']['access-control-allow-methods'] ?? '') === 'GET, OPTIONS');
$response = apiRequest('api/v1/mosques', [], 'POST');
check('Write methods are rejected with JSON 405', $response['status'] === 405
    && (payload($response)['error']['code'] ?? '') === 'method_not_allowed');

// Physical shared-hosting fallbacks dispatch through the same API.
$response = apiRequest('api/v1/mosques.php');
check('Physical collection fallback works', $response['status'] === 200);
$response = apiRequest('api/v1/mosque.php?id=' . $sample['registration_number']);
check('Physical single-resource fallback works', $response['status'] === 200
    && (payload($response)['data']['id'] ?? '') === (string) $sample['registration_number']);
$response = apiRequest('api/v1/mosques.geojson.php');
check('Physical GeoJSON fallback works', $response['status'] === 200
    && (payload($response)['type'] ?? '') === 'FeatureCollection');

$imageTransformer = new MosquePublicTransformer(new Config(dirname(__DIR__) . '/config'));
$unsafeImage = $imageTransformer->transform([
    'registration_number' => 1,
    'main_image' => '../../storage/private.jpg',
]);
check('Unsafe image paths fall back to a public URL', !str_contains($unsafeImage['image_url'], '..')
    && str_contains($unsafeImage['image_url'], '/assets/images/institutional/'));

// Key mode uses password_verify against a stored hash; the raw key is never configured.
$keyConfigDir = sys_get_temp_dir() . '/mosque_api_key_' . bin2hex(random_bytes(4));
mkdir($keyConfigDir, 0700, true);
$keyHash = password_hash('integration-secret', PASSWORD_DEFAULT);
file_put_contents(
    $keyConfigDir . '/api.php',
    '<?php return ' . var_export(['access_mode' => 'key', 'key_hash' => $keyHash], true) . ';'
);
$keyMiddleware = new ApiKeyAuth(new Config($keyConfigDir));
$next = static fn (Request $request): Response => Response::json(['ok' => true]);
$baseServer = ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '203.0.113.10'];
$valid = new Request([], [], [], $baseServer + ['HTTP_X_API_KEY' => 'integration-secret']);
$invalid = new Request([], [], [], $baseServer + ['HTTP_X_API_KEY' => 'wrong']);
$missing = new Request([], [], [], $baseServer);
check('Valid API key passes in key mode', $keyMiddleware->handle($valid, $next)->status() === 200);
check('Invalid API key returns 401', $keyMiddleware->handle($invalid, $next)->status() === 401);
check('Missing API key returns 401', $keyMiddleware->handle($missing, $next)->status() === 401);
unlink($keyConfigDir . '/api.php');
rmdir($keyConfigDir);

// File-backed rate limiting is deterministic and isolated outside public/.
$rateConfigDir = sys_get_temp_dir() . '/mosque_api_rate_config_' . bin2hex(random_bytes(4));
$rateCacheDir = sys_get_temp_dir() . '/mosque_api_rate_cache_' . bin2hex(random_bytes(4));
mkdir($rateConfigDir, 0700, true);
file_put_contents(
    $rateConfigDir . '/api.php',
    '<?php return ' . var_export([
        'rate_limit' => 2,
        'rate_window' => 60,
        'rate_cache_path' => $rateCacheDir,
    ], true) . ';'
);
$rateMiddleware = new ApiRateLimit(new Config($rateConfigDir));
$rateRequest = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '198.51.100.20']);
$rateOne = $rateMiddleware->handle($rateRequest, $next);
$rateTwo = $rateMiddleware->handle($rateRequest, $next);
$rateThree = $rateMiddleware->handle($rateRequest, $next);
check('Rate limiter allows requests within the limit', $rateOne->status() === 200 && $rateTwo->status() === 200);
check('Rate limiter returns 429 after the limit', $rateThree->status() === 429
    && $rateThree->header('Retry-After') !== null
    && $rateThree->header('X-RateLimit-Remaining') === '0');
foreach (glob($rateCacheDir . '/*.json') ?: [] as $file) {
    unlink($file);
}
rmdir($rateCacheDir);
unlink($rateConfigDir . '/api.php');
rmdir($rateConfigDir);

// Even with debug enabled, an API database failure must stay generic JSON.
$failureScript = <<<'PHP'
putenv('APP_DEBUG=true');
putenv('API_ACCESS_MODE=public');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=1');
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/api/v1/mosques',
    'REMOTE_ADDR' => '127.0.0.2',
];
require '/var/www/html/public/index.php';
PHP;
$output = [];
$exitCode = 0;
exec('php -r ' . escapeshellarg($failureScript), $output, $exitCode);
$failureBody = implode(PHP_EOL, $output);
$failure = json_decode($failureBody, true);
check('Unexpected API failures return safe JSON 500 bodies', ($failure['error']['code'] ?? '') === 'internal_error'
    && !str_contains($failureBody, '/var/www')
    && !str_contains(strtolower($failureBody), 'stack trace')
    && !str_contains(strtolower($failureBody), 'pdo'));

@unlink($rateFile);

echo PHP_EOL . '--- ' . $pass . ' passed, ' . $fail . ' failed ---' . PHP_EOL;
exit($fail > 0 ? 1 : 0);
