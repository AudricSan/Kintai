<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PermissionMiddlewareTest extends TestCase
{
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private PermissionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->storeUsers  = $this->createMock(StoreUserRepositoryInterface::class);
        $this->middleware  = new PermissionMiddleware(
            new PermissionService($this->assignments, $this->roles),
            new ViewRenderer(sys_get_temp_dir()),
            $this->storeUsers,
        );
    }

    /** @param string|array|null $permission Règle telle que déclarée par Route::$permission. */
    private function makeRequest(string|array|null $permission, array $authUser, ?array $managedIds, array $routeParams = []): Request
    {
        $request = new Request();
        $request->setAttribute('route_permission', $permission);
        $request->setAttribute('auth_user', $authUser);
        $request->setAttribute('managed_store_ids', $managedIds);
        $request->setRouteParams($routeParams);
        return $request;
    }

    private function next(): \Closure
    {
        return fn(Request $r) => Response::html('ok');
    }

    /**
     * Route sans permission déclarée (null) ou 'public' (self-service, agrégat, ou déjà
     * protégée par requireOwner()) : porte grossière héritée de l'ancien AdminMiddleware,
     * fusionné dans ce middleware en RBAC-V2 — au moins une permission RBAC quelque part
     * suffit, aucune clé précise n'est exigée.
     */
    public function testRouteWithoutPermissionDeclaredPassesThroughWhenAnyPermissionIsGranted(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['photos.view']);

        $request  = $this->makeRequest(null, ['id' => 10], [1]);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->status());
    }

    public function testPublicRoutePassesThroughWhenAnyPermissionIsGranted(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['photos.view']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn([]);

        $request = $this->makeRequest('public', ['id' => 10], [1]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        // Non-régression : sans permission globale, managed_store_ids reste borné au
        // store réel — le fix ci-dessous ne doit pas sur-octroyer un accès illimité.
        $this->assertSame(200, $response->status());
        $this->assertSame([1], json_decode($response->body(), true)['managed']);
    }

    /**
     * Repro du bug rapporté : photos.view marquée globale ("Toutes les boutiques") sur
     * un rôle dont l'affectation reste store-scope. anyGrantedStoreIds() (utilisée par
     * la porte grossière ci-dessus) ne consulte jamais getGlobalPermissionKeys() et
     * aurait borné managed_store_ids au seul store d'origine — exactement ce qui faisait
     * échouer le chargement des photos des autres magasins sur /storage/{path*} ('public')
     * alors que la liste des rapports (route à permission précise photos.view) les
     * montrait déjà correctement.
     */
    public function testPublicRoutePassesWithUnlimitedScopeWhenRoleHasGlobalPermissionOnStoreScopedAssignment(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['photos.view']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn(['photos.view']);

        $request  = $this->makeRequest('public', ['id' => 10], [1]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertNull(json_decode($response->body(), true)['managed']);
    }

    /** Même scénario que ci-dessus mais pour une route sans permission déclarée du tout ($key = null). */
    public function testRouteWithoutPermissionDeclaredPassesWithUnlimitedScopeForGlobalPermissionOnRole(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['photos.view']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn(['photos.view']);

        $request  = $this->makeRequest(null, ['id' => 10], [1]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertNull(json_decode($response->body(), true)['managed']);
    }

    /**
     * Non-régression critique : le court-circuit "permission globale" ne doit jamais
     * contourner le refus d'une clé de permission PRÉCISE non accordée. Le rôle rend
     * employees.view globale (donc hasAnyGlobalPermissionGrant() = true), mais la route
     * exige stores.view, jamais accordée — doit toujours lever ForbiddenException.
     */
    public function testForbiddenWithoutTheDeclaredPermissionStaysForbiddenEvenWithUnrelatedGlobalPermission(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['employees.view']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn(['employees.view']);

        $this->expectException(ForbiddenException::class);
        $this->middleware->handle($this->makeRequest('stores.view', ['id' => 10], [3]), $this->next());
    }

    /** Sans aucune permission RBAC nulle part, la porte grossière redirige vers /employee. */
    public function testPublicRouteRedirectsToEmployeeWithoutAnyPermission(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([]);

        $request  = $this->makeRequest('public', ['id' => 10], null);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(302, $response->status());
    }

    public function testScopedByStoresView(): void
    {
        // Ex. admin.activity → 'stores.view' : un manager scopé sur un seul
        // store ne doit voir que le journal de ce store.
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 5, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['stores.view']);

        $request = $this->makeRequest('stores.view', ['id' => 10], [1, 3]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertSame([3], json_decode($response->body(), true)['managed']);
    }

    public function testForbiddenWithoutTheDeclaredPermission(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 5, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['shifts.view']);

        $this->expectException(ForbiddenException::class);
        $this->middleware->handle($this->makeRequest('stores.view', ['id' => 10], [3]), $this->next());
    }

    public function testOwnerBypassesPermissionCheck(): void
    {
        $request = $this->makeRequest('employees.view', ['id' => 1, 'is_admin' => 1], null);
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

        $request = $this->makeRequest('employees.view', ['id' => 10], [1, 2]);
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

        $this->middleware->handle($this->makeRequest('employees.view', ['id' => 10], [2]), $this->next());
    }

    public function testGlobalScopeGrantPassesWithoutNarrowing(): void
    {
        // Affectation de portée globale (rôle non-système, ex. futur "Auditor" global) :
        // aucune restriction par store pour CETTE permission, managed_store_ids = null
        // (pas un heuristique calculé ailleurs).
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 7, 'user_id' => 10, 'role_id' => 4, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->willReturn(['id' => 4, 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['employees.view']);

        $request = $this->makeRequest('employees.view', ['id' => 10], null);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertNull(json_decode($response->body(), true)['managed']);
    }

    /**
     * Un rôle store-scope (ex. Manager sur le store 3) peut accorder une
     * permission précise en portée globale (case "Toutes les boutiques"
     * cochée dans l'éditeur de rôle) : managed_store_ids devient null pour
     * CETTE permission uniquement, sans changer la portée de l'affectation.
     */
    public function testPerPermissionGlobalScopeOnRoleNarrowsToNullForThatKey(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['shifts.view']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn(['shifts.view']);

        $request  = $this->makeRequest('shifts.view', ['id' => 10], [3]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertNull(json_decode($response->body(), true)['managed']);
    }

    /** La même affectation reste restreinte à son store pour une permission non global-flaggée. */
    public function testPerPermissionGlobalScopeDoesNotLeakToOtherPermissionsOnTheSameRole(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['shifts.view', 'shifts.update']);
        $this->roles->method('getGlobalPermissionKeys')->with(2)->willReturn(['shifts.view']);

        $request  = $this->makeRequest('shifts.update', ['id' => 10], [3]);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertSame([3], json_decode($response->body(), true)['managed']);
    }

    // -------------------------------------------------------------------------
    // Règle 'membership' (bundle DailyReport) : accès en libre-service pour
    // tout membre du store ciblé (paramètre de route 'id'), sans permission
    // RBAC dédiée.
    // -------------------------------------------------------------------------

    public function testMembershipRuleAllowsAnyStoreMemberWithoutPermission(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([]);
        $this->storeUsers->method('findMembership')->with(1, 10)->willReturn(['store_id' => 1, 'user_id' => 10]);

        $request = $this->makeRequest(['perm' => 'daily_reports.create', 'membership' => true], ['id' => 10], null, ['id' => '1']);

        $this->assertSame(200, $this->middleware->handle($request, $this->next())->status());
    }

    /**
     * Ni membre du store ciblé, ni aucune permission RBAC nulle part : retombe sur la
     * porte grossière (ex-AdminMiddleware), qui redirige vers /employee — pas un 403,
     * cohérent avec le comportement d'origine où AdminMiddleware aurait de toute façon
     * intercepté cet utilisateur avant qu'il n'atteigne la vérification de permission.
     */
    public function testMembershipRuleRejectsNonMemberWithoutPermission(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([]);
        $this->storeUsers->method('findMembership')->with(1, 10)->willReturn(null);

        $request  = $this->makeRequest(['perm' => 'daily_reports.create', 'membership' => true], ['id' => 10], null, ['id' => '1']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(302, $response->status());
    }

    public function testMembershipRuleStillGrantsViaScopedPermissionAndNarrows(): void
    {
        // Un gestionnaire avec daily_reports.create sur le store passe par la
        // clé RBAC normale (managed_store_ids narrowé), sans consulter l'adhésion.
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['daily_reports.create']);
        $this->storeUsers->expects($this->never())->method('findMembership');

        $request  = $this->makeRequest(['perm' => 'daily_reports.create', 'membership' => true], ['id' => 10], null, ['id' => '1']);
        $response = $this->middleware->handle($request, function (Request $r) {
            return Response::json(['managed' => $r->getAttribute('managed_store_ids')]);
        });

        $this->assertSame(200, $response->status());
        $this->assertSame([1], json_decode($response->body(), true)['managed']);
    }

    public function testNonMembershipRuleNeverConsultsMembershipAndRedirectsWithoutAnyPermission(): void
    {
        // Une règle sans 'membership' n'a pas d'exception libre-service (ex. admin.daily_reports.delete).
        // Aucune permission nulle part → porte grossière → /employee (pas de findMembership consulté).
        $this->assignments->method('findByUser')->with(10)->willReturn([]);
        $this->storeUsers->expects($this->never())->method('findMembership');

        $request  = $this->makeRequest('daily_reports.delete', ['id' => 10], null, ['id' => '1']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(302, $response->status());
    }

    /** Une permission RBAC existe ailleurs (porte grossière franchie) mais pas la clé exacte requise : 403. */
    public function testNonMembershipRuleForbiddenWhenSomePermissionExistsButNotTheRequiredOne(): void
    {
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['photos.view']);
        $this->storeUsers->expects($this->never())->method('findMembership');

        $request = $this->makeRequest('daily_reports.delete', ['id' => 10], null, ['id' => '1']);

        $this->expectException(ForbiddenException::class);
        $this->middleware->handle($request, $this->next());
    }
}
