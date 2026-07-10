<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;
use Throwable;

/**
 * Application kernel and minimal service container.
 *
 * Boots configuration, error handling, and the session; resolves
 * controllers/middleware with constructor injection of core services;
 * runs the middleware pipeline and sends the response.
 */
final class App
{
    private static ?self $instance = null;

    public readonly Config $config;
    public readonly Router $router;
    public readonly Session $session;
    public readonly View $view;
    public readonly Database $database;
    public readonly ErrorHandler $errors;

    /** @var array<class-string, object> */
    private array $resolved = [];

    private function __construct(public readonly string $basePath)
    {
        $this->config = new Config($basePath . '/config');
        $this->errors = new ErrorHandler(
            (bool) $this->config->get('app.debug', false),
            $basePath . '/storage/logs/app.log'
        );
        $this->session = new Session();
        $this->database = new Database($this->config);
        $this->view = new View($basePath . '/resources/views');
        $this->router = new Router();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app = new self($basePath);
        self::$instance = $app;

        $app->errors->register();
        $app->session->start();
        $app->loadLegacyHelpers();

        $routes = require $basePath . '/routes/web.php';
        $routes($app->router);

        return $app;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been booted.');
        }

        return self::$instance;
    }

    /**
     * Handle the current HTTP request (or an explicit legacy route path,
     * as passed by the physical shim files) and send the response.
     */
    public function handle(?string $routePath = null): void
    {
        $request = Request::capture();

        try {
            $path = $routePath ?? $request->routePath();
            $route = $this->router->match($request->method(), $path);

            $pipeline = $this->buildPipeline($route['middleware'], function (Request $req) use ($route): Response {
                [$class, $method] = $route['action'];
                $controller = $this->make($class);

                return $controller->{$method}($req);
            });

            $response = $pipeline($request);
        } catch (HttpException $e) {
            $response = $this->errors->handleException($e);
        } catch (Throwable $e) {
            $response = $this->errors->handleException($e);
        }

        $response->send();
    }

    /**
     * Resolve a class, injecting known core services by constructor
     * parameter type. Repositories/services with class-typed parameters
     * are resolved recursively.
     */
    public function make(string $class): object
    {
        if (isset($this->resolved[$class])) {
            return $this->resolved[$class];
        }

        $wellKnown = [
            Config::class => $this->config,
            Router::class => $this->router,
            Session::class => $this->session,
            View::class => $this->view,
            Database::class => $this->database,
            ErrorHandler::class => $this->errors,
            self::class => $this,
        ];

        if (isset($wellKnown[$class])) {
            return $wellKnown[$class];
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $args = [];

        foreach ($constructor?->getParameters() ?? [] as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot resolve parameter \${$param->getName()} of {$class}"
                );
            }
        }

        $instance = $reflection->newInstanceArgs($args);

        return $this->resolved[$class] = $instance;
    }

    /**
     * @param list<class-string> $middleware
     */
    private function buildPipeline(array $middleware, Closure $destination): Closure
    {
        $pipeline = $destination;

        foreach (array_reverse($middleware) as $class) {
            $next = $pipeline;
            $pipeline = function (Request $request) use ($class, $next): Response {
                /** @var \App\Middleware\MiddlewareInterface $instance */
                $instance = $this->make($class);

                return $instance->handle($request, $next);
            };
        }

        return $pipeline;
    }

    /**
     * Load the legacy function helpers (single source of truth during the
     * migration). Legacy pages load the same files through
     * includes/config.php; require_once makes both paths safe.
     */
    private function loadLegacyHelpers(): void
    {
        require_once $this->basePath . '/includes/csrf.php';
        require_once $this->basePath . '/includes/flash.php';
        require_once $this->basePath . '/includes/redirect.php';
        require_once $this->basePath . '/includes/helpers.php';
    }
}
