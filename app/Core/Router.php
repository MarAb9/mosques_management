<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * Route table keyed by HTTP method + script-relative path.
 *
 * Paths are the legacy public URLs ("mosques.php", "ajax/search_mosques.php")
 * so every existing URL keeps working, whether it arrives through a physical
 * shim file or through the public/index.php front controller.
 */
final class Router
{
    /**
     * @var array<string, array<string, array{action: array{class-string, string}, middleware: list<class-string>}>>
     */
    private array $routes = [];

    /** @var array<string, string> path alias => canonical path */
    private array $aliases = [];

    /**
     * @param array{class-string, string} $action [ControllerClass, method]
     * @param list<class-string> $middleware
     */
    public function add(string $method, string $path, array $action, array $middleware = []): void
    {
        $this->routes[strtoupper($method)][$this->normalize($path)] = [
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param array{class-string, string} $action
     * @param list<class-string> $middleware
     */
    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
        $this->add('HEAD', $path, $action, $middleware);
    }

    /**
     * @param array{class-string, string} $action
     * @param list<class-string> $middleware
     */
    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    /** Register an alternate URL that dispatches to an existing path. */
    public function alias(string $alias, string $target): void
    {
        $this->aliases[$this->normalize($alias)] = $this->normalize($target);
    }

    /**
     * @return array{action: array{class-string, string}, middleware: list<class-string>}
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $path = $this->normalize($path);
        $path = $this->aliases[$path] ?? $path;

        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            if (isset($this->routes['GET'][$path]) || isset($this->routes['POST'][$path])) {
                throw new HttpException(405, 'طلب غير صالح');
            }

            throw new HttpException(404, 'الصفحة غير موجودة');
        }

        return $route;
    }

    public function has(string $path): bool
    {
        $path = $this->normalize($path);
        $path = $this->aliases[$path] ?? $path;

        foreach ($this->routes as $byPath) {
            if (isset($byPath[$path])) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
