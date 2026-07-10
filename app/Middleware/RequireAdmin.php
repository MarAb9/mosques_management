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
class RequireAdmin implements MiddlewareInterface
{
    /** Overridden by permission subclasses to keep the legacy 403 texts. */
    protected string $message = 'غير مصرح بالوصول إلى هذه الصفحة';

    public function __construct(protected readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->session->role() !== 'admin') {
            throw new HttpException(403, $this->message);
        }

        return $next($request);
    }
}
