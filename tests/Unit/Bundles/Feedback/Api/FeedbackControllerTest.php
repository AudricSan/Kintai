<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Feedback\Api;

use kintai\Bundles\Feedback\Controllers\Api\FeedbackController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit RBAC du 11/09/2026) : show/update/destroy ne re-vérifiaient
 * jamais que la ressource chargée appartenait à un store géré par l'appelant, et
 * index() sans filtre renvoyait TOUS les feedbacks de TOUS les stores à n'importe
 * quel porteur de token — voir le docblock de FeedbackController.
 */
final class FeedbackControllerTest extends TestCase
{
    private FeedbackRepositoryInterface&MockObject $feedbacks;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private FeedbackController $controller;

    protected function setUp(): void
    {
        $this->feedbacks   = $this->createMock(FeedbackRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);
        $this->controller  = new FeedbackController(
            $this->feedbacks,
            new PermissionService($this->assignments, $this->roles)
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    /** Utilisateur global (rôle système, ex. Owner) : aucune restriction de store. */
    private function requestAsOwner(): Request
    {
        $this->assignments->method('findByUser')->with(1)->willReturn([
            ['id' => 1, 'user_id' => 1, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->with(10)->willReturn(['id' => 10, 'is_system' => 1]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        return $req;
    }

    /** Manager avec feedbacks.* uniquement sur le store $storeId. */
    private function requestAsStoreManager(int $userId, int $storeId, array $keys = ['feedbacks.view', 'feedbacks.update', 'feedbacks.delete']): Request
    {
        $this->assignments->method('findByUser')->with($userId)->willReturn([
            ['id' => 1, 'user_id' => $userId, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => $storeId],
        ]);
        $this->roles->method('findById')->with(20)->willReturn(['id' => 20, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(20)->willReturn($keys);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    public function testIndexReturnsAllFeedbacksForGlobalRole(): void
    {
        $this->feedbacks->method('findAll')->willReturn([['id' => 1, 'store_id' => 5], ['id' => 2, 'store_id' => 9]]);

        $response = $this->controller->index($this->requestAsOwner());

        $body = json_decode($response->body(), true);
        $this->assertCount(2, $body['data']);
    }

    public function testIndexWithoutFilterExcludesOtherStoresForScopedManager(): void
    {
        $this->feedbacks->method('findAll')->willReturn([['id' => 1, 'store_id' => 5], ['id' => 2, 'store_id' => 9]]);

        $response = $this->controller->index($this->requestAsStoreManager(2, 5));

        $body = json_decode($response->body(), true);
        $this->assertCount(1, $body['data']);
        $this->assertSame(5, $body['data'][0]['store_id']);
    }

    public function testIndexFiltersByShiftId(): void
    {
        $_GET = ['shift_id' => '7'];

        $this->feedbacks->method('findByShift')->with(7)->willReturn(['id' => 3, 'shift_id' => 7, 'store_id' => 5]);

        $response = $this->controller->index($this->requestAsStoreManager(2, 5));
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
    }

    public function testShowThrowsNotFoundForMissingFeedback(): void
    {
        $this->feedbacks->method('findById')->with(99)->willReturn(null);

        $req = $this->requestAsOwner();
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->show($req);
    }

    public function testShowRejectsFeedbackFromAnotherStore(): void
    {
        $this->feedbacks->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->show($req);
    }

    public function testDestroyDeletesExistingFeedbackInManagedStore(): void
    {
        $this->feedbacks->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 5]);
        $this->feedbacks->expects($this->once())->method('delete')->with(5);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyRejectsFeedbackFromAnotherStore(): void
    {
        $this->feedbacks->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->feedbacks->expects($this->never())->method('delete');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->destroy($req);
    }
}
