<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize mosque deletion for administrators. */
final class CanDeleteMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بحذف المساجد';
}
