<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\MosqueRepository;
use PDOException;

final class MapController extends Controller
{
    private const PAGE_SIZE = 15;

    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueRepository $mosques,
        private readonly ErrorHandler $errors,
        private readonly Config $config,
    ) {
        parent::__construct($view, $session);
    }

    /** Legacy mosque_maps.php */
    public function index(Request $request): Response
    {
        $requestedPage = max(1, (int) $request->query('page', 1));
        $mapConfig = $this->mapConfig();

        try {
            $totalWithCoords = $this->mosques->countWithCoordinates();
            $totalPages = (int) max(1, ceil($totalWithCoords / self::PAGE_SIZE));
            $page = min($requestedPage, $totalPages);
            $start = ($page - 1) * self::PAGE_SIZE;
            $allMosques = $this->mosques->allWithCoordinates();
            $data = [
                'totalWithCoords' => $totalWithCoords,
                'totalPages' => $totalPages,
                'mosques' => $this->mosques->withCoordinatesPaginated($start, self::PAGE_SIZE),
                'mosqueGeoJson' => $this->toGeoJson($allMosques),
                'totalMosques' => $this->mosques->countAll(),
                'communities' => $this->mosques->distinctCommunitiesForMap(),
                'statuses' => $this->mosques->distinctStatuses(),
                'mapConfig' => $mapConfig,
                'mapDefaults' => [
                    'latitude' => (float) $this->config->get('maps.default_latitude', 34.6814),
                    'longitude' => (float) $this->config->get('maps.default_longitude', -1.9086),
                    'zoom' => (int) $this->config->get('maps.default_zoom', 9),
                ],
            ];
        } catch (PDOException $e) {
            $this->errors->log($e);
            // Legacy fallback: render the page with empty data.
            $data = [
                'mosques' => [],
                'mosqueGeoJson' => ['type' => 'FeatureCollection', 'features' => []],
                'totalWithCoords' => 0,
                'totalMosques' => 0,
                'totalPages' => 1,
                'communities' => [],
                'statuses' => [],
                'mapConfig' => $mapConfig,
                'mapDefaults' => [
                    'latitude' => 34.6814,
                    'longitude' => -1.9086,
                    'zoom' => 9,
                ],
            ];
            $page = 1;
            $start = 0;
        }

        $data['page'] = $page;
        $data['start'] = $start;
        $data['limit'] = self::PAGE_SIZE;

        return $this->render('maps.index', $data);
    }

    /** @return array<string, mixed> */
    private function mapConfig(): array
    {
        return [
            'engine' => 'leaflet',
            'token' => (string) $this->config->get('maps.access_token', ''),
            'street' => (array) $this->config->get('maps.street', []),
            'satellite' => (array) $this->config->get('maps.satellite', []),
            'routing' => [
                'enabled' => (string) $this->config->get('maps.routing.token', '') !== '',
                'endpoint' => 'ajax/map_route.php',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $mosques
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function toGeoJson(array $mosques): array
    {
        $features = [];

        foreach ($mosques as $mosque) {
            $latitude = filter_var($mosque['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($mosque['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                continue;
            }

            $registrationNumber = (string) ($mosque['registration_number'] ?? '');
            $features[] = [
                'type' => 'Feature',
                'id' => $registrationNumber,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $longitude, (float) $latitude],
                ],
                'properties' => [
                    'registration_number' => $registrationNumber,
                    'national_code' => (string) ($mosque['national_code'] ?? ''),
                    'mosque_name' => (string) ($mosque['mosque_name'] ?? ''),
                    'status' => (string) ($mosque['status'] ?? ''),
                    'address' => (string) ($mosque['address'] ?? ''),
                    'community' => (string) ($mosque['community'] ?? ''),
                    'imam_name' => (string) ($mosque['imam_name'] ?? ''),
                    'guide_imam' => (string) ($mosque['guide_imam'] ?? ''),
                    'friday_prayer' => (string) ($mosque['friday_prayer'] ?? ''),
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}






