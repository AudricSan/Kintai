<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
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
use kintai\Core\Services\ImageCompressionService;
use kintai\UI\Controller\Web\AuthController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class AuthControllerAvatarTest extends TestCase
{
    private AuthController $controller;
    private $users;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user_id'] = 9;

        $this->users = $this->createMock(UserRepositoryInterface::class);

        $storeUsers      = $this->createMock(StoreUserRepositoryInterface::class);
        $stores          = $this->createMock(StoreRepositoryInterface::class);
        $roles           = $this->createMock(RoleRepositoryInterface::class);
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);
        $rememberTokens = $this->createStub(RememberTokenRepositoryInterface::class);

        $auth = new AuthService($this->users, $storeUsers, $stores, $roles, $roleAssignments, $rememberTokens);

        $this->controller = new AuthController(
            new ViewRenderer(sys_get_temp_dir()),
            $auth,
            new AuditLogger(),
            $this->users,
            $stores,
            $storeUsers,
            $this->createMock(IcalTokenRepositoryInterface::class),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
            $this->createMock(AvailabilityRepositoryInterface::class),
            $this->createMock(LanguageRepositoryInterface::class),
            new ImageCompressionService(),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    public function testUploadAvatarRedirectsWithErrorWhenNoFileProvided(): void
    {
        $this->users->method('findById')->with(9)->willReturn(['id' => 9]);

        $response = $this->controller->uploadAvatar(new Request());

        $this->assertSame(302, $response->status());
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertStringContainsString('error=avatar_invalid', $headersRef->getValue($response)['Location'] ?? '');
    }

    public function testRemoveAvatarRedirectsWithoutTouchingAnythingWhenNoAvatarSet(): void
    {
        $this->users->method('findById')->with(9)->willReturn(['id' => 9, 'avatar_path' => null]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->removeAvatar(new Request());

        $this->assertSame(302, $response->status());
    }

    public function testAvatarThrowsNotFoundWhenTargetHasNoPicture(): void
    {
        $this->users->method('findById')->with(42)->willReturn(['id' => 42, 'avatar_path' => null]);

        $request = new Request();
        $request->setRouteParams(['user_id' => '42']);

        $this->expectException(NotFoundException::class);
        $this->controller->avatar($request);
    }

    public function testAvatarThrowsNotFoundForUnknownUser(): void
    {
        $this->users->method('findById')->with(999)->willReturn(null);

        $request = new Request();
        $request->setRouteParams(['user_id' => '999']);

        $this->expectException(NotFoundException::class);
        $this->controller->avatar($request);
    }
}
