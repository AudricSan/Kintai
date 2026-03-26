<?php

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
        // For simplicity, let's assume environment variables are set directly
        // or fetched from a $_ENV / $_SERVER superglobal in a real application.
        // For this example, we'll return a hardcoded value if not found in $_ENV.
        // In a production app, you'd typically load from a .env file.
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
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
        // This assumes that the 'storage' directory is at the project root.
        // In a real application, you might get the base path from the Application instance.
        // For now, we'll use a hardcoded relative path.
        $basePath = dirname(__DIR__, 2); // Go up two directories from src/Core
        return $basePath . '/storage' . ($path ? '/' . ltrim($path, '/') : '');
    }
}
