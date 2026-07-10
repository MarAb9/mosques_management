<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session abstraction.
 *
 * Starts the session with exactly the same cookie policy as the legacy
 * includes/config.php bootstrap (lifetime 0, path /, secure on HTTPS,
 * httponly, SameSite=Lax) and uses the same session keys, so sessions
 * created by legacy pages remain valid on migrated pages and vice versa.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    // ── Auth state (same keys as legacy) ────────────────────────────────

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    // ── Flash messages (same keys as legacy: 'success' / 'error') ───────

    public function flash(string $type, string $message): void
    {
        $_SESSION[$type] = $message;
    }

    public function pullFlash(string $type): ?string
    {
        $message = $_SESSION[$type] ?? null;
        unset($_SESSION[$type]);

        return $message === null ? null : (string) $message;
    }

    // ── CSRF (same key as legacy includes/csrf.php) ──────────────────────

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}
