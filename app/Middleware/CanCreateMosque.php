<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize mosque creation for administrators. */
final class CanCreateMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بإضافة مساجد جديدة';
}
