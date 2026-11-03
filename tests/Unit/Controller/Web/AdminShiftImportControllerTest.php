<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\ImportAliasRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\RoleAssignmentSyncService;
use kintai\UI\Controller\Web\Scheduling\AdminShiftImportController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * L'import Excel matche les noms de la feuille aux employés existants. Deux
 * employés de magasins différents peuvent partager le même nom de famille ;
 * la correspondance automatique ne doit porter que sur les membres du magasin
 * courant, sous peine d'assigner un shift au mauvais utilisateur.
 */
final class AdminShiftImportControllerTest extends TestCase
{
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private UserRepositoryInterface&Stub $users;
    private ShiftRepositoryInterface&Stub $shifts;
    private StoreRepositoryInterface&Stub $stores;
    private ShiftTypeRepositoryInterface&Stub $shiftTypes;
    private ImportAliasRepositoryInterface&Stub $importAliases;
    private ?AdminShiftImportController $controller = null;

    protected function setUp(): void
    {
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $this->importAliases = $this->createStub(ImportAliasRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminShiftImportController
    {
        if ($this->controller === null) {
            $roleSync = new RoleAssignmentSyncService(
                $this->createStub(RoleRepositoryInterface::class),
                $this->createStub(RoleAssignmentRepositoryInterface::class),
            );
            $this->controller = new AdminShiftImportController(
                new ViewRenderer(sys_get_temp_dir()),
                $this->users,
                $this->stores,
                $this->shifts,
                $this->shiftTypes,
                $this->storeUsers,
                new AuditLogger(),
                $this->importAliases,
                $roleSync,
            );
        }

        return $this->controller;
    }

    public function testResolveEntryUserIdsIgnoresHomonymFromAnotherStore(): void
    {
        $entries = [
            ['staff_name' => 'Dupont'],
        ];

        // Seul l'utilisateur #10 (Dupont Jean) est membre du magasin courant ;
        // #20 (Dupont Marie) est un homonyme d'un autre magasin et ne doit
        // jamais être proposé automatiquement.
        $storeMembers = [
            ['id' => 10, 'last_name' => 'Dupont', 'first_name' => 'Jean', 'display_name' => 'Dupont'],
        ];

        $method = new \ReflectionMethod($this->controller(), 'resolveEntryUserIds');
        $method->setAccessible(true);
        $resolved = $method->invoke($this->controller(), $entries, $storeMembers, []);

        $this->assertSame(10, $resolved[0]['user_id']);
    }

    public function testResolveEntryUserIdsLeavesUnassignedWhenNameOnlyMatchesAnotherStore(): void
    {
        $entries = [
            ['staff_name' => 'Martin'],
        ];

        // Aucun "Martin" dans ce magasin : même si un "Martin" existe ailleurs
        // dans l'application, il ne doit pas être auto-assigné ici.
        $storeMembers = [
            ['id' => 10, 'last_name' => 'Dupont', 'first_name' => 'Jean', 'display_name' => 'Dupont'],
        ];

        $method = new \ReflectionMethod($this->controller(), 'resolveEntryUserIds');
        $method->setAccessible(true);
        $resolved = $method->invoke($this->controller(), $entries, $storeMembers, []);

        $this->assertSame(0, $resolved[0]['user_id']);
    }

    public function testResolveEntryUserIdsFallsBackToStoreScopedAlias(): void
    {
        $entries = [
            ['staff_name' => 'M. Dupont (surnom)'],
        ];

        $resolved = (new \ReflectionMethod($this->controller(), 'resolveEntryUserIds'))
            ->invoke($this->controller(), $entries, [], ['m. dupont (surnom)' => 10]);

        $this->assertSame(10, $resolved[0]['user_id']);
    }

    public function testStoreMemberIdsReturnsUserIdsOfStoreMembership(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['id' => 1, 'store_id' => 1, 'user_id' => 10],
            ['id' => 2, 'store_id' => 1, 'user_id' => 11],
        ]);

        $method = new \ReflectionMethod($this->controller(), 'storeMemberIds');
        $method->setAccessible(true);
        $ids = $method->invoke($this->controller(), 1);

        $this->assertSame([10, 11], $ids);
    }

    public function testActiveStoreMembersExcludesTombstonesAndInactiveUsers(): void
    {
        $this->storeUsers->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10],
            ['user_id' => 11],
            ['user_id' => 12],
        ]);
        $this->users->method('findAll')->willReturn([
            ['id' => 10, 'is_active' => 1, 'deleted_at' => null],
            ['id' => 11, 'is_active' => 0, 'deleted_at' => null],
            ['id' => 12, 'is_active' => 1, 'deleted_at' => '2026-11-03 10:00:00'],
        ]);

        $method = new \ReflectionMethod($this->controller(), 'activeStoreMembers');
        $method->setAccessible(true);

        $this->assertSame([10], array_column($method->invoke($this->controller(), 1), 'id'));
    }

    public function testFilterImportAliasesDeletesReferencesToNonMembers(): void
    {
        $this->importAliases = $this->createMock(ImportAliasRepositoryInterface::class);
        $this->importAliases->expects($this->once())->method('deleteAlias')->with(1, 'old name');
        $method = new \ReflectionMethod($this->controller(), 'filterImportAliases');
        $method->setAccessible(true);

        $aliases = $method->invoke($this->controller(), 1, ['old name' => 90, 'valid name' => 10], [
            ['id' => 10],
        ]);

        $this->assertSame(['valid name' => 10], $aliases);
    }

    public function testFilterImportAliasesRejectsCaseInsensitiveAmbiguity(): void
    {
        $method = new \ReflectionMethod($this->controller(), 'filterImportAliases');
        $method->setAccessible(true);

        $aliases = $method->invoke($this->controller(), 1, ['Name' => 10, ' name ' => 11], [
            ['id' => 10],
            ['id' => 11],
        ]);

        $this->assertSame([], $aliases);
    }

    public function testConfirmImportTransfersExactShiftFromTombstoneToActiveSelectedUser(): void
    {
        $this->storeUsers->method('findByStore')->with(1)->willReturn([['user_id' => 20]]);
        $this->users->method('findAll')->willReturn([
            ['id' => 20, 'is_active' => 1, 'deleted_at' => null],
        ]);
        $this->users->method('findById')->with(10)->willReturn([
            'id' => 10, 'is_active' => 0, 'deleted_at' => '2026-11-03 10:00:00',
        ]);
        $this->stores->method('getImportSettings')->with(1)->willReturn([]);
        $this->shiftTypes->method('findActive')->with(1)->willReturn([]);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shifts->method('findByDate')->with(1, '2026-11-04')->willReturn([
            [
                'id' => 42, 'user_id' => 10, 'store_id' => 1, 'shift_date' => '2026-11-04',
                'start_time' => '09:00', 'end_time' => '17:00', 'deleted_at' => null,
            ],
        ]);
        $this->shifts->method('findByUserAndDate')->with(20, 1, '2026-11-04')->willReturn([]);
        $this->shifts->method('findByUser')->with(20)->willReturn([]);
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            static fn(array $shift): bool => ($shift['id'] ?? null) === 42
                && ($shift['user_id'] ?? null) === 20
        ));
        $this->importAliases = $this->createMock(ImportAliasRepositoryInterface::class);
        $this->importAliases->method('transaction')->willReturnCallback(
            static fn(callable $callback): mixed => $callback()
        );
        $this->importAliases->expects($this->once())->method('upsert')->with(1, 'new name', 20);

        $_POST = [
            'store_id' => '1',
            'shifts_json' => json_encode([[
                'date' => '2026-11-04',
                'staff_name' => 'New Name',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'user_id' => '20',
                'skip' => '0',
            ]], JSON_THROW_ON_ERROR),
        ];
        $request = new Request();
        $request->setAttribute('managed_store_ids', null);

        $this->assertSame(302, $this->controller()->confirmImport($request)->status());
    }

    public function testConfirmImportDoesNotTransferExactShiftFromAnotherActiveEmployee(): void
    {
        $this->storeUsers->method('findByStore')->with(1)->willReturn([['user_id' => 20]]);
        $this->users->method('findAll')->willReturn([
            ['id' => 20, 'is_active' => 1, 'deleted_at' => null],
        ]);
        $this->users->method('findById')->with(10)->willReturn([
            'id' => 10, 'is_active' => 1, 'deleted_at' => null,
        ]);
        $this->stores->method('getImportSettings')->with(1)->willReturn([]);
        $this->shiftTypes->method('findActive')->with(1)->willReturn([]);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shifts->method('findByDate')->with(1, '2026-11-04')->willReturn([
            [
                'id' => 42, 'user_id' => 10, 'store_id' => 1, 'shift_date' => '2026-11-04',
                'start_time' => '09:00', 'end_time' => '17:00', 'deleted_at' => null,
            ],
        ]);
        $this->shifts->method('findByUserAndDate')->with(20, 1, '2026-11-04')->willReturn([]);
        $this->shifts->method('findByUser')->with(20)->willReturn([]);
        $this->shifts->expects($this->never())->method('save');
        $this->importAliases = $this->createMock(ImportAliasRepositoryInterface::class);
        $this->importAliases->method('transaction')->willReturnCallback(
            static fn(callable $callback): mixed => $callback()
        );
        $this->importAliases->expects($this->once())->method('upsert')->with(1, 'new name', 20);

        $_POST = [
            'store_id' => '1',
            'shifts_json' => json_encode([[
                'date' => '2026-11-04',
                'staff_name' => 'New Name',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'user_id' => '20',
                'skip' => '0',
            ]], JSON_THROW_ON_ERROR),
        ];
        $request = new Request();
        $request->setAttribute('managed_store_ids', null);

        $this->assertSame(302, $this->controller()->confirmImport($request)->status());
    }
}
