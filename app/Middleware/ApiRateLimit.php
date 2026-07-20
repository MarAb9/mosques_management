<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class ApiRateLimit implements MiddlewareInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) $this->config->get('api.rate_limit', 120);
        $window = (int) $this->config->get('api.rate_window', 60);
        $directory = (string) $this->config->get('api.rate_cache_path');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            return JsonResponse::error('internal_error', 'حدث خطأ غير متوقع', 500);
        }

        $file = $directory . '/' . hash('sha256', $request->clientIp()) . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return JsonResponse::error('internal_error', 'حدث خطأ غير متوقع', 500);
        }

        $now = time();
        $raw = stream_get_contents($handle);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state) || $now >= (int) ($state['started_at'] ?? 0) + $window) {
            $state = ['started_at' => $now, 'count' => 0];
        }
        $state['count']++;

        $encoded = json_encode($state, JSON_THROW_ON_ERROR);
        rewind($handle);
        $written = ftruncate($handle, 0);
        $bytes = $written ? fwrite($handle, $encoded) : false;
        $written = $bytes === strlen($encoded) && fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!$written) {
            return JsonResponse::error('internal_error', 'حدث خطأ غير متوقع', 500);
        }

        $reset = (int) $state['started_at'] + $window;
        $remaining = max(0, $limit - (int) $state['count']);
        $response = (int) $state['count'] > $limit
            ? JsonResponse::error('rate_limit_exceeded', 'تم تجاوز الحد المسموح للطلبات', 429)
                ->withHeader('Retry-After', (string) max(1, $reset - $now))
            : $next($request);

        if ((int) $state['count'] === 1) {
            // ponytail: O(n) once per client window; use a shared cache when traffic makes this measurable.
            $this->cleanupExpired($directory, $now, $window);
        }

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $reset);
    }

    private function cleanupExpired(string $directory, int $now, int $window): void
    {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            $handle = @fopen($file, 'r+');
            if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                continue;
            }

            $state = json_decode((string) stream_get_contents($handle), true);
            if (!is_array($state) || $now >= (int) ($state['started_at'] ?? 0) + $window) {
                @unlink($file);
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
