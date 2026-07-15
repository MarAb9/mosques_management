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
        $requestedPage = max(1, isset($query['page']) ? (int) $query['page'] : 1);

        $sort = isset($query['sort']) && in_array($query['sort'], MosqueRepository::LIST_SORT_COLUMNS, true)
            ? (string) $query['sort']
            : 'registration_number';
        $order = isset($query['order']) && strtolower((string) $query['order']) === 'asc' ? 'ASC' : 'DESC';

        $total = $this->mosques->countForList($query);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($requestedPage, $pages);
        $start = ($page - 1) * self::PAGE_SIZE;

        return [
            'mosques' => $this->mosques->searchForList($query, $sort, $order, $start, self::PAGE_SIZE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
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
    public function liveSearch(
        string $searchTerm,
        string $community,
        string $status,
        string $fridayPrayer,
        string $guideImam,
        int $page,
    ): array
    {
        $page = max(1, $page);
        $total = $this->mosques->countForLiveSearch($searchTerm, $community, $status, $fridayPrayer, $guideImam);
        $pages = max(1, (int) ceil((float) $total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $start = ($page - 1) * self::PAGE_SIZE;
        $results = $this->mosques->liveSearch(
            $searchTerm,
            $community,
            $status,
            $fridayPrayer,
            $guideImam,
            $start,
            self::PAGE_SIZE,
        );

        return [
            'success' => true,
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
