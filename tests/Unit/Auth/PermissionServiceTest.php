<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;

final class PermissionServiceTest extends TestCase
{
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private PermissionService $service;

    protected function setUp(): void
    {
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->service     = new PermissionService($this->assignments, $this->roles);
    }

    private function role(int $id, bool $isSystem = false): array
    {
        return ['id' => $id, 'name' => 'Role ' . $id, 'slug' => 'role-' . $id, 'is_system' => $isSystem ? 1 : 0];
    }

    // -------------------------------------------------------------------------
    // can() — utilisateur invalide
    // -------------------------------------------------------------------------

    public function testCanReturnsFalseForMissingUserId(): void
    {
        $this->assertFalse($this->service->can([], 'employees.create'));
    }

    // -------------------------------------------------------------------------
    // can() — rôle système (Owner)
    // -------------------------------------------------------------------------

    public function testCanReturnsTrueForSystemRoleRegardlessOfPermissionsTable(): void
    {
        $this->assignments->method('findByUser')->with(1)->willReturn([
            ['id' => 1, 'user_id' => 1, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->with(10)->willReturn($this->role(10, isSystem: true));
        $this->roles->expects($this->never())->method('getPermissions');

        $this->assertTrue($this->service->can(['id' => 1], 'settings.update', 5));
    }

    // -------------------------------------------------------------------------
    // can() — rôle store-scope
    // -------------------------------------------------------------------------

    public function testCanReturnsTrueWhenRoleGrantsPermissionOnMatchingStore(): void
    {
        $this->assignments->method('findByUser')->with(2)->willReturn([
            ['id' => 1, 'user_id' => 2, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
        ]);
        $this->roles->method('findById')->with(20)->willReturn($this->role(20));
        $this->roles->method('getPermissions')->with(20)->willReturn(['employees.create']);

        $this->assertTrue($this->service->can(['id' => 2], 'employees.create', 5));
    }

    public function testCanReturnsFalseWhenStoreDoesNotMatch(): void
    {
        $this->assignments->method('findByUser')->with(2)->willReturn([
            ['id' => 1, 'user_id' => 2, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
        ]);
        $this->roles->method('findById')->with(20)->willReturn($this->role(20));
        $this->roles->method('getPermissions')->with(20)->willReturn(['employees.create']);

        $this->assertFalse($this->service->can(['id' => 2], 'employees.create', 6));
    }

    public function testCanReturnsFalseWhenRoleDoesNotGrantPermission(): void
    {
        $this->assignments->method('findByUser')->with(2)->willReturn([
            ['id' => 1, 'user_id' => 2, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
        ]);
        $this->roles->method('findById')->with(20)->willReturn($this->role(20));
        $this->roles->method('getPermissions')->with(20)->willReturn(['employees.view']);

        $this->assertFalse($this->service->can(['id' => 2], 'employees.delete', 5));
    }

    public function testCanWithNullStoreMatchesAnyScope(): void
    {
        $this->assignments->method('findByUser')->with(2)->willReturn([
            ['id' => 1, 'user_id' => 2, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
        ]);
        $this->roles->method('findById')->with(20)->willReturn($this->role(20));
        $this->roles->method('getPermissions')->with(20)->willReturn(['employees.view']);

        $this->assertTrue($this->service->can(['id' => 2], 'employees.view'));
    }

    public function testCanUnionsPermissionsAcrossMultipleRoles(): void
    {
        $this->assignments->method('findByUser')->with(3)->willReturn([
            ['id' => 1, 'user_id' => 3, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
            ['id' => 2, 'user_id' => 3, 'role_id' => 21, 'scope_type' => 'store', 'scope_id' => 5],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [20, $this->role(20)],
            [21, $this->role(21)],
        ]);
        $this->roles->method('getPermissions')->willReturnMap([
            [20, ['employees.view']],
            [21, ['employees.delete']],
        ]);

        $this->assertTrue($this->service->can(['id' => 3], 'employees.delete', 5));
    }

    // -------------------------------------------------------------------------
    // scopedStoreIds()
    // -------------------------------------------------------------------------

    public function testScopedStoreIdsReturnsStoresGrantingThatPermission(): void
    {
        $this->assignments->method('findByUser')->with(4)->willReturn([
            ['id' => 1, 'user_id' => 4, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 5],
            ['id' => 2, 'user_id' => 4, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => 6],
            ['id' => 3, 'user_id' => 4, 'role_id' => 21, 'scope_type' => 'store', 'scope_id' => 7],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [20, $this->role(20)],
            [21, $this->role(21)],
        ]);
        $this->roles->method('getPermissions')->willReturnMap([
            [20, ['employees.view']],
            [21, ['shifts.view']],
        ]);

        $result = $this->service->scopedStoreIds(4, 'employees.view');
        $this->assertSame([5, 6], $result);
    }

    public function testScopedStoreIdsExcludesGlobalAssignments(): void
    {
        $this->assignments->method('findByUser')->with(1)->willReturn([
            ['id' => 1, 'user_id' => 1, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->with(10)->willReturn($this->role(10, isSystem: true));

        $this->assertSame([], $this->service->scopedStoreIds(1, 'employees.view'));
    }
}
