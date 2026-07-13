<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize mosque editing for administrators. */
final class CanEditMosque extends RequireAdmin
{
    protected string $message = 'غير مصرح بتعديل المساجد';
}
