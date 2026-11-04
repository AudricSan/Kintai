<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\AvatarImageOptimizer;
use kintai\UI\Controller\Web\AuthController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class AuthControllerLoginTest extends TestCase
{
    private ?AuthController $controller = null;
    private $users;
    private StoreUserRepositoryInterface $storeUsers;
    private StoreRepositoryInterface $stores;
    private RoleAssignmentRepositoryInterface $roleAssignments;
    private RoleRepositoryInterface $roles;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['auth_user_id']);

        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->roleAssignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createStub(RoleRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AuthController
    {
        if ($this->controller === null) {
            $rememberTokens = $this->createStub(RememberTokenRepositoryInterface::class);
            $auth = new AuthService($this->users, $this->storeUsers, $this->stores, $this->roles, $this->roleAssignments, $rememberTokens);
            $this->controller = new AuthController(
                new ViewRenderer(sys_get_temp_dir()),
                $auth,
                new AuditLogger(),
                $this->users,
                $this->stores,
                $this->storeUsers,
                $this->createStub(IcalTokenRepositoryInterface::class),
                $this->createStub(UserNavPrefsRepositoryInterface::class),
                $this->createStub(AvailabilityRepositoryInterface::class),
                $this->createStub(LanguageRepositoryInterface::class),
                new AvatarImageOptimizer(),
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    public function testSuccessfulLoginUpdatesLastLoginAt(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $user = [
            'id'            => 9,
            'email'         => 'employee@example.test',
            'password_hash' => password_hash('secret', PASSWORD_BCRYPT),
            'is_active'     => 1,
            'deleted_at'    => null,
        ];
        $this->users->expects($this->atLeastOnce())->method('findByEmail')->with('employee@example.test')->willReturn($user);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(9)->willReturn($user);

        $this->users->expects($this->once())
            ->method('save')
            ->with($this->callback(function (array $data): bool {
                return ($data['id'] ?? null) === 9
                    && !empty($data['last_login_at'])
                    && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['last_login_at']) === 1;
            }))
            ->willReturn($user);

        $_POST = [
            'login_mode' => 'email',
            'email'      => 'employee@example.test',
            'password'   => 'secret',
        ];
        $request = new Request();

        $response = $this->controller()->login($request);

        $this->assertSame(302, $response->status());
    }

    public function testSuccessfulAdminLoginRedirectsToCurrentWeekTimeline(): void
    {
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $user = [
            'id' => 9,
            'email' => 'admin@example.test',
            'password_hash' => password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]),
            'is_active' => 1,
            'is_admin' => 1,
            'deleted_at' => null,
        ];
        $this->users->method('findByEmail')->willReturn($user);
        $this->users->method('findById')->willReturn($user);
        $this->users->method('save')->willReturn($user);
        $this->roleAssignments->method('findByUser')->with(9)->willReturn([
            ['scope_type' => 'global', 'scope_id' => null, 'role_id' => 2],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 1]);
        $_POST = ['login_mode' => 'email', 'email' => 'admin@example.test', 'password' => 'secret'];

        $response = $this->controller()->login(new Request());
        $location = (string) ((fn() => $this->headers['Location'] ?? '')->call($response));

        $this->assertStringContainsString('/admin/shifts/timeline?start=' . date('Y-m-d') . '&view=week', $location);
    }

    public function testSuccessfulManagerLoginRedirectsToCurrentWeekTimeline(): void
    {
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $user = [
            'id' => 9,
            'email' => 'manager@example.test',
            'password_hash' => password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]),
            'is_active' => 1,
            'is_admin' => 0,
            'deleted_at' => null,
        ];
        $this->users->method('findByEmail')->willReturn($user);
        $this->users->method('findById')->willReturn($user);
        $this->users->method('save')->willReturn($user);
        $this->roleAssignments->method('findByUser')->with(9)->willReturn([
            ['scope_type' => 'store', 'scope_id' => 4, 'role_id' => 3],
        ]);
        $this->roles->method('findById')->with(3)->willReturn(['id' => 3, 'is_manager' => 1]);
        $_POST = ['login_mode' => 'email', 'email' => 'manager@example.test', 'password' => 'secret'];

        $response = $this->controller()->login(new Request());
        $location = (string) ((fn() => $this->headers['Location'] ?? '')->call($response));

        $this->assertStringContainsString('/admin/shifts/timeline?start=' . date('Y-m-d') . '&view=week', $location);
    }

    public function testFailedLoginFlagsTheRequestForTheThrottle(): void
    {
        $this->users->method('findByEmail')->willReturn(null);

        $_POST = ['login_mode' => 'email', 'email' => 'employee@example.test', 'password' => bin2hex(random_bytes(6))];
        $request = new Request();
        $this->controller()->login($request);

        // Lu par LoginThrottleMiddleware : seuls les échecs comptent.
        $this->assertTrue($request->getAttribute('auth_failed'));
    }

    public function testSuccessfulLoginDoesNotFlagTheRequest(): void
    {
        $password = bin2hex(random_bytes(6));
        $user = [
            'id'            => 9,
            'email'         => 'employee@example.test',
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'is_active'     => 1,
            'deleted_at'    => null,
        ];
        $this->users->method('findByEmail')->willReturn($user);
        $this->users->method('findById')->willReturn($user);
        $this->users->method('save')->willReturn($user);

        $_POST = ['login_mode' => 'email', 'email' => 'employee@example.test', 'password' => $password];
        $request = new Request();
        $this->controller()->login($request);

        $this->assertNull($request->getAttribute('auth_failed'));
    }

    public function testFailedLoginDoesNotTouchLastLoginAt(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->users->expects($this->atLeastOnce())->method('findByEmail')->with('employee@example.test')->willReturn(null);
        $this->users->expects($this->never())->method('save');

        $_POST = [
            'login_mode' => 'email',
            'email'      => 'employee@example.test',
            'password'   => 'wrong',
        ];
        $request = new Request();

        $response = $this->controller()->login($request);

        $this->assertSame(302, $response->status());
    }
}
