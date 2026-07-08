<?php

declare(strict_types=1);

namespace kintai\Core\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Application;

final class MigrationRunner
{
    private Capsule $capsule;
    private string $migrationsPath;

    public function __construct(Application $app)
    {
        $this->capsule = $app->container()->make(Capsule::class);
        $this->migrationsPath = $app->basePath('database/migrations/php');
        
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0775, true);
        }
    }

    public function run(): int
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        $count = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $executed)) {
                continue;
            }

            $this->executeMigration($file, $name);
            $count++;
        }

        return $count;
    }

    private function ensureMigrationsTable(): void
    {
        $schema = $this->capsule->getConnection()->getSchemaBuilder();
        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration')->unique();
                $table->timestamp('executed_at')->useCurrent();
            });
        }
    }

    private function getExecutedMigrations(): array
    {
        return $this->capsule->table('migrations')->pluck('migration')->toArray();
    }

    private function executeMigration(string $file, string $name): void
    {
        try {
            $migrationClass = require $file;

            if (is_object($migrationClass) && $migrationClass instanceof Migration) {
                $migrationClass->up();
            } else {
                $migration = new $migrationClass($this->capsule);
                $migration->up();
            }
        } catch (\Throwable $e) {
            // Colonne/table déjà présente en base mais migration non trackée : on la considère comme déjà appliquée.
            if (!$this->isAlreadyAppliedError($e)) {
                throw new \RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }

        $this->capsule->table('migrations')->insert([
            'migration' => $name,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function isAlreadyAppliedError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate column')
            || str_contains($message, 'already exists')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');
    }
}
