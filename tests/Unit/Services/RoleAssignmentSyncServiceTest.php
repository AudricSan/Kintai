<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Services\RoleAssignmentSyncService;

final class RoleAssignmentSyncServiceTest extends TestCase
{
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleAssignmentSyncService $service;

    protected function setUp(): void
    {
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->service      = new RoleAssignmentSyncService($this->roles, $this->assignments);
    }

    // -------------------------------------------------------------------------
    // syncOwnerRole()
    // -------------------------------------------------------------------------

    public function testSyncOwnerRoleAssignsWhenPromotedAndNotAlreadyOwner(): void
    {
        $this->roles->method('findBySlug')->with('owner')->willReturn(['id' => 1]);
        $this->assignments->method('findByUser')->with(10)->willReturn([]);
        $this->assignments->expects($this->once())->method('assign')->with(10, 1, 'global', null);

        $this->service->syncOwnerRole(10, true);
    }

    public function testSyncOwnerRoleDoesNothingWhenAlreadyOwner(): void
    {
        $this->roles->method('findBySlug')->with('owner')->willReturn(['id' => 1]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 5, 'user_id' => 10, 'role_id' => 1, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->assignments->expects($this->never())->method('assign');
        $this->assignments->expects($this->never())->method('revoke');

        $this->service->syncOwnerRole(10, true);
    }

    public function testSyncOwnerRoleRevokesWhenDemoted(): void
    {
        $this->roles->method('findBySlug')->with('owner')->willReturn(['id' => 1]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 5, 'user_id' => 10, 'role_id' => 1, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->assignments->expects($this->once())->method('revoke')->with(5);

        $this->service->syncOwnerRole(10, false);
    }

    public function testSyncOwnerRoleDoesNothingWhenDemotedAndNotOwner(): void
    {
        $this->roles->method('findBySlug')->with('owner')->willReturn(['id' => 1]);
        $this->assignments->method('findByUser')->willReturn([]);
        $this->assignments->expects($this->never())->method('revoke');

        $this->service->syncOwnerRole(10, false);
    }

    public function testSyncOwnerRoleNoopsWhenOwnerRoleMissing(): void
    {
        $this->roles->method('findBySlug')->willReturn(null);
        $this->assignments->expects($this->never())->method('assign');
        $this->assignments->expects($this->never())->method('findByUser');

        $this->service->syncOwnerRole(10, true);
    }

    // -------------------------------------------------------------------------
    // syncStoreRole()
    // -------------------------------------------------------------------------

    public function testSyncStoreRoleAssignsManagerForAdminLegacyRole(): void
    {
        $this->roles->method('findBySlug')->with('manager')->willReturn(['id' => 2]);
        $this->assignments->method('findByUser')->with(10)->willReturn([]);
        $this->assignments->expects($this->once())->method('assign')->with(10, 2, 'store', 1);

        $this->service->syncStoreRole(10, 1, 'admin');
    }

    public function testSyncStoreRoleAssignsManagerForManagerLegacyRole(): void
    {
        $this->roles->method('findBySlug')->with('manager')->willReturn(['id' => 2]);
        $this->assignments->method('findByUser')->willReturn([]);
        $this->assignments->expects($this->once())->method('assign')->with(10, 2, 'store', 1);

        $this->service->syncStoreRole(10, 1, 'manager');
    }

    public function testSyncStoreRoleAssignsEmployeeForStaffLegacyRole(): void
    {
        $this->roles->method('findBySlug')->with('employee')->willReturn(['id' => 3]);
        $this->assignments->method('findByUser')->willReturn([]);
        $this->assignments->expects($this->once())->method('assign')->with(10, 3, 'store', 1);

        $this->service->syncStoreRole(10, 1, 'staff');
    }

    public function testSyncStoreRoleReplacesExistingDifferentRoleOnSameStore(): void
    {
        $this->roles->method('findBySlug')->with('manager')->willReturn(['id' => 2]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 9, 'user_id' => 10, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->assignments->expects($this->once())->method('revoke')->with(9);
        $this->assignments->expects($this->once())->method('assign')->with(10, 2, 'store', 1);

        $this->service->syncStoreRole(10, 1, 'manager');
    }

    public function testSyncStoreRoleDoesNothingWhenAlreadyCorrect(): void
    {
        $this->roles->method('findBySlug')->with('manager')->willReturn(['id' => 2]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 9, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->assignments->expects($this->never())->method('revoke');
        $this->assignments->expects($this->never())->method('assign');

        $this->service->syncStoreRole(10, 1, 'manager');
    }

    public function testSyncStoreRoleIgnoresAssignmentsOnOtherStores(): void
    {
        $this->roles->method('findBySlug')->with('manager')->willReturn(['id' => 2]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 9, 'user_id' => 10, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 2],
        ]);
        $this->assignments->expects($this->never())->method('revoke');
        $this->assignments->expects($this->once())->method('assign')->with(10, 2, 'store', 1);

        $this->service->syncStoreRole(10, 1, 'manager');
    }

    // -------------------------------------------------------------------------
    // revokeStoreRole()
    // -------------------------------------------------------------------------

    public function testRevokeStoreRoleRevokesMatchingAssignments(): void
    {
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 9, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
            ['id' => 11, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 2],
        ]);
        $this->assignments->expects($this->once())->method('revoke')->with(9);

        $this->service->revokeStoreRole(10, 1);
    }

    public function testRevokeStoreRoleNoopsWhenNoMatch(): void
    {
        $this->assignments->method('findByUser')->willReturn([]);
        $this->assignments->expects($this->never())->method('revoke');

        $this->service->revokeStoreRole(10, 1);
    }
}
