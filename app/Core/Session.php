<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session abstraction.
 *
 * Starts the session with hardened cookie settings: lifetime 0, path /,
 * secure on HTTPS, HttpOnly, and SameSite=Lax.
 */
final class Session
{
    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $isHttps = $this->requestIsSecure();
        session_name((string) $this->config->get('security.session.name', 'mosques_session'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->enforceLifetime();
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
        $_SESSION['_last_regenerated_at'] = time();
    }

    public function destroy(): void
    {
        session_unset();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

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

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function canEditContent(): bool
    {
        return $this->hasRole('admin', 'editor');
    }

    public function canDeleteContent(): bool
    {
        return $this->hasRole('admin');
    }

    public function canImportData(): bool
    {
        return $this->hasRole('admin', 'importer');
    }

    public function canViewAudit(): bool
    {
        return $this->hasRole('admin');
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

    private function enforceLifetime(): void
    {
        $now = time();
        $createdAt = (int) ($_SESSION['_created_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['_last_activity_at'] ?? $now);
        $lastRegenerated = (int) ($_SESSION['_last_regenerated_at'] ?? $now);
        $idleTimeout = max(60, (int) $this->config->get('security.session.idle_timeout', 1800));
        $absoluteTimeout = max($idleTimeout, (int) $this->config->get('security.session.absolute_timeout', 28800));
        $regenerateInterval = max(60, (int) $this->config->get('security.session.regenerate_interval', 900));

        if (($now - $lastActivity) > $idleTimeout || ($now - $createdAt) > $absoluteTimeout) {
            $_SESSION = [];
            session_regenerate_id(true);
            $createdAt = $now;
            $lastRegenerated = $now;
        } elseif (($now - $lastRegenerated) > $regenerateInterval) {
            session_regenerate_id(true);
            $lastRegenerated = $now;
        }

        $_SESSION['_created_at'] = $createdAt;
        $_SESSION['_last_activity_at'] = $now;
        $_SESSION['_last_regenerated_at'] = $lastRegenerated;
    }

    private function requestIsSecure(): bool
    {
        $directHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        if ($directHttps) {
            return true;
        }

        if (!(bool) $this->config->get('security.trust_proxy_headers', false)) {
            return false;
        }

        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        return $forwardedProto === 'https';
    }

    // ── CSRF ─────────────────────────────────────────────────────────────

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}
