<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Chargeur .env — nécessaire sur OVH (FastCGI) où .htaccess SetEnv n'atteint pas PHP.
// Priorité : .env.local (dev) → .env.ovh (production OVH) → .env (générique).
// Les variables déjà présentes dans l'environnement système ne sont jamais écrasées.
(static function (): void {
    foreach (['.env.local', '.env.ovh', '.env'] as $name) {
        $file = BASE_PATH . '/' . $name;
        if (!file_exists($file)) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (strlen($v) >= 2 && $v[0] === $v[-1] && ($v[0] === '"' || $v[0] === "'")) {
                $v = substr($v, 1, -1);
            }
            if ($k !== '' && !isset($_ENV[$k])) {
                putenv("$k=$v");
                $_ENV[$k]    = $v;
                $_SERVER[$k] = $v;
            }
        }
        break; // un seul fichier chargé
    }
})();

// Check if the application is installed
$installedLockFile = BASE_PATH . '/storage/installed.lock';
if (!file_exists($installedLockFile)) {
    // If not installed, redirect to the installation script
    header('Location: /install.php');
    exit;
}

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$app = new kintai\Core\Application(BASE_PATH);
$app->boot();
$app->handleRequest();