<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy edit_mosque.php guard: canEditMosque() or 403. */
final class CanEditMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بتعديل المساجد';
}
