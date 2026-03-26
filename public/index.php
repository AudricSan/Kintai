<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Check if the application is installed
$installedLockFile = BASE_PATH . '/storage/installed.lock';
if (!file_exists($installedLockFile)) {
    // If not installed, redirect to the installation script
    header('Location: /install.php');
    exit;
}

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

$app = new kintai\Core\Application(BASE_PATH);
$app->boot();
$app->handleRequest();