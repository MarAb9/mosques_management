<?php

declare(strict_types=1);

namespace App\Controllers\Ajax;

use App\Core\Controller;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\MosqueRepository;
use App\Services\MosqueDetailsService;
use App\Services\MosqueSearchService;
use PDOException;

final class MosqueAjaxController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueSearchService $search,
        private readonly MosqueDetailsService $details,
        private readonly MosqueRepository $mosques,
        private readonly ErrorHandler $errors,
    ) {
        parent::__construct($view, $session);
    }

    /** Legacy ajax/search_mosques.php */
    public function search(Request $request): Response
    {
        $searchTerm = trim((string) $request->query('q', ''));
        $community = trim((string) $request->query('community', ''));
        $status = trim((string) $request->query('status', ''));
        $fridayPrayer = trim((string) $request->query('friday_prayer', ''));
        $page = (int) $request->query('page', 1);

        try {
            $payload = $this->search->liveSearch($searchTerm, $community, $status, $fridayPrayer, $page);

            return $this->json($payload);
        } catch (PDOException $e) {
            $this->errors->log($e);

            return $this->json(['success' => false, 'message' => 'Database error']);
        }
    }

    /** Legacy ajax/get_mosque_details.php */
    public function details(Request $request): Response
    {
        // Same headers as the legacy endpoint, including the charset variant.
        $noCache = fn (Response $r): Response => $r
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');

        $id = $request->query('id');

        if ($id === null || $id === '') {
            return $noCache($this->json(['success' => false, 'message' => 'ID parameter is missing or empty'], 400));
        }

        try {
            $data = $this->details->details((string) $id);

            if ($data === null) {
                return $noCache($this->json(['success' => false, 'message' => 'Mosque not found'], 404));
            }

            return $noCache($this->json([
                'success' => true,
                'data' => $data,
                'timestamp' => time(),
            ]));
        } catch (PDOException $e) {
            $this->errors->log($e);

            return $noCache($this->json(['success' => false, 'message' => 'Database error'], 500));
        }
    }

    /** Legacy ajax/get_mosque_stats.php */
    public function stats(Request $request): Response
    {
        try {
            return $this->json([
                'success' => true,
                'statusStats' => $this->mosques->statusStats(),
                'fridayStats' => $this->mosques->fridayStats(),
                'communityStats' => $this->mosques->communityStats(),
            ]);
        } catch (PDOException $e) {
            $this->errors->log($e);

            return $this->json(['success' => false, 'message' => 'Failed to load statistics']);
        }
    }
}
