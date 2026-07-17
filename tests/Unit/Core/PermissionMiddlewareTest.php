<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PermissionMiddlewareTest extends TestCase
{
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private PermissionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->middleware  = new PermissionMiddleware(
            new PermissionService($this->assignments, $this->roles),
            new ViewRenderer(sys_get_temp_dir()),
        );
    }

    private function makeRequest(?string $routeName, array $authUser, ?array $managedIds): Request
    {
        $request = new Request();
        $request->setAttribute('route_name', $routeName);
        $request->setAttribute('auth_user', $authUser);
        $request->setAttribute('managed_store_ids', $managedIds);
        return $request;
    }

    private function next(): \Closure
    {
        return fn(Request $r) => Response::html('ok');
    }

    public function testUnmappedRoutePassesThrough(): void
    {
        // 'admin.activity' n'a volontairement pas de permission fine dans config/permissions.php
        $request = $this->makeRequest('admin.activity', ['id' => 10], [1]);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->status());
    }

    public function testMissingRouteNamePassesThrough(): void
    {
        $request = $this->makeRequest(null, ['id' => 10], [1]);

        $this->assertSame(200, $this->middleware->handle($request, $this->next())->status());
    }

    public function testOwnerBypassesPermissionCheck(): void
    {
        $request = $this->makeRequest('admin.users', ['id' => 1, 'is_admin' => 1], null);
        $this->assignments->expects($this->never())->method('findByUser');

        $this->assertSame(200, $this->middleware->handle($request, $this->next())->status());
    }

    public function testManagerWithScopedPermissionPassesAndNarrowsManagedStores(): void
    {
        // Rôle 2 accorde employees.view sur le store 1 uniquement ; le store 2
        // est géré via un rôle 3 qui ne l'accorde pas.
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 5, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
            ['id' => 6, 'user_id' => 10, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 2],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [2, ['id' => 2, 'is_system' => 0]],
            [3, ['id' => 3, 'is_system' => 0]],
        ]);
        $this->roles->method('getPermissions')->willReturnMap([
            [2, ['employees.view']],
            [3, ['shifts.view']],
        ]);

        $request = $this->makeRequest('admin.users', ['id' => 10], [1, 2]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertSame([1], json_decode($response->body(), true)['managed']);
    }

    public function testManagerWithoutPermissionIsForbidden(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 6, 'user_id' => 10, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 2],
        ]);
        $this->roles->method('findById')->willReturn(['id' => 3, 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['shifts.view']);

        $this->expectException(ForbiddenException::class);

        $this->middleware->handle($this->makeRequest('admin.users', ['id' => 10], [2]), $this->next());
    }

    public function testGlobalScopeGrantPassesWithoutNarrowing(): void
    {
        // Affectation de portée globale (rôle non-système, ex. futur "Auditor" global)
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 7, 'user_id' => 10, 'role_id' => 4, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->willReturn(['id' => 4, 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['employees.view']);

        $request = $this->makeRequest('admin.users', ['id' => 10], [1, 2]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertSame([1, 2], json_decode($response->body(), true)['managed']);
    }
}
