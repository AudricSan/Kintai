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
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Router;
use kintai\UI\Controller\Web\NotificationController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class NotificationControllerTest extends TestCase
{
    private NotificationRepositoryInterface&Stub $notifications;
    private DevicePushTokenRepositoryInterface&Stub $pushTokens;
    private RoleAssignmentRepositoryInterface&Stub $roleAssignments;
    private AuthService $auth;
    private ?NotificationController $controller = null;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user_id'] = 5;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'employee@example.com']);

        $this->roleAssignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roleAssignments->method('findByUser')->willReturn([]);

        $this->auth = new AuthService(
            $users,
            $this->createStub(StoreUserRepositoryInterface::class),
            $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
            $this->roleAssignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        );

        $this->notifications = $this->createStub(NotificationRepositoryInterface::class);
        $this->pushTokens    = $this->createStub(DevicePushTokenRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): NotificationController
    {
        if ($this->controller === null) {
            $this->controller = new NotificationController(
                $this->auth,
                $this->notifications,
                $this->pushTokens,
                new ViewRenderer(dirname(__DIR__, 4) . '/src/UI/View'),
            );
        }

        return $this->controller;
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
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->once())->method('deleteAllForUser')->with(5);

        $response = $this->controller()->deleteAll($this->makeRequest());

        $this->assertSame(302, $response->status());
    }

    public function testRedirectsKeepTheSubfolderPrefixWhenInstalledInASubfolder(): void
    {
        $previous = $_SERVER['SCRIPT_NAME'] ?? null;
        $_SERVER['SCRIPT_NAME'] = '/Kintai/index.php';
        try {
            $response = $this->controller()->deleteAll($this->makeRequest());
        } finally {
            $previous === null ? $_SERVER['SCRIPT_NAME'] = '/index.php' : $_SERVER['SCRIPT_NAME'] = $previous;
        }

        $location = (string) ((fn() => $this->headers['Location'] ?? '')->call($response));
        $this->assertSame('/Kintai/notifications', $location);
    }

    public function testDeleteAllReturnsJsonForAjaxRequests(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->once())->method('deleteAllForUser')->with(5);

        $response = $this->controller()->deleteAll($this->makeRequest(ajax: true));

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // pushSubscribe() / pushUnsubscribe()
    // -------------------------------------------------------------------------

    public function testPushSubscribeSavesTokenForCurrentUserAsWebPlatform(): void
    {
        $this->pushTokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->pushTokens->expects($this->once())->method('save')->with([
            'user_id'  => 5,
            'token'    => 'fcm-token-abc',
            'platform' => 'web',
        ]);

        $response = $this->controller()->pushSubscribe($this->makeJsonRequest(['token' => 'fcm-token-abc']));

        $this->assertSame(200, $response->status());
    }

    public function testPushSubscribeRejectsEmptyToken(): void
    {
        $this->pushTokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->pushTokens->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->pushSubscribe($this->makeJsonRequest(['token' => '']));
    }

    public function testPushUnsubscribeDeletesToken(): void
    {
        $this->pushTokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->pushTokens->expects($this->once())->method('deleteByToken')->with('fcm-token-abc', $this->callback(fn($uid) => is_int($uid) && $uid > 0));

        $response = $this->controller()->pushUnsubscribe($this->makeJsonRequest(['token' => 'fcm-token-abc']));

        $this->assertSame(200, $response->status());
    }

    /** Audit n°7 : la désinscription ne doit retirer que l'appareil de l'utilisateur connecté (id 5 ici). */
    public function testPushUnsubscribeIsScopedToTheLoggedInUser(): void
    {
        $this->pushTokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->pushTokens->expects($this->once())->method('deleteByToken')->with('fcm-token-abc', 5);

        $this->controller()->pushUnsubscribe($this->makeJsonRequest(['token' => 'fcm-token-abc']));
    }

    public function testPushUnsubscribeIsNoopWithoutToken(): void
    {
        $this->pushTokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->pushTokens->expects($this->never())->method('deleteByToken');

        $response = $this->controller()->pushUnsubscribe($this->makeJsonRequest([]));

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
    /** Audit n°7 : la destination postée n'était pas filtrée (redirection ouverte). */
    #[\PHPUnit\Framework\Attributes\DataProvider('markReadRedirects')]
    public function testMarkReadOnlyRedirectsToInternalPaths(string $posted, string $expectedSuffix): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/notifications/3/read', 'SCRIPT_NAME' => '/index.php'];
        $_POST   = ['redirect' => $posted];
        $req = new \kintai\Core\Request();
        $req->setRouteParams(['id' => '3']);

        $response = $this->controller()->markRead($req);

        $location = (string) ((fn() => $this->headers['Location'] ?? '')->call($response));
        $this->assertSame(302, $response->status());
        $this->assertStringEndsWith($expectedSuffix, $location);
        $this->assertStringNotContainsString('evil.example', $location);
    }

    /** @return array<string, array{string, string}> */
    public static function markReadRedirects(): array
    {
        return [
            'chemin interne'            => ['/employee/shifts', '/employee/shifts'],
            'site externe'              => ['https://evil.example/', '/notifications'],
            'origine relative au schéma' => ['//evil.example/', '/notifications'],
            'antislash'                 => ['/\evil.example/', '/notifications'],
            'vide'                      => ['', '/notifications'],
        ];
    }

    public function testOpenMarksReadAndRedirectsToStoredLink(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn([
            'id' => 10, 'user_id' => 5, 'link' => '/employee/shifts/day?start=2026-08-03',
        ]);
        $this->notifications->expects($this->once())->method('markRead')->with(10, 5);

        $response = $this->controller()->open($this->makeGetRequest(10));

        $this->assertSame(302, $response->status());
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/employee/shifts/day?start=2026-08-03', $headersRef->getValue($response)['Location'] ?? null);
    }

    public function testOpenFallsBackToNotificationsListWhenNoLink(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 5, 'link' => null]);
        $this->notifications->expects($this->once())->method('markRead')->with(10, 5);

        $response = $this->controller()->open($this->makeGetRequest(10));

        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/notifications', $headersRef->getValue($response)['Location'] ?? null);
    }

    /** Un lien stocké absolu/protocole-relatif (jamais généré par ce code, mais défensif) est ignoré. */
    public function testOpenIgnoresProtocolRelativeLink(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 5, 'link' => '//evil.example.com']);
        $this->notifications->method('markRead');

        $response = $this->controller()->open($this->makeGetRequest(10));

        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertSame('/notifications', $headersRef->getValue($response)['Location'] ?? null);
    }

    public function testOpenThrowsNotFoundForAnotherUsersNotification(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'user_id' => 999, 'link' => null]);
        $this->notifications->expects($this->never())->method('markRead');

        $this->expectException(\kintai\Core\Exceptions\NotFoundException::class);
        $this->controller()->open($this->makeGetRequest(10));
    }

    public function testOpenThrowsNotFoundWhenNotificationDoesNotExist(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(null);

        $this->expectException(\kintai\Core\Exceptions\NotFoundException::class);
        $this->controller()->open($this->makeGetRequest(10));
    }

    // -------------------------------------------------------------------------
    // poll() — curseur par id, panneau rafraîchi seulement si quelque chose a changé
    // -------------------------------------------------------------------------

    private function bindRouter(): void
    {
        $router = new Router();
        foreach (['notifications.open' => '/notifications/{id}/open', 'notifications.read_all' => '/notifications/read-all',
                  'notifications.delete_all' => '/notifications/delete-all', 'notifications.index' => '/notifications'] as $name => $path) {
            $router->get($path, [\stdClass::class, 'x'], name: $name);
        }
        Container::getInstance()->instance(Router::class, $router);
    }

    private function makePollRequest(array $query): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        $_GET = $query;
        return new Request();
    }

    private function json(\kintai\Core\Response $response): array
    {
        return json_decode($response->body(), true);
    }

    public function testPollAsksRepositoryForRowsAfterTheCursorAndAdvancesIt(): void
    {
        $this->bindRouter();
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->expects($this->once())->method('findAfterId')->with(5, 40, 50)->willReturn([
            ['id' => 41, 'type' => 'shift_assigned', 'body' => 'a', 'is_read' => 0],
            ['id' => 43, 'type' => 'shift_assigned', 'body' => 'déjà lue', 'is_read' => 1],
        ]);
        $this->notifications->method('countUnread')->willReturn(1);
        $this->notifications->method('findByUser')->willReturn([]);

        try {
            $data = $this->json($this->controller()->poll($this->makePollRequest(['after_id' => '40', 'count' => '0'])));
        } finally {
            $_GET = [];
        }

        $this->assertSame(43, $data['last_id']);
        $this->assertSame(1, $data['unread_count']);
        $this->assertSame('1', $data['badge']);
        // Seules les non lues donnent un toast.
        $this->assertSame([41], array_column($data['notifications'], 'id'));
        $this->assertSame('a', $data['notifications'][0]['body']);
        $this->assertArrayHasKey('panel_html', $data);
    }

    public function testPollOmitsPanelWhenNothingChanged(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->notifications->method('findAfterId')->willReturn([]);
        $this->notifications->method('countUnread')->willReturn(3);
        $this->notifications->expects($this->never())->method('findByUser');

        try {
            $data = $this->json($this->controller()->poll($this->makePollRequest(['after_id' => '40', 'count' => '3'])));
        } finally {
            $_GET = [];
        }

        $this->assertSame(40, $data['last_id']);
        $this->assertSame([], $data['notifications']);
        $this->assertArrayNotHasKey('panel_html', $data);
    }

    /** Une notification lue ailleurs (autre onglet, autre appareil) change le compteur : le panneau suit. */
    public function testPollReturnsPanelWhenUnreadCountDiffersFromTheBrowser(): void
    {
        $this->bindRouter();
        $this->notifications->method('findAfterId')->willReturn([]);
        $this->notifications->method('countUnread')->willReturn(0);
        $this->notifications->method('findByUser')->willReturn([
            ['id' => 40, 'type' => 'shift_assigned', 'body' => 'Shift <b>', 'is_read' => 1, 'created_at' => '2026-10-19 10:00:00'],
        ]);

        try {
            $data = $this->json($this->controller()->poll($this->makePollRequest(['after_id' => '40', 'count' => '2'])));
        } finally {
            $_GET = [];
        }

        $this->assertStringContainsString('notif-entry', $data['panel_html']);
        $this->assertStringContainsString('Shift &lt;b&gt;', $data['panel_html']);
        $this->assertStringNotContainsString('notif-entry--unread', $data['panel_html']);
    }
}
