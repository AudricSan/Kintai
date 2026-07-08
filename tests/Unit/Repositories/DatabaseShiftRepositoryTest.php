<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DatabaseShiftRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Domain\Eloquent\Shift as EloquentShift;

final class DatabaseShiftRepositoryTest extends TestCase
{
    private DatabaseShiftRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('shifts', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('user_id')->nullable();
            $table->string('shift_date');
            $table->integer('is_open')->default(0);
            $table->integer('ical_sequence')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        $this->repo = new DatabaseShiftRepository();
    }

    private function shift(int $id, int $storeId = 1, int $userId = 10, string $date = '2025-06-15'): array
    {
        return ['id' => $id, 'store_id' => $storeId, 'user_id' => $userId, 'shift_date' => $date];
    }

    // -------------------------------------------------------------------------
    // findById()
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsShift(): void
    {
        $s = EloquentShift::create(['store_id' => 1, 'user_id' => 10, 'shift_date' => '2025-06-15']);
        $found = $this->repo->findById($s->id);
        $this->assertNotNull($found);
        $this->assertEquals($s->id, $found['id']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    // -------------------------------------------------------------------------
    // findByStore()
    // -------------------------------------------------------------------------

    public function testFindByStoreReturnsResults(): void
    {
        EloquentShift::create(['store_id' => 2, 'user_id' => 1, 'shift_date' => '2025-06-15']);
        EloquentShift::create(['store_id' => 2, 'user_id' => 2, 'shift_date' => '2025-06-15']);
        EloquentShift::create(['store_id' => 1, 'user_id' => 1, 'shift_date' => '2025-06-15']);

        $this->assertCount(2, $this->repo->findByStore(2));
    }

    // -------------------------------------------------------------------------
    // findByUser()
    // -------------------------------------------------------------------------

    public function testFindByUserReturnsResults(): void
    {
        EloquentShift::create(['store_id' => 1, 'user_id' => 5, 'shift_date' => '2025-06-15']);
        EloquentShift::create(['store_id' => 2, 'user_id' => 5, 'shift_date' => '2025-06-16']);

        $this->assertCount(2, $this->repo->findByUser(5));
    }

    public function testFindByUserReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->repo->findByUser(5));
    }

    // -------------------------------------------------------------------------
    // findByDate()
    // -------------------------------------------------------------------------

    public function testFindByDateReturnsMatchingShifts(): void
    {
        EloquentShift::create(['store_id' => 1, 'user_id' => 10, 'shift_date' => '2025-06-15']);
        
        $result = $this->repo->findByDate(1, '2025-06-15');
        $this->assertCount(1, $result);
        $this->assertEquals('2025-06-15', $result[0]['shift_date']);
    }

    // -------------------------------------------------------------------------
    // findByUserAndDate()
    // -------------------------------------------------------------------------

    public function testFindByUserAndDateReturnsResults(): void
    {
        EloquentShift::create(['store_id' => 1, 'user_id' => 10, 'shift_date' => '2025-07-01']);
        
        $result = $this->repo->findByUserAndDate(10, 1, '2025-07-01');
        $this->assertCount(1, $result);
    }

    public function testFindByUserAndDateReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->repo->findByUserAndDate(10, 1, '2025-07-01'));
    }

    // -------------------------------------------------------------------------
    // findAll()
    // -------------------------------------------------------------------------

    public function testFindAllReturnsAllShifts(): void
    {
        EloquentShift::create(['store_id' => 1, 'user_id' => 1, 'shift_date' => '2025-06-15']);
        EloquentShift::create(['store_id' => 2, 'user_id' => 2, 'shift_date' => '2025-06-15']);

        $this->assertCount(2, $this->repo->findAll());
    }

    // -------------------------------------------------------------------------
    // findAllByDate()
    // -------------------------------------------------------------------------

    public function testFindAllByDateReturnsShifts(): void
    {
        EloquentShift::create(['store_id' => 1, 'user_id' => 10, 'shift_date' => '2025-06-20']);
        EloquentShift::create(['store_id' => 2, 'user_id' => 11, 'shift_date' => '2025-06-20']);

        $this->assertCount(2, $this->repo->findAllByDate('2025-06-20'));
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSaveCreatesShift(): void
    {
        $data = ['store_id' => 1, 'user_id' => 10, 'shift_date' => '2025-06-15'];
        $result = $this->repo->save($data);
        
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(0, $result['ical_sequence']);
        $this->assertCount(1, EloquentShift::all());
    }

    public function testSaveIncrementsIcalSequenceOnUpdate(): void
    {
        $s = EloquentShift::create([
            'store_id' => 1, 
            'user_id' => 10, 
            'shift_date' => '2025-06-15', 
            'ical_sequence' => 2
        ]);
        
        $data = ['id' => $s->id, 'shift_date' => '2025-06-16'];
        $result = $this->repo->save($data);
        
        $this->assertEquals(3, $result['ical_sequence']);
        $this->assertNotNull($result['updated_at']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteDeletesShift(): void
    {
        $s = EloquentShift::create(['store_id' => 1, 'user_id' => 1, 'shift_date' => '2025-06-15']);
        $count = $this->repo->delete($s->id);
        
        $this->assertEquals(1, $count);
        $this->assertNull(EloquentShift::find($s->id));
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete(999));
    }
}
