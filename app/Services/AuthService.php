<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\UserRepository;

/**
 * Authentication business logic.
 *
 * Mirrors the legacy includes/auth.php flow exactly: password_verify,
 * session ID regeneration on success, and the same session keys.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Session $session,
    ) {
    }

    /**
     * Attempt to log a user in. Returns true on success.
     */
    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->session->set('username', $user['username']);
        $this->session->set('role', $user['role']);
        $this->session->set('full_name', $user['full_name']);

        return true;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }
}
