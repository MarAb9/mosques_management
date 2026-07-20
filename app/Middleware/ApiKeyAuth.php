<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class ApiKeyAuth implements MiddlewareInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->config->get('api.access_mode') === 'public') {
            return $next($request);
        }

        $key = (string) $request->header('X-API-Key', '');
        $hash = (string) $this->config->get('api.key_hash', '');
        if ($key !== '' && $hash !== '' && password_verify($key, $hash)) {
            return $next($request);
        }

        error_log('API authentication failed for client ' . $request->clientIp());

        return JsonResponse::error('unauthorized', 'مفتاح API مفقود أو غير صالح', 401);
    }
}
