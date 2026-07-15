<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize mosque creation for administrators and editors. */
final class CanCreateMosque extends RequireAdmin
{
    protected array $allowedRoles = ['admin', 'editor'];

    protected string $message = 'غير مصرح بإضافة مساجد جديدة';
}
