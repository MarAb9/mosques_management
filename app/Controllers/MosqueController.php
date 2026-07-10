<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\MosqueSearchService;

final class MosqueController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueSearchService $search,
    ) {
        parent::__construct($view, $session);
    }

    public function index(Request $request): Response
    {
        $query = (array) $request->query();
        $data = $this->search->listPage($query);

        // One-shot row highlight (legacy behavior: cleared only when the
        // highlighted mosque is on the rendered page).
        $highlight = $this->session->get('highlight_mosque_national_code');
        if ($highlight !== null) {
            foreach ($data['mosques'] as $row) {
                if ($row['national_code'] == $highlight) {
                    $this->session->remove('highlight_mosque_national_code');
                    break;
                }
            }
        }

        $data += [
            'queryParams' => $query,
            'isAdmin' => $this->session->role() === 'admin',
            'csrfToken' => $this->session->csrfToken(),
            'highlightNationalCode' => $highlight,
        ];

        return $this->render('mosques.index', $data);
    }
}
