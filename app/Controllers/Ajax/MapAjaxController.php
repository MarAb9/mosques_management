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

final class MapAjaxController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueRepository $mosques,
        private readonly ErrorHandler $errors,
    ) {
        parent::__construct($view, $session);
    }

    /** Legacy ajax/get_mosques_for_map.php */
    public function mosques(Request $request): Response
    {
        try {
            return $this->json($this->mosques->coordinatesForMapEndpoint());
        } catch (\Exception $e) {
            $this->errors->log($e);

            return $this->json(['error' => 'Failed to load mosque data'], 500);
        }
    }
}
