<?php

declare(strict_types=1);

namespace App\Controllers;

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
    ) {
        parent::__construct($view, $session);
    }

    /** Legacy mosque_maps.php */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $start = ($page - 1) * self::PAGE_SIZE;

        try {
            $totalWithCoords = $this->mosques->countWithCoordinates();
            $data = [
                'totalWithCoords' => $totalWithCoords,
                'totalPages' => (int) max(1, ceil($totalWithCoords / self::PAGE_SIZE)),
                'mosques' => $this->mosques->withCoordinatesPaginated($start, self::PAGE_SIZE),
                'allMosques' => $this->mosques->allWithCoordinates(),
                'totalMosques' => $this->mosques->countAll(),
                'communities' => $this->mosques->distinctCommunitiesForMap(),
                'statuses' => $this->mosques->distinctStatuses(),
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
        }

        $data['page'] = $page;
        $data['start'] = $start;
        $data['limit'] = self::PAGE_SIZE;

        return $this->render('maps.index', $data);
    }
}
