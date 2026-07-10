<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use Closure;

/**
 * CSRF validation for state-changing requests.
 *
 * Uses the same session key and comparison as the legacy
 * verify_csrf_token(): posted 'csrf_token' must hash_equals the
 * session token. Non-POST requests pass through.
 */
final class VerifyCsrf implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() === 'POST') {
            $posted = $request->post('csrf_token', '');

            if (!is_string($posted) || !hash_equals($this->session->csrfToken(), $posted)) {
                throw new HttpException(403, 'طلب غير صالح');
            }
        }

        return $next($request);
    }
}
