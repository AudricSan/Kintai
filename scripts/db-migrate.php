<?php

/**
 * Usage:
 *   php scripts/db-migrate.php              # applique les migrations en attente
 *   php scripts/db-migrate.php --dry-run    # liste les migrations en attente sans les exécuter
 */

declare(strict_types=1);

use kintai\Core\Application;
use kintai\Core\Database\MigrationRunner;

require __DIR__ . '/../vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application(dirname(__DIR__));
$app->boot();

$runner = new MigrationRunner($app);

if ($dryRun) {
    $pending = $runner->getPendingMigrations();
    if ($pending === []) {
        echo "No pending migrations.\n";
        exit(0);
    }
    echo count($pending) . " pending migration(s):\n";
    foreach ($pending as $name) {
        echo "  - {$name}\n";
    }
    exit(0);
}

$count = $runner->run();
echo "{$count} migration(s) applied.\n";
