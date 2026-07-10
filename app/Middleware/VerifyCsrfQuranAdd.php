<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * CSRF check for the Quran add form
 * (legacy verify_csrf_token('add_quran_mosque.php')).
 */
final class VerifyCsrfQuranAdd extends VerifyCsrf
{
    protected function failureRedirect(): ?string
    {
        return 'add_quran_mosque.php';
    }
}
