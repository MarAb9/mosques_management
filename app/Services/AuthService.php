<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\UserRepository;

/**
 * Authentication business logic.
 *
 * Verifies credentials, regenerates the session ID, and stores auth state.
 */
final class AuthService
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$hegqhcN9sN1yTL8Q9K0K/eW1UWs2vq1bJZevSr52B5tmAlnEaxUrC';

    public function __construct(
        private readonly UserRepository $users,
        private readonly Session $session,
        private readonly Config $config,
        private readonly LoginThrottle $throttle,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Attempt to log a user in. Returns true on success.
     */
    public function attempt(string $username, string $password, Request $request): bool
    {
        if ($this->throttle->tooManyAttempts($username, $request->clientIp())) {
            $this->audit->record('authentication.login', 'blocked', $request, [
                'attempted_username' => $username,
                'reason' => 'rate_limited',
            ]);
            return false;
        }

        $user = $this->users->findByUsername($username);
        $hash = (string) ($user['password'] ?? self::DUMMY_PASSWORD_HASH);
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid) {
            $this->throttle->hit($username, $request->clientIp());
            $this->audit->record('authentication.login', 'failed', $request, [
                'attempted_username' => $username,
                'reason' => 'invalid_credentials',
            ]);
            return false;
        }

        $blockedPasswords = (array) $this->config->get('security.login.blocked_production_passwords', []);
        if ($this->config->get('app.env') === 'production' && in_array($password, $blockedPasswords, true)) {
            $this->throttle->hit($username, $request->clientIp());
            $this->audit->record('authentication.login', 'blocked', $request, [
                'attempted_username' => $username,
                'reason' => 'default_password_forbidden',
            ]);
            return false;
        }

        $this->throttle->clear($username, $request->clientIp());
        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->session->set('username', $user['username']);
        $this->session->set('role', $user['role']);
        $this->session->set('full_name', $user['full_name']);
        $this->audit->record('authentication.login', 'success', $request);

        return true;
    }

    public function logout(Request $request): void
    {
        $this->audit->record('authentication.logout', 'success', $request);
        $this->session->destroy();
    }
}
