<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP request abstraction over PHP superglobals.
 *
 * Controllers/services must use this instead of touching $_GET/$_POST/$_FILES
 * directly. Values are returned unmodified so behavior matches legacy code.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    /** GET value first, then POST (legacy $_REQUEST-style lookups). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->post[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function clientIp(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? 'unknown');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 512);
    }

    public function isSecure(bool $trustProxyHeaders = false): bool
    {
        if ((!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443)
        ) {
            return true;
        }

        if (!$trustProxyHeaders) {
            return false;
        }

        $forwardedProto = strtolower(trim(explode(',', (string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        return $forwardedProto === 'https';
    }

    /**
     * Route key for the router: the requested script path relative to the
     * web root, e.g. "mosques.php" or "ajax/search_mosques.php".
     */
    public function routePath(): string
    {
        $script = (string) ($this->server['SCRIPT_NAME'] ?? '');
        $base = basename($script);

        if ($base !== '' && $base !== 'index.php') {
            // Legacy physical-file shim: use the script's own path.
            return ltrim($this->stripWebRoot($script), '/');
        }

        // Front-controller mode: derive from the request URI path.
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $path = ltrim($this->stripWebRoot($path), '/');

        return $path === '' ? 'index.php' : $path;
    }

    private function stripWebRoot(string $path): string
    {
        // Remove the directory portion of SCRIPT_NAME (app may live in a
        // subdirectory on shared hosting).
        $scriptDir = str_replace('\\', '/', dirname((string) ($this->server['SCRIPT_NAME'] ?? '')));
        if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return $path;
    }
}
