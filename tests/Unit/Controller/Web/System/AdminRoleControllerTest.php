<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use kintai\Core\Container;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\FeatureManager;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\System\AdminRoleController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminRoleControllerTest extends TestCase
{
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private UserRepositoryInterface&MockObject $users;
    private StoreRepositoryInterface&MockObject $stores;
    private AdminRoleController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('system.roles');
        $this->ensureViewFile('system.roles-form');
        $this->ensureViewFile('layout.app');

        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->users       = $this->createMock(UserRepositoryInterface::class);
        $this->stores      = $this->createMock(StoreRepositoryInterface::class);

        $this->controller = new AdminRoleController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->roles,
            $this->assignments,
            $this->users,
            $this->stores,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        // Réinitialise le singleton Container pour ne pas propager le
        // FeatureManager injecté par les tests de bundles désactivés.
        $instance = new \ReflectionProperty(Container::class, 'instance');
        $instance->setValue(null, null);
    }

    private function ownerRequest(): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        return $req;
    }

    // -------------------------------------------------------------------------
    // roles()
    // -------------------------------------------------------------------------

    public function testRolesRendersListWithAssignmentCounts(): void
    {
        $this->roles->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Owner', 'slug' => 'owner', 'is_system' => 1],
            ['id' => 2, 'name' => 'Manager', 'slug' => 'manager', 'is_system' => 0],
        ]);
        $this->assignments->method('findByRole')->willReturnMap([
            [1, [['id' => 10]]],
            [2, [['id' => 11], ['id' => 12]]],
        ]);

        $response = $this->controller->roles($this->ownerRequest());

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // createRole() / storeRole()
    // -------------------------------------------------------------------------

    public function testCreateRoleRendersForm(): void
    {
        $response = $this->controller->createRole($this->ownerRequest());
        $this->assertSame(200, $response->status());
    }

    public function testStoreRoleCreatesRoleWithPermissions(): void
    {
        $_POST = [
            'name'                    => 'Auditor',
            'description'             => 'Lecture seule',
            'color'                   => '#ff0000',
            'perm_employees_view'     => '1',
            'perm_documents_view'     => '1',
        ];
        $req = $this->ownerRequest();

        $this->roles->method('findBySlug')->willReturn(null);

        $captured = null;
        $this->roles->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 5];
        });

        $capturedPermissions = null;
        $this->roles->expects($this->once())->method('savePermissions')
            ->willReturnCallback(function (int $roleId, array $perms) use (&$capturedPermissions) {
                $capturedPermissions = $perms;
            });

        $response = $this->controller->storeRole($req);

        $this->assertSame(302, $response->status());
        $this->assertSame('Auditor', $captured['name']);
        $this->assertSame('auditor', $captured['slug']);
        $this->assertSame(0, $captured['is_system']);
        sort($capturedPermissions);
        $this->assertSame(['documents.view', 'employees.view'], $capturedPermissions);
    }

    public function testStoreRoleRedirectsWithErrorWhenNameBlank(): void
    {
        $_POST = ['name' => '   '];
        $this->roles->expects($this->never())->method('save');

        $response = $this->controller->storeRole($this->ownerRequest());

        $this->assertSame(302, $response->status());
    }

    public function testStoreRoleDedupesSlugWhenAlreadyTaken(): void
    {
        $_POST = ['name' => 'Manager'];
        $this->roles->method('findBySlug')->willReturnMap([
            ['manager', ['id' => 2, 'slug' => 'manager']],
            ['manager-2', null],
        ]);

        $captured = null;
        $this->roles->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 6];
        });

        $this->controller->storeRole($this->ownerRequest());

        $this->assertSame('manager-2', $captured['slug']);
    }

    // -------------------------------------------------------------------------
    // editRole() / updateRole()
    // -------------------------------------------------------------------------

    public function testEditRoleThrowsWhenNotFound(): void
    {
        $this->roles->method('findById')->willReturn(null);
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 99]);

        $this->expectException(NotFoundException::class);
        $this->controller->editRole($req);
    }

    public function testEditRoleRendersHoldersList(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['employees.view']);
        $this->assignments->method('findByRole')->willReturn([
            ['id' => 1, 'user_id' => 7, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->users->method('findById')->with(7)->willReturn(['id' => 7, 'display_name' => 'Jane']);
        $this->stores->method('findById')->with(3)->willReturn(['id' => 3, 'name' => 'Store A']);

        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $response = $this->controller->editRole($req);
        $this->assertSame(200, $response->status());
    }

    public function testUpdateRoleThrowsForbiddenOnSystemRole(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 1, 'name' => 'Owner', 'is_system' => 1]);
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 1]);

        $this->expectException(ForbiddenException::class);
        $this->controller->updateRole($req);
    }

    public function testUpdateRoleReplacesPermissions(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['employees.view']);
        $this->roles->method('save')->willReturnCallback(fn(array $d) => $d);

        $capturedPermissions = null;
        $this->roles->expects($this->once())->method('savePermissions')
            ->willReturnCallback(function (int $roleId, array $perms) use (&$capturedPermissions) {
                $capturedPermissions = $perms;
            });

        $_POST = ['name' => 'Manager', 'perm_shifts_view' => '1'];
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $response = $this->controller->updateRole($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(['shifts.view'], $capturedPermissions);
    }

    public function testUpdateRolePreservesPermissionsOfDisabledBundles(): void
    {
        // Aucun bundle activé → les catégories portées par un bundle (timeoff,
        // swaps…) sont masquées du formulaire : leurs cases ne sont ni
        // affichées ni prises en compte, mais les clés déjà accordées
        // survivent à la sauvegarde.
        Container::getInstance()->instance(FeatureManager::class, new FeatureManager([]));

        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['timeoff.view', 'timeoff.approve', 'employees.view']);
        $this->roles->method('save')->willReturnCallback(fn(array $d) => $d);

        $capturedPermissions = null;
        $this->roles->expects($this->once())->method('savePermissions')
            ->willReturnCallback(function (int $roleId, array $perms) use (&$capturedPermissions) {
                $capturedPermissions = $perms;
            });

        $_POST = ['name' => 'Manager', 'perm_shifts_view' => '1', 'perm_swaps_view' => '1'];
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $this->controller->updateRole($req);

        sort($capturedPermissions);
        // employees.view (catégorie Core, décochée) disparaît ; swaps.view
        // (catégorie masquée, cochée par manipulation du POST) est ignorée ;
        // les clés timeoff.* déjà en base sont préservées.
        $this->assertSame(['shifts.view', 'timeoff.approve', 'timeoff.view'], $capturedPermissions);
    }

    public function testUpdateRoleAcceptsBundleCategoryWhenBundleEnabled(): void
    {
        Container::getInstance()->instance(FeatureManager::class, new FeatureManager(['timeoff']));

        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn([]);
        $this->roles->method('save')->willReturnCallback(fn(array $d) => $d);

        $capturedPermissions = null;
        $this->roles->expects($this->once())->method('savePermissions')
            ->willReturnCallback(function (int $roleId, array $perms) use (&$capturedPermissions) {
                $capturedPermissions = $perms;
            });

        $_POST = ['name' => 'Manager', 'perm_timeoff_view' => '1'];
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $this->controller->updateRole($req);

        $this->assertSame(['timeoff.view'], $capturedPermissions);
    }

    // -------------------------------------------------------------------------
    // deleteRole()
    // -------------------------------------------------------------------------

    public function testDeleteRoleThrowsForbiddenOnSystemRole(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 1, 'name' => 'Owner', 'is_system' => 1]);
        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 1]);

        $this->expectException(ForbiddenException::class);
        $this->controller->deleteRole($req);
    }

    public function testDeleteRoleRedirectsWithErrorWhenStillAssigned(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->assignments->method('findByRole')->willReturn([['id' => 1]]);
        $this->roles->expects($this->never())->method('delete');

        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $response = $this->controller->deleteRole($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteRoleDeletesWhenUnassigned(): void
    {
        $this->roles->method('findById')->willReturn(['id' => 2, 'name' => 'Auditor', 'is_system' => 0]);
        $this->assignments->method('findByRole')->willReturn([]);
        $this->roles->expects($this->once())->method('delete')->with(2);

        $req = $this->ownerRequest();
        $req->setRouteParams(['id' => 2]);

        $response = $this->controller->deleteRole($req);

        $this->assertSame(302, $response->status());
    }

    private function ensureViewFile(string $view): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($file);
    }
}
