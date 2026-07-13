<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * CSRF check that bounces back to the mosque list with a flashed error
 * on failure (legacy verify_csrf_token('mosques.php')).
 */
final class VerifyCsrfMosqueList extends VerifyCsrf
{
    protected function failureRedirect(): ?string
    {
        return 'mosques.php';
    }
}
