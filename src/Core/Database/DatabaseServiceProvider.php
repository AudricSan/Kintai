<?php

declare(strict_types=1);

namespace kintai\Core\Database;

use kintai\Core\ServiceProvider;
use Illuminate\Database\Capsule\Manager as Capsule;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerEloquent();
    }

    private function registerEloquent(): void
    {
        $dbConfig = $this->loadConfig();
        $driver = $dbConfig['driver'] ?? 'sqlite';
        $connectionConfig = $dbConfig['connections'][$driver] ?? [];

        $capsule = new Capsule();

        $eloquentConfig = match ($driver) {
            'sqlite' => [
                'driver'   => 'sqlite',
                'database' => $connectionConfig['path'] ?? BASE_PATH . '/storage/app/database.sqlite',
                'prefix'   => '',
            ],
            'mysql' => [
                'driver'    => 'mysql',
                'host'      => $connectionConfig['host'] ?? '127.0.0.1',
                'port'      => $connectionConfig['port'] ?? 3306,
                'database'  => $connectionConfig['database'] ?? 'kintai',
                'username'  => $connectionConfig['username'] ?? 'root',
                'password'  => $connectionConfig['password'] ?? '',
                'charset'   => $connectionConfig['charset'] ?? 'utf8mb4',
                'collation' => $connectionConfig['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix'    => $connectionConfig['prefix'] ?? '',
            ],
            default => throw new \InvalidArgumentException(
                "Unsupported database driver: [{$driver}]. Supported: sqlite, mysql."
            ),
        };

        $capsule->addConnection($eloquentConfig);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->container->instance(Capsule::class, $capsule);
    }

    private function loadConfig(): array
    {
        $base   = dirname(dirname(dirname(__DIR__))) . '/config';
        $config = require $base . '/database.php';

        $localPath = $base . '/database.local.php';
        if (file_exists($localPath)) {
            $local = require $localPath;
            if (isset($local['driver'])) {
                $config['driver'] = $local['driver'];
            }
            if (isset($local['connections']) && is_array($local['connections'])) {
                foreach ($local['connections'] as $driver => $cfg) {
                    $config['connections'][$driver] = array_merge(
                        $config['connections'][$driver] ?? [],
                        $cfg
                    );
                }
            }
        }

        return $config;
    }
}
