<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Services\AppResetService;
use PHPUnit\Framework\TestCase;

final class AppResetServiceTest extends TestCase
{
    private function makeCapsule(): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->getConnection()->getSchemaBuilder();

        $schema->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration')->unique();
            $table->timestamp('executed_at')->useCurrent();
        });
        $schema->create('roles', function ($table) {
            $table->increments('id');
            $table->string('slug');
        });
        $schema->create('users', function ($table) {
            $table->increments('id');
        });
        $schema->create('shifts', function ($table) {
            $table->increments('id');
        });

        $capsule->table('migrations')->insert([
            ['migration' => '2026_07_01_000000_create_shifts_table', 'executed_at' => date('Y-m-d H:i:s')],
            ['migration' => '2026_07_14_000003_seed_default_roles', 'executed_at' => date('Y-m-d H:i:s')],
        ]);
        $capsule->table('roles')->insert(['slug' => 'owner']);
        $capsule->table('users')->insert([]);
        $capsule->table('shifts')->insert([]);

        return $capsule;
    }

    /**
     * Régression : après resetFactory() + réinstallation, la table roles restait
     * vide pour toujours car sa migration de seed était trackée comme déjà
     * appliquée (table migrations préservée) alors que ses données avaient été
     * vidées — sa garde d'idempotence bloquait tout reseed.
     */
    public function testResetFactoryAllowsSeedDefaultRolesMigrationToReplay(): void
    {
        $capsule = $this->makeCapsule();
        $service = new AppResetService($capsule);

        $service->resetFactory();

        $this->assertSame(
            ['2026_07_01_000000_create_shifts_table'],
            $capsule->table('migrations')->pluck('migration')->toArray(),
        );
        $this->assertSame(0, $capsule->table('roles')->count());
    }

    public function testResetFactoryClearsOperationalTables(): void
    {
        $capsule = $this->makeCapsule();
        $service = new AppResetService($capsule);

        $wiped = $service->resetFactory();

        $this->assertContains('users', $wiped);
        $this->assertContains('shifts', $wiped);
        $this->assertSame(0, $capsule->table('users')->count());
        $this->assertSame(0, $capsule->table('shifts')->count());
    }
}
