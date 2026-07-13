<?php

declare(strict_types=1);

namespace App\Middleware;

/** Legacy bulk_delete_mosques.php guard. */
final class CanBulkDeleteMosques extends RequireAdmin
{
    protected string $message = 'غير مصرح بالحذف الجماعي';
}
