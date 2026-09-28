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

    // -------------------------------------------------------------------------
    // open() — clic sur une notification : marquer lu + rediriger vers sa cible
    // -------------------------------------------------------------------------

    private function makeGetRequest(int $id): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $req = new Request();
        $req->setRouteParams(['id' => $id]);
        return $req;
    }

    /**
     * Régression : une notification "nouveau shift" ne disait pas lequel et ne
     * menait nulle part. open() doit marquer lu puis rediriger vers le lien stocké
     * (voir NotificationService::notify()).
     */
    public function testOpenMarksReadAndRedirectsToStoredLink(): void
    {
        $this->notifications->method('findById')->with(10)->willReturn([
            'id' => 10, 'user_id' => 5, 'link' => '/employee/shifts/day?start=2026-08-03',
        ]);
        $this->notifications->expects($this->once())->method('markRead')->with(10, 5);

        $response = $this->controller->open($this->makeGetRequest(10));

        $this->assertSame(302, $response->status());
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/employee/shifts/day?start=2026-08-03', $headersRef->getValue($response)['Location'] ?? null);
    }

    public function testOpenFallsBackToNotificationsListWhenNoLink(): void
    {
        $this->notifications->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 5, 'link' => null]);
        $this->notifications->expects($this->once())->method('markRead')->with(10, 5);

        $response = $this->controller->open($this->makeGetRequest(10));

        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/notifications', $headersRef->getValue($response)['Location'] ?? null);
    }

    /** Un lien stocké absolu/protocole-relatif (jamais généré par ce code, mais défensif) est ignoré. */
    public function testOpenIgnoresProtocolRelativeLink(): void
    {
        $this->notifications->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 5, 'link' => '//evil.example.com']);
        $this->notifications->method('markRead');

        $response = $this->controller->open($this->makeGetRequest(10));

        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/notifications', $headersRef->getValue($response)['Location'] ?? null);
    }

    public function testOpenThrowsNotFoundForAnotherUsersNotification(): void
    {
        $this->notifications->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 999, 'link' => null]);
        $this->notifications->expects($this->never())->method('markRead');

        $this->expectException(\kintai\Core\Exceptions\NotFoundException::class);
        $this->controller->open($this->makeGetRequest(10));
    }

    public function testOpenThrowsNotFoundWhenNotificationDoesNotExist(): void
    {
        $this->notifications->method('findById')->with(10)->willReturn(null);

        $this->expectException(\kintai\Core\Exceptions\NotFoundException::class);
        $this->controller->open($this->makeGetRequest(10));
    }
}
