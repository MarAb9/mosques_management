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
     * @return array{action: array{class-string, string}, middleware: list<class-string>, parameters: array<string, string>}
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $path = $this->normalize($path);
        $path = $this->aliases[$path] ?? $path;

        $route = $this->routes[$method][$path] ?? null;
        $parameters = [];

        if ($route === null) {
            [$route, $parameters] = $this->matchDynamic($method, $path);
        }

        if ($route === null) {
            foreach (array_keys($this->routes) as $registeredMethod) {
                if (isset($this->routes[$registeredMethod][$path])
                    || $this->matchDynamic($registeredMethod, $path)[0] !== null
                ) {
                    throw new HttpException(405, 'طريقة الطلب غير مسموحة');
                }
            }

            throw new HttpException(404, 'الصفحة غير موجودة');
        }

        return $route + ['parameters' => $parameters];
    }

    public function has(string $path): bool
    {
        $path = $this->normalize($path);
        $path = $this->aliases[$path] ?? $path;

        foreach ($this->routes as $byPath) {
            foreach ($byPath as $registeredPath => $_route) {
                if ($registeredPath === $path || $this->pathParameters($registeredPath, $path) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }

    /** @return array{0: array{action: array{class-string, string}, middleware: list<class-string>}|null, 1: array<string, string>} */
    private function matchDynamic(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $registeredPath => $route) {
            $parameters = $this->pathParameters($registeredPath, $path);
            if ($parameters !== null) {
                return [$route, $parameters];
            }
        }

        return [null, []];
    }

    /** @return array<string, string>|null */
    private function pathParameters(string $registeredPath, string $path): ?array
    {
        if (!str_contains($registeredPath, '{')) {
            return null;
        }

        $names = [];
        $parts = array_map(function (string $part) use (&$names): string {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $match) === 1) {
                $names[] = $match[1];

                return '([^/]+)';
            }

            return preg_quote($part, '#');
        }, explode('/', $registeredPath));

        if (preg_match('#^' . implode('/', $parts) . '$#u', $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[$name] = rawurldecode($matches[$index + 1]);
        }

        return $parameters;
    }
}
