<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Require a logged-in user; otherwise redirect to login.php
 * (same behavior as the legacy checkAuth()).
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->session->isLoggedIn()) {
            return Response::redirect('login.php');
        }

        return $next($request);
    }
}
