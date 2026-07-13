<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy delete_quran_mosque.php guard. */
final class CanDeleteQuranProgram extends RequireAdmin
{
    protected string $message = 'غير مصرح بحذف مساجد التحفيظ';
}
