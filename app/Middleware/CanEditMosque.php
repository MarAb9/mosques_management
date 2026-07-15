<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize mosque editing for administrators and editors. */
final class CanEditMosque extends RequireAdmin
{
    protected array $allowedRoles = ['admin', 'editor'];

    protected string $message = 'غير مصرح بتعديل المساجد';
}
