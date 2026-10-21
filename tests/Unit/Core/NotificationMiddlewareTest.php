<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Middleware\NotificationMiddleware;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

/**
 * Couvre push_web_config, partagé à toutes les vues par ce middleware : vide tant
 * que PUSH_FCM_WEB_VAPID_KEY n'est pas configurée (config/push.php), pour que
 * push.js/le bouton "Activer" du profil ne s'affichent nulle part sur une instance
 * qui n'a pas mis en place le web push — voir aussi IcalService pour le même
 * principe de "no-op tant que non configuré" côté iCal.
 */
final class NotificationMiddlewareTest extends TestCase
{
    private Container $container;
    private ViewRenderer $view;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $this->container = new Container();
        $this->view       = new ViewRenderer(sys_get_temp_dir());
        $this->container->instance(ViewRenderer::class, $this->view);

        foreach (['PUSH_FCM_ENABLED', 'PUSH_FCM_PROJECT_ID', 'PUSH_FCM_WEB_API_KEY', 'PUSH_FCM_WEB_APP_ID', 'PUSH_FCM_WEB_VAPID_KEY'] as $key) {
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        foreach (['PUSH_FCM_ENABLED', 'PUSH_FCM_PROJECT_ID', 'PUSH_FCM_WEB_API_KEY', 'PUSH_FCM_WEB_APP_ID', 'PUSH_FCM_WEB_VAPID_KEY'] as $key) {
            unset($_ENV[$key]);
        }
    }

    private function bindAuth(?array $user): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($user);
        $roleAssignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);

        $auth = new AuthService(
            $users,
            $this->createStub(StoreUserRepositoryInterface::class),
            $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
            $roleAssignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        );
        $this->container->instance(AuthService::class, $auth);

        if ($user !== null) {
            $_SESSION['auth_user_id'] = $user['id'];
        }
    }

    private function bindNotifications(): void
    {
        $repo = $this->createStub(NotificationRepositoryInterface::class);
        $repo->method('countUnread')->willReturn(0);
        $repo->method('findByUser')->willReturn([]);
        $this->container->instance(NotificationRepositoryInterface::class, $repo);
    }

    private function noop(): \Closure
    {
        return fn(Request $req) => Response::json(['ok' => true]);
    }

    public function testPushWebConfigEmptyWhenAnonymous(): void
    {
        $_ENV['PUSH_FCM_PROJECT_ID']      = 'proj';
        $_ENV['PUSH_FCM_WEB_VAPID_KEY']   = 'vapid-key';
        $this->bindAuth(null);
        $this->bindNotifications();
        $middleware = new NotificationMiddleware($this->container);

        $middleware->handle(new Request(), $this->noop());

        $this->assertSame([], $this->view->get('push_web_config'));
    }

    public function testPushWebConfigEmptyWhenVapidKeyNotConfigured(): void
    {
        $_ENV['PUSH_FCM_PROJECT_ID'] = 'proj';
        // PUSH_FCM_WEB_VAPID_KEY volontairement absente.
        $this->bindAuth(['id' => 7]);
        $this->bindNotifications();
        $middleware = new NotificationMiddleware($this->container);

        $middleware->handle(new Request(), $this->noop());

        $this->assertSame([], $this->view->get('push_web_config'));
    }

    public function testPushWebConfigPopulatedWhenAuthenticatedAndConfigured(): void
    {
        $_ENV['PUSH_FCM_PROJECT_ID']    = 'my-project';
        $_ENV['PUSH_FCM_WEB_API_KEY']   = 'api-key-123';
        $_ENV['PUSH_FCM_WEB_APP_ID']    = 'app-id-456';
        $_ENV['PUSH_FCM_WEB_VAPID_KEY'] = 'vapid-key-789';
        $this->bindAuth(['id' => 7]);
        $this->bindNotifications();
        $middleware = new NotificationMiddleware($this->container);

        $middleware->handle(new Request(), $this->noop());

        $this->assertSame([
            'project_id' => 'my-project',
            'api_key'    => 'api-key-123',
            'app_id'     => 'app-id-456',
            'vapid_key'  => 'vapid-key-789',
        ], $this->view->get('push_web_config'));
    }

    public function testDegradesGracefullyWhenNotificationRepositoryThrows(): void
    {
        $this->bindAuth(['id' => 7]);
        $this->container->instance(NotificationRepositoryInterface::class, new class implements NotificationRepositoryInterface {
            public function findById(int $id): ?array { throw new \RuntimeException('table absente'); }
            public function findByUser(int $userId, int $limit = 20, int $offset = 0): array { throw new \RuntimeException('table absente'); }
            public function countByUser(int $userId): int { throw new \RuntimeException('table absente'); }
            public function findAfterId(int $userId, int $afterId, int $limit = 50): array { throw new \RuntimeException('table absente'); }
            public function countUnread(int $userId): int { throw new \RuntimeException('table absente'); }
            public function save(array $data): array { throw new \RuntimeException('table absente'); }
            public function markRead(int $id, int $userId): void { throw new \RuntimeException('table absente'); }
            public function markAllRead(int $userId): void { throw new \RuntimeException('table absente'); }
            public function markReadByReference(int $userId, string $referenceType, array $referenceIds): void { throw new \RuntimeException('table absente'); }
            public function delete(int $id): int { throw new \RuntimeException('table absente'); }
            public function deleteAllForUser(int $userId): void { throw new \RuntimeException('table absente'); }
        });
        $middleware = new NotificationMiddleware($this->container);

        $response = $middleware->handle(new Request(), $this->noop());

        $this->assertSame(200, $response->status());
        $this->assertSame(0, $this->view->get('unread_notifications_count'));
    }

    /** Le curseur de départ du polling est l'id de la plus récente notification (la première du dropdown). */
    public function testSharesLastIdOfNewestNotification(): void
    {
        $this->bindAuth(['id' => 7]);
        $repo = $this->createStub(NotificationRepositoryInterface::class);
        $repo->method('countUnread')->willReturn(2);
        $repo->method('findByUser')->willReturn([['id' => 42, 'type' => 't'], ['id' => 40, 'type' => 't']]);
        $this->container->instance(NotificationRepositoryInterface::class, $repo);

        (new NotificationMiddleware($this->container))->handle(new Request(), $this->noop());

        $this->assertSame(42, $this->view->get('notifications_last_id'));
        $this->assertSame(2, $this->view->get('unread_notifications_count'));
    }

    /**
     * Le poll de la cloche (toutes les 15 s) et les autres requêtes AJAX ne rendent pas la topbar : le middleware
     * ne doit pas y refaire ses requêtes.
     */
    public function testSkipsAjaxRequests(): void
    {
        $this->bindAuth(['id' => 7]);
        $repo = $this->createMock(NotificationRepositoryInterface::class);
        $repo->expects($this->never())->method('countUnread');
        $repo->expects($this->never())->method('findByUser');
        $this->container->instance(NotificationRepositoryInterface::class, $repo);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        try {
            $response = (new NotificationMiddleware($this->container))->handle(new Request(), $this->noop());
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        $this->assertSame(200, $response->status());
    }
}
