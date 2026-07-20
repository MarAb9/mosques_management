<?php

declare(strict_types=1);

namespace App\Core;

final class JsonResponse
{
    public static function create(mixed $data, int $status = 200): Response
    {
        return new Response(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            ),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function error(string $code, string $message, int $status): Response
    {
        return self::create(['error' => ['code' => $code, 'message' => $message]], $status)
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function cacheable(mixed $data, Request $request, string $cacheControl): Response
    {
        $response = self::create($data);
        $etag = chr(34) . hash('sha256', $response->body()) . chr(34);
        $response
            ->withHeader('Cache-Control', $cacheControl)
            ->withHeader('ETag', $etag);

        $candidates = array_map('trim', explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array('*', $candidates, true) || in_array($etag, $candidates, true)) {
            return (new Response('', 304, ['Content-Type' => 'application/json; charset=utf-8']))
                ->withHeader('Cache-Control', $cacheControl)
                ->withHeader('ETag', $etag);
        }

        return $response;
    }
}
