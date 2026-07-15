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

        try {
            $totalWithCoords = $this->mosques->countWithCoordinates();
            $totalPages = (int) max(1, ceil($totalWithCoords / self::PAGE_SIZE));
            $page = min($requestedPage, $totalPages);
            $start = ($page - 1) * self::PAGE_SIZE;
            $data = [
                'totalWithCoords' => $totalWithCoords,
                'totalPages' => $totalPages,
                'mosques' => $this->mosques->withCoordinatesPaginated($start, self::PAGE_SIZE),
                'allMosques' => $this->mosques->allWithCoordinates(),
                'totalMosques' => $this->mosques->countAll(),
                'communities' => $this->mosques->distinctCommunitiesForMap(),
                'statuses' => $this->mosques->distinctStatuses(),
                'mapProvider' => (string) $this->config->get('maps.provider', 'google'),
                'googleMapsApiKey' => (string) $this->config->get('maps.google_api_key', ''),
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
                'allMosques' => [],
                'totalWithCoords' => 0,
                'totalMosques' => 0,
                'totalPages' => 1,
                'communities' => [],
                'statuses' => [],
            ];
            $page = 1;
            $start = 0;
        }

        $data['page'] = $page;
        $data['start'] = $start;
        $data['limit'] = self::PAGE_SIZE;

        return $this->render('maps.index', $data);
    }
}






