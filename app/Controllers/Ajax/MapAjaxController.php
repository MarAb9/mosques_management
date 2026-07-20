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
use App\Services\ArcGisRouteService;

final class MapAjaxController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly MosqueRepository $mosques,
        private readonly ErrorHandler $errors,
        private readonly ArcGisRouteService $routes,
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

    public function route(Request $request): Response
    {
        $csrf = $request->post('csrf_token');
        if (!is_string($csrf) || !hash_equals($this->session->csrfToken(), $csrf)) {
            return $this->routeError('csrf_failed', 419);
        }

        $values = [
            $request->post('origin_latitude'),
            $request->post('origin_longitude'),
            $request->post('destination_latitude'),
            $request->post('destination_longitude'),
        ];
        if (array_filter($values, static fn (mixed $value): bool => !is_numeric($value)) !== []) {
            return $this->routeError('route_invalid_coordinates', 422);
        }

        try {
            $route = $this->routes->route(
                (float) $values[0],
                (float) $values[1],
                (float) $values[2],
                (float) $values[3],
                (string) $request->post('mode', 'driving'),
            );

            return $this->json(['data' => $route]);
        } catch (\DomainException $e) {
            return $this->routeError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->errors->log($e);
            $code = strtok($e->getMessage(), ':') ?: 'route_failed';
            $status = match ($code) {
                'route_rate_limit', 'route_quota' => 429,
                'route_not_found' => 404,
                'route_unavailable' => 503,
                default => 502,
            };

            return $this->routeError($code, $status);
        }
    }

    private function routeError(string $code, int $status): Response
    {
        return $this->json(['error' => ['code' => $code]], $status);
    }
}
