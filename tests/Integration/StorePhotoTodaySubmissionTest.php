<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Repositories\DatabaseStorePhotoRepository;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseStorePhotoRepository::findTodaySubmission() permet de fusionner plusieurs
 * envois de photos du même magasin le même jour en un seul rapport (voir
 * StorePhotoController::store()). Le filtrage par plage de created_at (choisi plutôt
 * que whereDate(), driver-dépendant) est vérifié contre une vraie base.
 */
final class StorePhotoTodaySubmissionTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->capsule = $capsule;

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
    }

    public function testReturnsSubmissionCreatedTodayForSameStore(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $today, 'image_count' => 2,
            'created_at' => $today . ' 09:00:00',
        ]);

        $repo  = new DatabaseStorePhotoRepository();
        $found = $repo->findTodaySubmission(1, $today);

        $this->assertNotNull($found);
        $this->assertSame(2, $found['image_count']);
    }

    public function testIgnoresSubmissionsFromOtherDays(): void
    {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $yesterday, 'image_count' => 1,
            'created_at' => $yesterday . ' 23:59:59',
        ]);

        $repo = new DatabaseStorePhotoRepository();

        $this->assertNull($repo->findTodaySubmission(1, $today));
    }

    public function testIgnoresSubmissionsFromOtherStores(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('stores')->insert(['name' => 'Store B', 'code' => 'STB', 'created_at' => $today, 'updated_at' => $today]);
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 2, 'week_label' => $today, 'image_count' => 5,
            'created_at' => $today . ' 10:00:00',
        ]);

        $repo = new DatabaseStorePhotoRepository();

        $this->assertNull($repo->findTodaySubmission(1, $today));
    }

    public function testIgnoresSoftDeletedSubmissions(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $today, 'image_count' => 3,
            'created_at' => $today . ' 08:00:00', 'deleted_at' => $today . ' 08:30:00',
        ]);

        $repo = new DatabaseStorePhotoRepository();

        $this->assertNull($repo->findTodaySubmission(1, $today));
    }
}
