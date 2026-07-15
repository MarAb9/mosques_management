<?php

declare(strict_types=1);

namespace App\Middleware;

/** Authorize import/export operations for administrators and import-only users. */
final class CanUseImportExport extends RequireAdmin
{
    protected array $allowedRoles = ['admin', 'importer'];

    protected string $message = 'غير مصرح بالوصول إلى الاستيراد والتصدير';
}