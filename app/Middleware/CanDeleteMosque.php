<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy delete_mosque.php guard: canDeleteMosque() or 403. */
final class CanDeleteMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بحذف المساجد';
}
