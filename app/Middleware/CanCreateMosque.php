<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy add_mosque.php guard: canCreateMosque() or 403. */
final class CanCreateMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بإضافة مساجد جديدة';
}
