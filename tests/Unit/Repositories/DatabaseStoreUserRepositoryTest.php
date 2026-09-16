<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DatabaseStoreUserRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Domain\Eloquent\StoreUser as EloquentStoreUser;

final class DatabaseStoreUserRepositoryTest extends TestCase
{
    private DatabaseStoreUserRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('store_user', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('user_id');
            $table->string('role')->default('staff');
        });

        $this->repo = new DatabaseStoreUserRepository();
    }

    private function membership(int $id, int $storeId = 1, int $userId = 10, string $role = 'staff'): array
    {
        return ['id' => $id, 'store_id' => $storeId, 'user_id' => $userId, 'role' => $role];
    }

    // -------------------------------------------------------------------------
    // findById()
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsMembership(): void
    {
        $m = EloquentStoreUser::create(['store_id' => 1, 'user_id' => 10, 'role' => 'admin']);
        $found = $this->repo->findById($m->id);
        $this->assertNotNull($found);
        $this->assertEquals('admin', $found['role']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    // -------------------------------------------------------------------------
    // findByStore()
    // -------------------------------------------------------------------------

    public function testFindByStoreReturnsAllMembersForStore(): void
    {
        EloquentStoreUser::create(['store_id' => 2, 'user_id' => 10]);
        EloquentStoreUser::create(['store_id' => 2, 'user_id' => 11]);
        EloquentStoreUser::create(['store_id' => 3, 'user_id' => 12]);

        $this->assertCount(2, $this->repo->findByStore(2));
    }

    // -------------------------------------------------------------------------
    // findByUser()
    // -------------------------------------------------------------------------

    public function testFindByUserReturnsAllStoresForUser(): void
    {
        EloquentStoreUser::create(['store_id' => 1, 'user_id' => 5]);
        EloquentStoreUser::create(['store_id' => 3, 'user_id' => 5]);

        $result = $this->repo->findByUser(5);
        $this->assertCount(2, $result);
    }

    public function testFindByUserReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->repo->findByUser(99));
    }

    // -------------------------------------------------------------------------
    // findMembership()
    // -------------------------------------------------------------------------

    public function testFindMembershipReturnsMembership(): void
    {
        EloquentStoreUser::create(['store_id' => 3, 'user_id' => 7, 'role' => 'manager']);
        $result = $this->repo->findMembership(3, 7);
        $this->assertNotNull($result);
        $this->assertSame('manager', $result['role']);
    }

    public function testFindMembershipReturnsNullWhenNotMember(): void
    {
        $this->assertNull($this->repo->findMembership(1, 999));
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSaveCreatesMembership(): void
    {
        $data = ['store_id' => 1, 'user_id' => 10, 'role' => 'staff'];
        $result = $this->repo->save($data);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('staff', $result['role']);
    }

    public function testSaveUpdatesMembership(): void
    {
        $m = EloquentStoreUser::create(['store_id' => 1, 'user_id' => 10, 'role' => 'staff']);
        $result = $this->repo->save(['id' => $m->id, 'role' => 'admin']);
        $this->assertEquals('admin', $result['role']);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteDeletesMembership(): void
    {
        $m = EloquentStoreUser::create(['store_id' => 1, 'user_id' => 1]);
        $count = $this->repo->delete($m->id);
        $this->assertEquals(1, $count);
        $this->assertNull(EloquentStoreUser::find($m->id));
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete(999));
    }
}
