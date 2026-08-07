<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\DailyReport\Api;

use kintai\Bundles\DailyReport\Controllers\Api\DailyReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\DailyReportPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * ApiDailyReportController n'appelait jusqu'ici jamais DailyReportPermissionService :
 * show/update/destroy/submit/validate opéraient sur n'importe quel rapport de
 * n'importe quel store pour tout porteur de token authentifié. Ce test vérifie
 * qu'un simple membre du store (sans permission RBAC dédiée) ne peut agir que
 * sur son propre brouillon, et qu'un gestionnaire avec la permission adéquate
 * peut agir sur n'importe quel rapport du store.
 */
final class DailyReportControllerTest extends TestCase
{
    private DailyReportRepositoryInterface&MockObject $reports;
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private DailyReportController $controller;

    protected function setUp(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);

        $this->controller = new DailyReportController(
            $this->reports,
            $this->stores,
            $this->storeUsers,
            new DailyReportPermissionService(new PermissionService($this->assignments, $this->roles)),
        );

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
    }

    private function requestWithJson(array $json, array $routeParams = [], array $authUser = ['id' => 20]): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        $req->setRouteParams($routeParams);
        $req->setAttribute('auth_user', $authUser);
        return $req;
    }

    private function noPermissions(): void
    {
        $this->assignments->method('findByUser')->willReturn([]);
    }

    private function grant(string $key, int $userId = 20, int $storeId = 1): void
    {
        $this->assignments->method('findByUser')->with($userId)->willReturn([
            ['id' => 1, 'user_id' => $userId, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId],
        ]);
        $this->roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(5)->willReturn([$key]);
    }

    // -------------------------------------------------------------------------
    // update() — un membre sans permission ne peut éditer que son propre brouillon
    // -------------------------------------------------------------------------

    public function testMemberCannotUpdateColleaguesSubmittedReport(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller->update($req);

        $this->assertSame(403, $response->status());
    }

    public function testMemberCanUpdateOwnDraftReport(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'draft',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller->update($req);

        $this->assertSame(200, $response->status());
    }

    public function testManagerWithPermissionCanUpdateAnyReportInStore(): void
    {
        $this->grant('daily_reports.update');
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller->update($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // destroy() — exige daily_reports.delete, jamais de fallback libre-service
    // -------------------------------------------------------------------------

    public function testMemberCannotDeleteOwnDraftWithoutDeletePermission(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'draft', 'deleted_at' => null,
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('delete');

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->destroy($req);

        $this->assertSame(403, $response->status());
    }

    public function testManagerWithDeletePermissionCanDelete(): void
    {
        $this->grant('daily_reports.delete');
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted', 'deleted_at' => null,
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('delete')->with(42);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    // -------------------------------------------------------------------------
    // validate() — un manager avec daily_reports.approve peut valider n'importe
    // quel rapport soumis du store, un simple membre jamais
    // -------------------------------------------------------------------------

    public function testManagerWithApprovePermissionCanValidateAnySubmittedReport(): void
    {
        $this->grant('daily_reports.approve');
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->validate($req);

        $this->assertSame(200, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertSame('validated', $body['status']);
    }

    public function testMemberWithoutApprovePermissionCannotValidate(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->validate($req);

        $this->assertSame(403, $response->status());
    }

    // -------------------------------------------------------------------------
    // show() — sans permission, un membre ne voit que ses propres rapports
    // -------------------------------------------------------------------------

    public function testMemberCannotViewColleaguesReportWithoutPermission(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->show($req);

        $this->assertSame(403, $response->status());
    }

    public function testMemberCanViewOwnReport(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'submitted',
        ]);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller->show($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowThrowsNotFoundForMissingReport(): void
    {
        $this->noPermissions();
        $this->reports->method('findById')->with(999)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->show($this->requestWithJson([], ['id' => '999']));
    }

    // -------------------------------------------------------------------------
    // store() — réservé au détenteur de daily_reports.create, plus de libre-service membre
    // -------------------------------------------------------------------------

    public function testNonMemberCannotCreateReport(): void
    {
        $this->noPermissions();
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller->store($req);

        $this->assertSame(403, $response->status());
    }

    /**
     * Régression : le libre-service "tout membre du store peut créer son propre rapport" a
     * été retiré à la demande explicite du client — la création requiert désormais
     * daily_reports.create, même pour un simple membre du store. Voir CHANGELOG.
     */
    public function testStoreMemberWithoutCreatePermissionCannotCreateReport(): void
    {
        $this->noPermissions();
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller->store($req);

        $this->assertSame(403, $response->status());
    }

    /** daily_reports.create accordé seul (ex. à un employé spécifique) suffit à créer. */
    public function testMemberWithCreatePermissionCanCreateOwnReport(): void
    {
        $this->grant('daily_reports.create');
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 55]);

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller->store($req);

        $this->assertSame(201, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertSame(20, $body['author_id']);
    }
}
