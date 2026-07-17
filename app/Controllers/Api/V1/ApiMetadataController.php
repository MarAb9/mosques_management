<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\MosqueApiService;

final class ApiMetadataController
{
    public function __construct(
        private readonly MosqueApiService $mosques,
        private readonly Config $config,
    ) {
    }

    public function filters(Request $request): Response
    {
        if ($request->hasDuplicateQueryParameters() || $request->query() !== []) {
            return JsonResponse::error('invalid_request', 'معامل طلب غير صالح', 400);
        }

        return $this->success($this->mosques->filters(), $request, 300);
    }

    public function health(Request $request): Response
    {
        if ($request->hasDuplicateQueryParameters() || $request->query() !== []) {
            return JsonResponse::error('invalid_request', 'معامل طلب غير صالح', 400);
        }

        return $this->success(['status' => 'ok'], $request, 60);
    }

    private function success(mixed $data, Request $request, int $maxAge): Response
    {
        if ($this->config->get('api.access_mode') !== 'public') {
            return JsonResponse::create($data)->withHeader('Cache-Control', 'private, no-store');
        }

        return JsonResponse::cacheable($data, $request, 'public, max-age=' . $maxAge);
    }
}
