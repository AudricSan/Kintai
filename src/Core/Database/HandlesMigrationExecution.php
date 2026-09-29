<?php

declare(strict_types=1);

namespace kintai\Core\Database;

/**
 * Exécution d'un fichier de migration (format `return new class($this->capsule)
 * extends Migration {...}`, voir Migration::class) partagée entre MigrationRunner
 * (Core, table `migrations`) et BundleMigrationRunner (bundles distribués, table
 * `bundle_migrations`) — seules la table de tracking et son scope diffèrent entre
 * les deux, cette logique de require/up()/tolérance aux erreurs "déjà appliqué"
 * est strictement identique. Suppose une propriété `private Capsule $capsule`
 * sur la classe hôte : `$this->capsule` doit s'y résoudre depuis le fichier requis.
 */
trait HandlesMigrationExecution
{
    private function runMigrationFile(string $file, string $name): void
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
