<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * remember_tokens ("remember me") et sessions (session persistée en BD) n'ont jamais eu
 * la moindre implémentation (aucun modèle Eloquent, aucun repository, aucune lecture/
 * écriture nulle part) — les sessions sont natives PHP, fichier. Une nouvelle installation
 * ne doit plus créer ces deux tables mortes du tout.
 */
final class UnusedRememberTokensAndSessionsTablesDroppedTest extends TestCase
{
    public function testFreshInstallDoesNotHaveTheseTables(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(MigrationRunner::class))->newInstanceWithoutConstructor();
        $capsuleProp = new \ReflectionProperty($runner, 'capsule');
        $capsuleProp->setAccessible(true);
        $capsuleProp->setValue($runner, $capsule);
        $pathProp = new \ReflectionProperty($runner, 'migrationsPath');
        $pathProp->setAccessible(true);
        $pathProp->setValue($runner, dirname(__DIR__, 2) . '/database/migrations/php');

        $runner->run();

        $schema = $capsule->getConnection()->getSchemaBuilder();
        $this->assertFalse($schema->hasTable('remember_tokens'));
        $this->assertFalse($schema->hasTable('sessions'));
    }
}
