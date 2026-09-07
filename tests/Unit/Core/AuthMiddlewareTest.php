<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\AuthService;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\FeatureManager;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\EmployeeStatsService;
use kintai\Core\Services\TranslationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * AuthMiddleware partage le helper de vue `user_can` pour TOUTES les pages
 * authentifiées (et non plus PermissionMiddleware, limité aux routes admin
 * mappées) : la navigation (sidebar, bottom-nav) doit refléter les mêmes
 * droits quelle que soit la page affichée.
 */
final class AuthMiddlewareTest extends TestCase
{
    private Container $container;
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->view      = new ViewRenderer(sys_get_temp_dir());
        $this->container->instance(ViewRenderer::class, $this->view);
        $this->container->instance(FeatureManager::class, new FeatureManager([]));

        $this->container->instance(TranslationService::class, new TranslationService(
            $this->createStub(TranslationRepositoryInterface::class),
            $this->createStub(LanguageRepositoryInterface::class),
        ));
        $this->container->instance(
            LanguageRepositoryInterface::class,
            $this->createStub(LanguageRepositoryInterface::class),
        );
        $this->container->instance(
            UserNavPrefsRepositoryInterface::class,
            $this->createStub(UserNavPrefsRepositoryInterface::class),
        );
        $this->container->instance(EmployeeStatsService::class, new EmployeeStatsService(
            $this->createStub(ShiftRepositoryInterface::class),
            $this->createStub(ShiftTypeRepositoryInterface::class),
            $this->createStub(UserShiftTypeRateRepositoryInterface::class),
            $this->createStub(StoreUserRepositoryInterface::class),
            $this->createStub(StoreRepositoryInterface::class),
        ));
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
    }

    /**
     * Câble AuthService + PermissionService : l'utilisateur 10 est connecté,
     * affecté au rôle 2 (portée store 1) accordant $permissions.
     */
    private function bindAuthenticatedUser(array $permissions, bool $isSystemRole = false): void
    {
        $_SESSION['auth_user_id'] = 10;

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturn(['id' => 10, 'display_name' => 'Test']);

        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 2, 'is_system' => $isSystemRole ? 1 : 0]);
        $roles->method('getPermissions')->willReturn($permissions);

        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            [
                'id'         => 1,
                'user_id'    => 10,
                'role_id'    => 2,
                'scope_type' => $isSystemRole ? 'global' : 'store',
                'scope_id'   => $isSystemRole ? null : 1,
            ],
        ]);

        $storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $stores     = $this->createStub(StoreRepositoryInterface::class);

        $this->container->instance(AuthService::class, new AuthService(
            $users, $storeUsers, $stores, $roles, $assignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        ));
        $this->container->instance(PermissionService::class, new PermissionService($assignments, $roles));
        $this->container->instance(StoreUserRepositoryInterface::class, $storeUsers);
        $this->container->instance(StoreRepositoryInterface::class, $stores);
    }

    private function handle(): Response
    {
        $middleware = new AuthMiddleware($this->container);
        return $middleware->handle(new Request(), fn(Request $r) => Response::html('ok'));
    }

    public function testSharesUserCanReflectingRolePermissions(): void
    {
        $this->bindAuthenticatedUser(['shifts.view', 'timeoff.view']);

        $this->handle();

        $can = $this->view->get('user_can');
        $this->assertIsCallable($can);
        $this->assertTrue($can('shifts.view'));
        $this->assertTrue($can('timeoff.view'));
        $this->assertFalse($can('employees.view'));
        $this->assertFalse($can('stores.delete'));
    }

    public function testOwnerSystemRoleGrantsEveryKey(): void
    {
        $this->bindAuthenticatedUser([], isSystemRole: true);

        $this->handle();

        $can = $this->view->get('user_can');
        $this->assertTrue($can('employees.view'));
        $this->assertTrue($can('stores.delete'));
    }

    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        unset($_SESSION['auth_user_id']);
        $users = $this->createStub(UserRepositoryInterface::class);
        $this->container->instance(AuthService::class, new AuthService(
            $users,
            $this->createStub(StoreUserRepositoryInterface::class),
            $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
            $this->createStub(RoleAssignmentRepositoryInterface::class),
            $this->createStub(RememberTokenRepositoryInterface::class),
        ));

        $response = $this->handle();

        $this->assertSame(302, $response->status());
        $this->assertNull($this->view->get('user_can'));
    }
}
