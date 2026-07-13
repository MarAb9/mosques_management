<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Redirect already-authenticated users away from guest-only pages
 * (login page redirects to index.php, as in the legacy login.php).
 */
final class Guest implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->isLoggedIn()) {
            return Response::redirect('index.php');
        }

        return $next($request);
    }
}
