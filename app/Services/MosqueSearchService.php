<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GuideImamRepository;
use App\Repositories\MosqueRepository;

/**
 * Search/filter/pagination logic for the mosque list page and the
 * live-search AJAX endpoint. Page size stays 10, as in the legacy pages.
 */
final class MosqueSearchService
{
    private const PAGE_SIZE = 10;

    public function __construct(
        private readonly MosqueRepository $mosques,
        private readonly GuideImamRepository $guideImams,
    ) {
    }

    /**
     * Assemble everything the mosque list view needs.
     *
     * @param array<string, mixed> $query raw GET parameters
     * @return array<string, mixed>
     */
    public function listPage(array $query): array
    {
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $start = ($page - 1) * self::PAGE_SIZE;

        $sort = isset($query['sort']) && in_array($query['sort'], MosqueRepository::LIST_SORT_COLUMNS, true)
            ? (string) $query['sort']
            : 'registration_number';
        $order = isset($query['order']) && strtolower((string) $query['order']) === 'asc' ? 'ASC' : 'DESC';

        $total = $this->mosques->countForList($query);

        return [
            'mosques' => $this->mosques->searchForList($query, $sort, $order, $start, self::PAGE_SIZE),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PAGE_SIZE),
            'openCount' => $this->mosques->countByStatus('مفتوح'),
            'fridayCount' => $this->mosques->countFridayMosques(),
            'communityCount' => $this->mosques->countDistinctCommunities(),
            'communities' => $this->mosques->distinctCommunities(),
            'statuses' => $this->mosques->distinctStatuses(),
            'fridayOptions' => $this->mosques->distinctFridayOptions(),
            'guideImams' => $this->guideImams->allWithMosqueCounts(),
        ];
    }

    /**
     * Live-search results in the exact legacy JSON payload shape
     * (total stays the raw DB value; pages stays ceil()'s float).
     *
     * @return array<string, mixed>
     */
    public function liveSearch(string $searchTerm, string $community, string $status, string $fridayPrayer, int $page): array
    {
        $start = ($page - 1) * self::PAGE_SIZE;

        $total = $this->mosques->countForLiveSearch($searchTerm, $community, $status, $fridayPrayer);
        $results = $this->mosques->liveSearch($searchTerm, $community, $status, $fridayPrayer, $start, self::PAGE_SIZE);

        return [
            'success' => true,
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'pages' => ceil((float) $total / self::PAGE_SIZE),
        ];
    }
}
