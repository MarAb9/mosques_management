<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy add_quran_mosque.php guard. */
final class CanCreateQuranProgram extends RequireAdmin
{
    protected array $allowedRoles = ['admin', 'editor'];

    protected string $message = 'غير مصرح باضافة مساجد التحفيظ';
}
