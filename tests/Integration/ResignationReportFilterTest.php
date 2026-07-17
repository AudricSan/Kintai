<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Repositories\DatabaseResignationReportRepository;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseResignationReportRepository::findAll() gagne un filtrage year/month
 * (sur resignation_date, une vraie colonne DATE) et person_in_charge, pour
 * offrir sur /admin/reports/resignation la même barre de filtres que
 * /admin/reports/salary. Ce test vérifie ce filtrage contre une vraie base,
 * car whereYear()/whereMonth() ont un comportement driver-dépendant qu'un
 * repository mocké ne peut pas vérifier.
 */
final class ResignationReportFilterTest extends TestCase
{
    protected function setUp(): void
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

        $now = date('Y-m-d H:i:s');
        $capsule->table('stores')->insert(['name' => 'Store A', 'code' => 'STA', 'created_at' => $now, 'updated_at' => $now]);
        $capsule->table('resignation_reports')->insert([
            'store_id' => 1, 'employee_name' => 'Jean Dupont', 'resignation_date' => '2026-07-10',
            'person_in_charge' => 'Alice Dupont', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $capsule->table('resignation_reports')->insert([
            'store_id' => 1, 'employee_name' => 'Paul Martin', 'resignation_date' => '2026-08-15',
            'person_in_charge' => 'Bob Sato', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function testFiltersByYearAndMonth(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(1, $repo->findAll(null, ['year' => '2026', 'month' => '07']));
        $this->assertCount(1, $repo->findAll(null, ['year' => '2026', 'month' => '08']));
        $this->assertCount(0, $repo->findAll(null, ['year' => '2025']));
        $this->assertCount(2, $repo->findAll(null, ['year' => '2026']));
    }

    public function testFiltersByPersonInChargeSubstring(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(1, $repo->findAll(null, ['person_in_charge' => 'Dupont']));
        $this->assertCount(1, $repo->findAll(null, ['person_in_charge' => 'Sato']));
        $this->assertCount(0, $repo->findAll(null, ['person_in_charge' => 'Nobody']));
    }

    public function testNoFiltersReturnsEverything(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(2, $repo->findAll());
    }
}
