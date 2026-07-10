<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly DashboardService $dashboard,
    ) {
        parent::__construct($view, $session);
    }

    public function index(Request $request): Response
    {
        $data = $this->dashboard->stats();
        $data['isAdmin'] = $this->session->role() === 'admin';

        return $this->render('dashboard.index', $data);
    }
}
