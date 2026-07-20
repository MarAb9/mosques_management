<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class ApiCors implements MiddlewareInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = rtrim(trim((string) $request->header('Origin', '')), '/');
        $allowed = in_array($origin, (array) $this->config->get('api.allowed_origins', []), true);
        $response = $request->method() === 'OPTIONS' ? new Response('', 204) : $next($request);

        if (!$allowed || $origin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Accept, Content-Type, X-API-Key')
            ->withHeader('Access-Control-Max-Age', '3600');
    }
}
