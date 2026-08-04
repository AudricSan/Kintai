<?php

declare(strict_types=1);

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null) ? $default : $value;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        // KINTAI_STORAGE_PATH permet aux tests d'isoler le dossier storage
        // (voir BackupServiceTest / UpdateServiceTest) sans toucher au
        // vrai storage/ de l'application.
        $storageDir = env('KINTAI_STORAGE_PATH');
        if (!$storageDir) {
            // This assumes that the 'storage' directory is at the project root.
            // In a real application, you might get the base path from the Application instance.
            $basePath   = dirname(__DIR__, 2); // Go up two directories from src/Core
            $storageDir = $basePath . '/storage';
        }
        return $storageDir . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('bundle_enabled')) {
    /**
     * Indique si un bundle est activé au niveau de l'instance (FeatureManager).
     * "Fail open" (true) si le service n'est pas encore prêt, comme __() —
     * l'accès réel reste de toute façon protégé par les routes elles-mêmes.
     */
    function bundle_enabled(string $slug): bool
    {
        try {
            $container = \kintai\Core\Container::getInstance();
            if ($container->has(\kintai\Core\FeatureManager::class)) {
                return $container->make(\kintai\Core\FeatureManager::class)->isEnabled($slug);
            }
        } catch (\Throwable $e) {
            // En cas d'erreur avant que le service soit prêt
        }
        return true;
    }
}

if (!function_exists('feat_bundle')) {
    /**
     * Pour un slug de feature par-store (ex: 'timeoff', 'swaps'), indique si le
     * bundle Core dont il dépend est activé au niveau de l'instance. Retourne
     * true si le slug ne correspond à aucun bundle connu (fonctionnalité Core,
     * toujours active) ou si $slug est null. À combiner avec le toggle par
     * store (ex: $feat() dans les vues) qui ne couvre pas ce niveau.
     */
    function feat_bundle(?string $slug): bool
    {
        if ($slug === null) {
            return true;
        }
        $bundle = \kintai\Core\FeatureManager::STORE_FEATURE_BUNDLE_MAP[$slug] ?? null;
        return $bundle === null || bundle_enabled($bundle);
    }
}

if (!function_exists('hash_token')) {
    function hash_token(string $raw): string
    {
        return hash('sha256', $raw);
    }
}

if (!function_exists('render_markdown')) {
    /**
     * Rend du Markdown en HTML (Parsedown, safe mode) pour l'affichage de
     * contenu provenant d'une source externe (ex : notes de version GitHub) —
     * contrairement à WikiContentService::render(), qui désactive le safe mode
     * car les pages du wiki sont des fichiers locaux de confiance.
     */
    function render_markdown(string $markdown): string
    {
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);

        return $parsedown->text($markdown);
    }
}

if (!function_exists('base_url')) {
    /**
     * Calcule la base URL à partir de SCRIPT_NAME.
     * Exemples :
     *   DocumentRoot = public/         → SCRIPT_NAME=/index.php           → ''
     *   DocumentRoot = htdocs/         → SCRIPT_NAME=/Kintai/public/index.php → /Kintai/public
     */
    function base_url(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }

    /**
     * Génère une URL à partir d'un nom de route et de paramètres.
     * Utilise le Router pour résoudre le pattern de la route nommée.
     */
    function route_url(string $name, array $params = []): string
    {
        $router = \kintai\Core\Container::getInstance()->make(\kintai\Core\Router::class);
        return base_url() . $router->url($name, $params);
    }
}
