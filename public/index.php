<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';
// Fichier .env (.env.local → .env.ovh → .env), voir kintai_load_env_file().
kintai_load_env_file(BASE_PATH);
kintai_normalize_script_name();

// Bundles installés dynamiquement (storage/bundles/) : hors PSR-4 standard,
// voir kintai\Core\InstalledBundleAutoloader.
(new kintai\Core\InstalledBundleAutoloader())->register();

// Check if the application is installed
$installedLockFile = BASE_PATH . '/storage/installed.lock';
if (!file_exists($installedLockFile)) {
    if (kintai_installed_via_database(BASE_PATH)) {
        // Le marqueur a disparu (hors Git, cause externe non identifiée — voir CHANGELOG)
        // alors que la base de données est bien installée : on le régénère au lieu de
        // forcer un réinstall destructeur à chaque requête.
        @file_put_contents($installedLockFile, bin2hex(random_bytes(32)));
    } else {
        // Réellement pas installé → rediriger vers l'installeur
        header('Location: ' . base_url() . '/install.php');
        exit;
    }
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$app = new kintai\Core\Application(BASE_PATH);
$app->boot();
$app->handleRequest();