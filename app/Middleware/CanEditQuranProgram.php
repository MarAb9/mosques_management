<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy edit_quran_mosque.php guard. */
final class CanEditQuranProgram extends RequireAdmin
{
    protected array $allowedRoles = ['admin', 'editor'];

    protected string $message = ' غير مصرح بتعديل مساجد التحفيظ';
}
