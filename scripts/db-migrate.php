<?php

declare(strict_types=1);

use kintai\Core\Application;
use kintai\Core\Database\MigrationRunner;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$app->boot();

$runner = new MigrationRunner($app);
$runner->run();
