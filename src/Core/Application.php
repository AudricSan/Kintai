<?php

declare(strict_types=1);

namespace kintai\Core;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\HttpException;
use kintai\Core\Exceptions\MethodNotAllowedException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Middleware\MiddlewarePipeline;
use kintai\UI\ViewRenderer;
use Throwable;

use kintai\Core\Database\DriverFactory;
use kintai\Core\Database\PersistenceDriverInterface;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\DatabaseAvailabilityRepository;
use kintai\Core\Repositories\DatabaseShiftRepository;
use kintai\Core\Repositories\DatabaseShiftSwapRequestRepository;
use kintai\Core\Repositories\DatabaseShiftTypeRepository;
use kintai\Core\Repositories\DatabaseStoreRepository;
use kintai\Core\Repositories\DatabaseStoreUserRepository;
use kintai\Core\Repositories\DatabaseTimeoffRequestRepository;
use kintai\Core\Repositories\DatabaseUserRepository;
use kintai\Core\Repositories\DatabaseAuditLogRepository;
use kintai\Core\Repositories\DatabaseUserShiftTypeRateRepository;
use kintai\Core\Repositories\DatabaseFeedbackRepository;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\AuditLogRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\TranslationService;

final class Application
{
    private readonly Container $container;
    private readonly Router $router;
    private readonly MiddlewarePipeline $pipeline;
    private readonly string $basePath;

    /** @var string[] Global middleware applied to every request */
    private array $globalMiddleware = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = Container::getInstance();
        $this->router = new Router();
        $this->pipeline = new MiddlewarePipeline($this->container);

        // Register core instances
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(MiddlewarePipeline::class, $this->pipeline);

        // Register ViewRenderer
        $this->container->singleton(ViewRenderer::class, fn() => new ViewRenderer(
            $this->basePath . '/src/UI/View'
        ));

        // Register database services
        $this->registerDatabaseServices();

        // Register TranslationService
        $this->container->singleton(TranslationService::class, fn() => new TranslationService(
            $this->basePath . '/lang'
        ));
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

    /**
     * Load configuration, routes, then dispatch the request.
     */
    public function boot(): void
    {
        $this->loadConfig();
        $this->loadRoutes();
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
    }

    private function loadConfig(): void
    {
        // BASE_URL — calculé en premier, avant de lire config/app.php.
        // Règle : on supprime le nom du script (index.php) de SCRIPT_NAME.
        //   vhost  (DocumentRoot = public/)          → SCRIPT_NAME=/index.php         → BASE_URL=''
        //   htdocs (DocumentRoot = htdocs/)          → SCRIPT_NAME=/MyShift/public/index.php → BASE_URL=/MyShift/public
        $sn      = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $baseUrl = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        if ($baseUrl === '.' || $baseUrl === '/') {
            $baseUrl = '';
        }
        $this->container->make(ViewRenderer::class)->share('BASE_URL', $baseUrl);

        $configFile = $this->basePath . '/config/app.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (isset($config['debug']) && $config['debug']) {
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

    private function registerDatabaseServices(): void
    {
        $databaseConfigPath = $this->basePath . '/config/database.php';
        if (!file_exists($databaseConfigPath)) {
            throw new \RuntimeException('Database configuration file not found.');
        }
        $dbConfig = require $databaseConfigPath;

        $driver           = $dbConfig['driver'] ?? 'json';
        $connectionConfig = $dbConfig['connections'][$driver] ?? [];

        // DriverFactory is the single point of knowledge about concrete driver classes.
        $this->container->instance(DriverFactory::class, new DriverFactory());

        // PersistenceDriverInterface: resolved once, connected, and disconnected on shutdown.
        $this->container->singleton(
            PersistenceDriverInterface::class,
            function (Container $c) use ($driver, $connectionConfig): PersistenceDriverInterface {
                $persistence = $c->make(DriverFactory::class)->create($driver);
                $persistence->connect($connectionConfig);

                // Critical for JsonDriver: ensures data is flushed to disk at process end.
                register_shutdown_function([$persistence, 'disconnect']);

                return $persistence;
            }
        );

        // Chaque repository dépend de PersistenceDriverInterface, jamais d'un driver concret.
        $driver = fn(Container $c) => $c->make(PersistenceDriverInterface::class);

        $this->container->singleton(
            UserRepositoryInterface::class,
            fn(Container $c) => new DatabaseUserRepository($driver($c))
        );

        $this->container->singleton(
            StoreRepositoryInterface::class,
            fn(Container $c) => new DatabaseStoreRepository($driver($c))
        );

        $this->container->singleton(
            StoreUserRepositoryInterface::class,
            fn(Container $c) => new DatabaseStoreUserRepository($driver($c))
        );

        $this->container->singleton(
            ShiftTypeRepositoryInterface::class,
            fn(Container $c) => new DatabaseShiftTypeRepository($driver($c))
        );

        $this->container->singleton(
            ShiftRepositoryInterface::class,
            fn(Container $c) => new DatabaseShiftRepository($driver($c))
        );

        $this->container->singleton(
            AvailabilityRepositoryInterface::class,
            fn(Container $c) => new DatabaseAvailabilityRepository($driver($c))
        );

        $this->container->singleton(
            TimeoffRequestRepositoryInterface::class,
            fn(Container $c) => new DatabaseTimeoffRequestRepository($driver($c))
        );

        $this->container->singleton(
            ShiftSwapRequestRepositoryInterface::class,
            fn(Container $c) => new DatabaseShiftSwapRequestRepository($driver($c))
        );

        $this->container->singleton(
            UserShiftTypeRateRepositoryInterface::class,
            fn(Container $c) => new DatabaseUserShiftTypeRateRepository($driver($c))
        );

        $this->container->singleton(
            AuthService::class,
            fn(Container $c) => new AuthService(
                $c->make(UserRepositoryInterface::class),
                $c->make(StoreUserRepositoryInterface::class),
                $c->make(StoreRepositoryInterface::class),
            )
        );

        $this->container->singleton(
            AuditLogRepositoryInterface::class,
            fn(Container $c) => new DatabaseAuditLogRepository($driver($c))
        );

        $this->container->singleton(
            AuditLogger::class,
            fn(Container $c) => new AuditLogger($c->make(AuditLogRepositoryInterface::class))
        );

        $this->container->singleton(
            FeedbackRepositoryInterface::class,
            fn(Container $c) => new DatabaseFeedbackRepository($driver($c))
        );
    }
}
