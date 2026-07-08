<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DatabaseStoreRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Domain\Eloquent\Store as EloquentStore;
use kintai\Domain\Eloquent\StoreFeature;
use kintai\Domain\Eloquent\StoreImportSetting;
use kintai\Domain\Eloquent\StoreDeductionSetting;

final class DatabaseStoreRepositoryTest extends TestCase
{
    private DatabaseStoreRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->getConnection()->getSchemaBuilder();
        
        $schema->create('stores', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('is_active')->default(1);
            $table->timestamp('deleted_at')->nullable();
        });

        $schema->create('store_features', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('feature_key');
        });

        $schema->create('store_import_settings', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('setting_name')->nullable();
        });

        $schema->create('store_deduction_settings', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('setting_name')->nullable();
        });

        $this->repo = new DatabaseStoreRepository();
    }

    private function store(int $id, string $code = 'STORE01', int $active = 1, ?string $deletedAt = null): array
    {
        return ['id' => $id, 'code' => $code, 'name' => 'Test Store', 'is_active' => $active, 'deleted_at' => $deletedAt];
    }

    // -------------------------------------------------------------------------
    // findById()
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsStore(): void
    {
        $store = EloquentStore::create(['name' => 'Test', 'code' => 'T01']);
        $found = $this->repo->findById($store->id);
        $this->assertNotNull($found);
        $this->assertEquals('T01', $found['code']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    // -------------------------------------------------------------------------
    // findByCode()
    // -------------------------------------------------------------------------

    public function testFindByCodeReturnsStore(): void
    {
        EloquentStore::create(['name' => 'Test', 'code' => 'T01']);
        $found = $this->repo->findByCode('T01');
        $this->assertNotNull($found);
        $this->assertEquals('T01', $found['code']);
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findByCode('UNKNOWN'));
    }

    // -------------------------------------------------------------------------
    // findAll()
    // -------------------------------------------------------------------------

    public function testFindAllReturnsAllIncludingInactive(): void
    {
        EloquentStore::create(['name' => 'S1', 'code' => 'S1', 'is_active' => 1]);
        EloquentStore::create(['name' => 'S2', 'code' => 'S2', 'is_active' => 0]);

        $this->assertCount(2, $this->repo->findAll());
    }

    // -------------------------------------------------------------------------
    // findActive()
    // -------------------------------------------------------------------------

    public function testFindActiveReturnsOnlyActiveStores(): void
    {
        EloquentStore::create(['name' => 'S1', 'code' => 'S1', 'is_active' => 1]);
        EloquentStore::create(['name' => 'S2', 'code' => 'S2', 'is_active' => 0]);
        EloquentStore::create(['name' => 'S3', 'code' => 'S3', 'is_active' => 1, 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->repo->findActive();
        $this->assertCount(1, $result);
        $this->assertEquals('S1', $result[0]['code']);
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSaveCreatesStore(): void
    {
        $data = ['name' => 'New Store', 'code' => 'NEW01', 'is_active' => 1];
        $result = $this->repo->save($data);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('NEW01', $result['code']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteDeletesStore(): void
    {
        $store = EloquentStore::create(['name' => 'T', 'code' => 'T']);
        $count = $this->repo->delete($store->id);
        $this->assertEquals(1, $count);
        $this->assertNull(EloquentStore::find($store->id));
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete(999));
    }

    // -------------------------------------------------------------------------
    // Features / Settings
    // -------------------------------------------------------------------------

    public function testFeaturesPersistence(): void
    {
        $this->repo->saveFeatures(1, ['f1', 'f2']);
        $this->assertEquals(['f1', 'f2'], $this->repo->getFeatures(1));
        
        $this->repo->saveFeatures(1, ['f3']);
        $this->assertEquals(['f3'], $this->repo->getFeatures(1));
    }

    public function testImportSettingsPersistence(): void
    {
        $this->repo->saveImportSettings(1, ['setting_name' => 'val1']);
        $settings = $this->repo->getImportSettings(1);
        $this->assertEquals('val1', $settings['setting_name']);
    }

    public function testDeductionSettingsPersistence(): void
    {
        $this->repo->saveDeductionSettings(1, ['setting_name' => 'val1']);
        $settings = $this->repo->getDeductionSettings(1);
        $this->assertEquals('val1', $settings['setting_name']);
    }
}
