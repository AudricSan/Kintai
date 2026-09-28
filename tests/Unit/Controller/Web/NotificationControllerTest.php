<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\UI\Controller\Web\NotificationController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationControllerTest extends TestCase
{
    private NotificationRepositoryInterface&MockObject $notifications;
    private DevicePushTokenRepositoryInterface&MockObject $pushTokens;
    private RoleAssignmentRepositoryInterface&MockObject $roleAssignments;
    private AuthService $auth;
    private NotificationController $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user_id'] = 5;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'employee@example.com']);

        $this->roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roleAssignments->method('findByUser')->willReturn([]);

        $this->auth = new AuthService(
            $users,
            $this->createMock(StoreUserRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(RoleRepositoryInterface::class),
            $this->roleAssignments,
            $this->createMock(RememberTokenRepositoryInterface::class),
        );

        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->pushTokens    = $this->createMock(DevicePushTokenRepositoryInterface::class);

        $this->controller = new NotificationController(
            $this->auth,
            $this->notifications,
            $this->pushTokens,
            new ViewRenderer(sys_get_temp_dir()),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    private function makeRequest(bool $ajax = false): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        if ($ajax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        } else {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
        return new Request();
    }

    /** Simule un corps JSON (php://input non mockable en test unitaire) — même technique que Api/V1/AuthControllerTest. */
    private function makeJsonRequest(array $json): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        return $req;
    }

    /**
     * Régression : marquer comme lu ne retire pas les notifications de la liste,
     * il faut un moyen de tout supprimer d'un coup pour l'utilisateur courant.
     */
    public function testDeleteAllCallsRepositoryForCurrentUserOnly(): void
    {
        $this->notifications->expects($this->once())->method('deleteAllForUser')->with(5);

        $response = $this->controller->deleteAll($this->makeRequest());

        $this->assertSame(302, $response->status());
    }

    public function testDeleteAllReturnsJsonForAjaxRequests(): void
    {
        $this->notifications->expects($this->once())->method('deleteAllForUser')->with(5);

        $response = $this->controller->deleteAll($this->makeRequest(ajax: true));

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // pushSubscribe() / pushUnsubscribe()
    // -------------------------------------------------------------------------

    public function testPushSubscribeSavesTokenForCurrentUserAsWebPlatform(): void
    {
        $this->pushTokens->expects($this->once())->method('save')->with([
            'user_id'  => 5,
            'token'    => 'fcm-token-abc',
            'platform' => 'web',
        ]);

        $response = $this->controller->pushSubscribe($this->makeJsonRequest(['token' => 'fcm-token-abc']));

        $this->assertSame(200, $response->status());
    }

    public function testPushSubscribeRejectsEmptyToken(): void
    {
        $this->pushTokens->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller->pushSubscribe($this->makeJsonRequest(['token' => '']));
    }

    public function testPushUnsubscribeDeletesToken(): void
    {
        $this->pushTokens->expects($this->once())->method('deleteByToken')->with('fcm-token-abc');

        $response = $this->controller->pushUnsubscribe($this->makeJsonRequest(['token' => 'fcm-token-abc']));

        $this->assertSame(200, $response->status());
    }

    public function testPushUnsubscribeIsNoopWithoutToken(): void
    {
        $this->pushTokens->expects($this->never())->method('deleteByToken');

        $response = $this->controller->pushUnsubscribe($this->makeJsonRequest([]));

        $this->assertSame(200, $response->status());
    }
}
