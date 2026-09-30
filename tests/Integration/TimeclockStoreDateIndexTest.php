<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Repositories\DatabaseTimeclockRepository;
use PHPUnit\Framework\TestCase;

/**
 * Le widget RH lit les pointages d'un store sur une période : lecture limitée par date (findByStoreBetween()) et
 * index (store_id, shift_date) pour ne plus parcourir tout l'historique.
 */
final class TimeclockStoreDateIndexTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $runner = (new \ReflectionClass(MigrationRunner::class))->newInstanceWithoutConstructor();
        foreach (['capsule' => $this->capsule, 'migrationsPath' => dirname(__DIR__, 2) . '/database/migrations/php'] as $prop => $value) {
            $p = new \ReflectionProperty($runner, $prop);
            $p->setAccessible(true);
            $p->setValue($runner, $value);
        }
        $runner->run();
    }

    public function testAFreshInstallHasTheStoreDateIndex(): void
    {
        $columns = array_map(
            static fn(array $i): array => $i['columns'],
            $this->capsule->getConnection()->getSchemaBuilder()->getIndexes('timeclocks')
        );

        $this->assertContains(['store_id', 'shift_date'], $columns);
    }

    public function testFindByStoreBetweenReturnsOnlyTheStoreAndThePeriodBoundsIncluded(): void
    {
        $db = $this->capsule->getConnection();
        $db->table('stores')->insert([['id' => 1, 'name' => 'A', 'code' => 'A'], ['id' => 2, 'name' => 'B', 'code' => 'B']]);
        $db->table('users')->insert(['id' => 10, 'email' => 'u@kintai.test', 'password_hash' => 'x', 'first_name' => 'A', 'last_name' => 'B', 'display_name' => 'A B']);
        foreach ([[1, 1, '2026-06-01'], [2, 1, '2026-06-15'], [3, 1, '2026-06-30'], [4, 1, '2026-07-01'], [5, 2, '2026-06-15']] as [$id, $store, $date]) {
            $db->table('timeclocks')->insert(['id' => $id, 'user_id' => 10, 'store_id' => $store, 'shift_date' => $date, 'clock_in_time' => $date . ' 09:00:00']);
        }

        $ids = array_map('intval', array_column((new DatabaseTimeclockRepository())->findByStoreBetween(1, '2026-06-01', '2026-06-30'), 'id'));

        $this->assertSame([1, 2, 3], $ids);
    }
}
