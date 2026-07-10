<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use Closure;

/**
 * Admin-only routes (create/edit/delete/import) — same policy as the
 * legacy canCreateMosque()/canEditMosque()/canDeleteMosque()/canImportData()
 * helpers, which all restrict to role 'admin'.
 */
final class RequireAdmin implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->role() !== 'admin') {
            throw new HttpException(403, 'غير مصرح بالوصول إلى هذه الصفحة');
        }

        return $next($request);
    }
}
