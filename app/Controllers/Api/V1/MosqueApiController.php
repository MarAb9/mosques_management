<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Config;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\MosqueApiService;
use InvalidArgumentException;

final class MosqueApiController
{
    public function __construct(
        private readonly MosqueApiService $mosques,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        if ($request->hasDuplicateQueryParameters()) {
            return JsonResponse::error('invalid_request', 'لا يسمح بتكرار معاملات الطلب', 400);
        }

        try {
            return $this->success($this->mosques->collection((array) $request->query()), $request);
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error('invalid_request', $e->getMessage(), 400);
        }
    }

    public function show(Request $request): Response
    {
        $query = (array) $request->query();
        if ($request->hasDuplicateQueryParameters() || array_diff(array_keys($query), ['id']) !== []) {
            return JsonResponse::error('invalid_request', 'معامل طلب غير صالح', 400);
        }

        try {
            $mosque = $this->mosques->find((string) $request->route('id', ''));
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error('invalid_request', $e->getMessage(), 400);
        }

        return $mosque === null
            ? JsonResponse::error('not_found', 'المسجد غير موجود', 404)
            : $this->success(['data' => $mosque], $request);
    }

    public function geoJson(Request $request): Response
    {
        if ($request->hasDuplicateQueryParameters()) {
            return JsonResponse::error('invalid_request', 'لا يسمح بتكرار معاملات الطلب', 400);
        }

        try {
            return $this->success($this->mosques->geoJson((array) $request->query()), $request);
        } catch (InvalidArgumentException $e) {
            return JsonResponse::error('invalid_request', $e->getMessage(), 400);
        }
    }

    private function success(mixed $data, Request $request): Response
    {
        if ($this->config->get('api.access_mode') !== 'public') {
            return JsonResponse::create($data)->withHeader('Cache-Control', 'private, no-store');
        }

        return JsonResponse::cacheable($data, $request, 'public, max-age=60, stale-while-revalidate=300');
    }
}
