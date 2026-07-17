<?php

declare(strict_types=1);

namespace kintai\Core;

use kintai\Core\Exceptions\HttpException;
use kintai\Core\Exceptions\MethodNotAllowedException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Middleware\MiddlewarePipeline;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\UI\ViewRenderer;
use kintai\Core\Services\Log;
use kintai\Core\Services\TranslationService;
use Throwable;

final class Application
{
    private readonly Container $container;
    private readonly Router $router;
    private readonly MiddlewarePipeline $pipeline;
    private readonly BundleManager $bundleManager;
    private readonly string $basePath;

    /** @var string[] Global middleware applied to every request */
    private array $globalMiddleware = [];

    private bool $debug = false;

    /** @var string[] List of registered service providers */
    private array $providers = [
        \kintai\Core\Database\DatabaseServiceProvider::class,
        \kintai\Core\Repositories\RepositoryServiceProvider::class,
        \kintai\Core\Auth\AuthServiceProvider::class,
        \kintai\Core\Services\AppServiceProvider::class,
        \kintai\Core\LicenseServiceProvider::class,
        \kintai\Core\BundleServiceProvider::class,
    ];

    /** @var ServiceProvider[] Instances of booted providers */
    private array $providerInstances = [];

    public function __construct(string $basePath)
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', rtrim($basePath, '/\\'));
        }
        $this->basePath = BASE_PATH;
        $this->container = Container::getInstance();
        $this->router = new Router();
        $this->pipeline = new MiddlewarePipeline($this->container);
        $this->bundleManager = new BundleManager($this);

        // Register core instances
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(MiddlewarePipeline::class, $this->pipeline);
        $this->container->instance(BundleManager::class, $this->bundleManager);

        // Register ViewRenderer
        $this->container->singleton(ViewRenderer::class, fn() => new ViewRenderer(
            $this->basePath . '/src/UI/View'
        ));

        // Register TranslationService
        $this->container->singleton(TranslationService::class, fn() => new TranslationService(
            $this->container->make(TranslationRepositoryInterface::class),
            $this->container->make(LanguageRepositoryInterface::class),
        ));

        $this->registerProviders();
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Load configuration, routes, then boot providers.
     */
    public function boot(): void
    {
        $this->loadConfig();
        $this->loadRoutes();
        $this->bootProviders();
        $this->bundleManager->boot();

        // Handler PHP : convertir les erreurs PHP en exceptions (uniquement en debug)
        if ($this->isDebug()) {
            set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });
        }

        // Handler shutdown : capture les fatales non catchées
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->logError(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    public function handleRequest(): void
    {
        $request = new Request();
        $this->container->instance(Request::class, $request);

        try {
            $response = $this->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->handleException($e, $request);
        }

        $response->send();
    }

    private function dispatch(Request $request): Response
    {
        [$route, $params] = $this->router->dispatch($request->method(), $request->uri());

        $request->setRouteParams($params);
        // Nom de la route matchée, exploité par PermissionMiddleware (config/permissions.php)
        $request->setAttribute('route_name', $route->name);

        // Merge global + route-specific middleware
        $allMiddleware = array_merge($this->globalMiddleware, $route->middleware);

        // Core handler: resolve controller, call method
        $core = function (Request $request) use ($route) {
            [$controllerClass, $method] = $route->handler;
            $controller = $this->container->make($controllerClass);
            return $controller->$method($request);
        };

        return $this->pipeline->run($request, $allMiddleware, $core);
    }

    private function handleException(Throwable $e, Request $request): Response
    {
        $wantsJson = $request->wantsJson() || $request->getAttribute('wantsJson', false);

        if ($e instanceof ValidationException) {
            return $wantsJson
                ? Response::json(['error' => $e->getMessage(), 'errors' => $e->errors], 422)
                : $this->renderError(422, $e->getMessage(), $request);
        }

        if ($e instanceof MethodNotAllowedException) {
            $response = $wantsJson
                ? Response::json(['error' => $e->getMessage()], 405)
                : $this->renderError(405, $e->getMessage(), $request);
            return $response->withHeader('Allow', implode(', ', $e->allowedMethods));
        }

        if ($e instanceof HttpException) {
            return $wantsJson
                ? Response::json(['error' => $e->getMessage()], $e->statusCode)
                : $this->renderError($e->statusCode, $e->getMessage(), $request);
        }

        // Unexpected error
        $this->logError($e);

        return $wantsJson
            ? Response::json(['error' => 'Internal Server Error'], 500)
            : $this->renderError(500, 'Internal Server Error', $request);
    }

    private function renderError(int $status, string $message, Request $request): Response
    {
        try {
            $view = $this->container->make(ViewRenderer::class);
            $html = $view->render("errors.{$status}", [
                'status' => $status,
                'message' => $message,
            ]);
            return Response::html($html, $status);
        } catch (Throwable) {
            // Fallback if view doesn't exist
            return Response::html(
                "<h1>{$status}</h1><p>" . htmlspecialchars($message) . "</p>",
                $status,
            );
        }
    }

    private function logError(Throwable $e): void
    {
        $logDir = $this->basePath . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );

        @file_put_contents($logDir . '/error.log', $entry, FILE_APPEND | LOCK_EX);

        // Également logger en base via le système unifié
        try {
            Log::error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } catch (\Throwable) {
            // Le logging ne doit jamais faire planter l'application
        }
    }

    private function loadConfig(): void
    {
        $this->container->make(ViewRenderer::class)->share('BASE_URL', base_url());

        $configFile = $this->basePath . '/config/app.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (isset($config['debug']) && $config['debug']) {
                $this->debug = true;
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
            } else {
                error_reporting(0);
                ini_set('display_errors', '0');
            }
            if (isset($config['timezone'])) {
                date_default_timezone_set($config['timezone']);
            }
        }

        // Load middleware config
        $middlewareFile = $this->basePath . '/config/middleware.php';
        if (file_exists($middlewareFile)) {
            $middleware = require $middlewareFile;
            $this->globalMiddleware = $middleware['global'] ?? [];
        }
    }

    private function loadRoutes(): void
    {
        $routesFile = $this->basePath . '/config/routes.php';
        if (file_exists($routesFile)) {
            $router = $this->router;
            $container = $this->container;
            require $routesFile;
        }
    }

    private function registerProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass($this->container);
            $provider->register();
            $this->providerInstances[] = $provider;
        }
    }

    private function bootProviders(): void
    {
        foreach ($this->providerInstances as $provider) {
            $provider->boot();
        }
    }
}
