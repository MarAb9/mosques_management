<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Repositories\MosqueApiRepository;
use App\Transformers\MosqueGeoJsonTransformer;
use App\Transformers\MosquePublicTransformer;
use InvalidArgumentException;

final class MosqueApiService
{
    private const FILTER_PARAMETERS = [
        'q',
        'community',
        'status',
        'friday_prayer',
        'has_coordinates',
        'national_code',
    ];

    private const GEO_FILTER_PARAMETERS = [
        'q',
        'community',
        'status',
        'friday_prayer',
        'national_code',
    ];

    public function __construct(
        private readonly MosqueApiRepository $mosques,
        private readonly MosquePublicTransformer $publicTransformer,
        private readonly MosqueGeoJsonTransformer $geoJsonTransformer,
        private readonly Config $config,
    ) {
    }

    /** @param array<string, mixed> $query
     *  @return array<string, mixed>
     */
    public function collection(array $query): array
    {
        $this->assertAllowed($query, [...self::FILTER_PARAMETERS, 'page', 'per_page', 'sort', 'order']);
        $filters = $this->normalizeFilters($query);
        $page = $this->positiveInteger($query['page'] ?? null, 1, 'page');
        $perPage = min(100, $this->positiveInteger($query['per_page'] ?? null, 20, 'per_page'));
        $sort = $this->enum($query['sort'] ?? null, [
            'name',
            'national_code',
            'community',
            'status',
            'construction_year',
        ], 'name', 'sort');
        $order = $this->enum($query['order'] ?? null, ['asc', 'desc'], 'asc', 'order');

        $total = $this->mosques->count($filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $rows = $this->mosques->search($filters, $sort, $order, ($page - 1) * $perPage, $perPage);
        $data = array_map($this->publicTransformer->transform(...), $rows);
        $linkQuery = $this->linkFilters($filters) + [
            'page' => $page,
            'per_page' => $perPage,
            'sort' => $sort,
            'order' => $order,
        ];

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'links' => [
                'self' => $this->collectionUrl($linkQuery),
                'first' => $this->collectionUrl([...$linkQuery, 'page' => 1]),
                'last' => $this->collectionUrl([...$linkQuery, 'page' => $totalPages]),
                'previous' => $page > 1
                    ? $this->collectionUrl([...$linkQuery, 'page' => $page - 1])
                    : null,
                'next' => $page < $totalPages
                    ? $this->collectionUrl([...$linkQuery, 'page' => $page + 1])
                    : null,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(string $identifier): ?array
    {
        if (preg_match('/^\d{1,50}$/', $identifier) !== 1) {
            throw new InvalidArgumentException('معرف المسجد غير صالح');
        }

        $row = $this->mosques->find($identifier);

        return $row === null ? null : $this->publicTransformer->transform($row);
    }

    /** @param array<string, mixed> $query
     *  @return array{type: string, features: list<array<string, mixed>>}
     */
    public function geoJson(array $query): array
    {
        $this->assertAllowed($query, self::GEO_FILTER_PARAMETERS);
        $rows = $this->mosques->geoJsonRows($this->normalizeFilters($query));
        $features = [];
        foreach ($rows as $row) {
            $mosque = $this->publicTransformer->transform($row);
            if ($mosque['has_coordinates']) {
                $features[] = $this->geoJsonTransformer->transform($mosque);
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $values = $this->mosques->filterValues();

        return ['data' => $values + [
            'friday_prayer' => [
                ['value' => true, 'label' => 'نعم'],
                ['value' => false, 'label' => 'لا'],
            ],
        ]];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $query): array
    {
        $filters = [];
        foreach (['q' => 200, 'community' => 100, 'status' => 100] as $field => $maximum) {
            if (array_key_exists($field, $query) && $query[$field] !== '') {
                $filters[$field] = $this->text($query[$field], $maximum, $field);
            }
        }

        if (array_key_exists('national_code', $query) && $query['national_code'] !== '') {
            $code = $this->text($query['national_code'], 50, 'national_code');
            if (preg_match('/^\d+$/', $code) !== 1) {
                throw new InvalidArgumentException('national_code غير صالح');
            }
            $filters['national_code'] = $code;
        }

        foreach (['friday_prayer', 'has_coordinates'] as $field) {
            if (array_key_exists($field, $query) && $query[$field] !== '') {
                $filters[$field] = $this->boolean($query[$field], $field);
            }
        }

        return $filters;
    }

    /** @param array<string, mixed> $query
     *  @param list<string> $allowed
     */
    private function assertAllowed(array $query, array $allowed): void
    {
        foreach (array_keys($query) as $parameter) {
            if (!in_array($parameter, $allowed, true)) {
                throw new InvalidArgumentException('معامل طلب غير صالح: ' . $parameter);
            }
        }
    }

    private function text(mixed $value, int $maximum, string $field): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maximum) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        return $value;
    }

    private function positiveInteger(mixed $value, int $default, string $field): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_scalar($value) || preg_match('/^\d+$/', (string) $value) !== 1 || (int) $value < 1) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        return (int) $value;
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $default, string $field): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        $value = strtolower(trim((string) $value));
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        return $value;
    }

    private function boolean(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException($field . ' غير صالح');
        }

        return match (mb_strtolower(trim((string) $value), 'UTF-8')) {
            'true', '1', 'نعم' => true,
            'false', '0', 'لا' => false,
            default => throw new InvalidArgumentException($field . ' غير صالح'),
        };
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, string>
     */
    private function linkFilters(array $filters): array
    {
        $query = [];
        foreach ($filters as $name => $value) {
            $query[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $query;
    }

    /** @param array<string, mixed> $query */
    private function collectionUrl(array $query): string
    {
        return rtrim((string) $this->config->get('app.url'), '/')
            . '/api/v1/mosques?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
