<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * CSRF check that bounces back to the Quran list with a flashed error
 * on failure (legacy verify_csrf_token('quran_mosques.php')).
 */
final class VerifyCsrfQuranList extends VerifyCsrf
{
    protected function failureRedirect(): ?string
    {
        return 'quran_mosques.php';
    }
}
