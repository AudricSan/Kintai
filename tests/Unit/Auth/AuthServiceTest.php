<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use kintai\Core\Auth\AuthService;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;

final class AuthServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private AuthService $auth;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['auth_user_id']);

        $this->users      = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores     = $this->createMock(StoreRepositoryInterface::class);

        $this->auth = new AuthService($this->users, $this->storeUsers, $this->stores);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function activeUser(int $id = 10, bool $isAdmin = false): array
    {
        return [
            'id'            => $id,
            'email'         => 'user@test.com',
            'is_active'     => 1,
            'is_admin'      => $isAdmin ? 1 : 0,
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'deleted_at'    => null,
        ];
    }

    // -------------------------------------------------------------------------
    // attempt()
    // -------------------------------------------------------------------------

    public function testAttemptSucceedsWithValidCredentials(): void
    {
        $user = $this->activeUser();
        $this->users->method('findByEmail')->with('user@test.com')->willReturn($user);

        $result = $this->auth->attempt('user@test.com', 'secret123');

        $this->assertTrue($result);
        $this->assertSame(10, $_SESSION['auth_user_id']);
    }

    public function testAttemptFailsForUnknownEmail(): void
    {
        $this->users->method('findByEmail')->willReturn(null);
        $this->assertFalse($this->auth->attempt('nobody@test.com', 'pass'));
    }

    public function testAttemptFailsForWrongPassword(): void
    {
        $user = $this->activeUser();
        $this->users->method('findByEmail')->willReturn($user);

        $this->assertFalse($this->auth->attempt('user@test.com', 'wrongpassword'));
    }

    public function testAttemptFailsForInactiveUser(): void
    {
        $user               = $this->activeUser();
        $user['is_active']  = 0;
        $this->users->method('findByEmail')->willReturn($user);

        $this->assertFalse($this->auth->attempt('user@test.com', 'secret123'));
    }

    public function testAttemptFailsForSoftDeletedUser(): void
    {
        $user                = $this->activeUser();
        $user['deleted_at']  = '2025-01-01 00:00:00';
        $this->users->method('findByEmail')->willReturn($user);

        $this->assertFalse($this->auth->attempt('user@test.com', 'secret123'));
    }

    // -------------------------------------------------------------------------
    // attemptByCode()
    // -------------------------------------------------------------------------

    public function testAttemptByCodeSucceeds(): void
    {
        $store = ['id' => 5, 'code' => 'STORE01'];
        $user  = array_merge($this->activeUser(), ['employee_code' => 'EMP001']);

        $this->stores->method('findByCode')->with('STORE01')->willReturn($store);
        $this->users->method('findByEmployeeCode')->with('EMP001')->willReturn($user);
        $this->storeUsers->method('findMembership')->with(5, 10)->willReturn(['role' => 'staff']);

        $result = $this->auth->attemptByCode('EMP001', 'STORE01', 'secret123');
        $this->assertTrue($result);
    }

    public function testAttemptByCodeFailsForUnknownStore(): void
    {
        $this->stores->method('findByCode')->willReturn(null);
        $this->assertFalse($this->auth->attemptByCode('EMP001', 'UNKNOWN', 'pass'));
    }

    public function testAttemptByCodeFailsForUnknownEmployee(): void
    {
        $this->stores->method('findByCode')->willReturn(['id' => 1, 'code' => 'S']);
        $this->users->method('findByEmployeeCode')->willReturn(null);

        $this->assertFalse($this->auth->attemptByCode('NOBODY', 'S', 'pass'));
    }

    public function testAttemptByCodeFailsWhenNotMember(): void
    {
        $store = ['id' => 5, 'code' => 'S'];
        $user  = $this->activeUser();
        $this->stores->method('findByCode')->willReturn($store);
        $this->users->method('findByEmployeeCode')->willReturn($user);
        $this->storeUsers->method('findMembership')->willReturn(null);

        $this->assertFalse($this->auth->attemptByCode('EMP', 'S', 'secret123'));
    }

    public function testAttemptByCodeNormalizesStoreCode(): void
    {
        // Code en minuscules doit être normalisé en majuscules
        $this->stores->expects($this->once())
            ->method('findByCode')
            ->with('STORE01')
            ->willReturn(null);

        $this->auth->attemptByCode('EMP', 'store01', 'pass');
    }

    // -------------------------------------------------------------------------
    // check()
    // -------------------------------------------------------------------------

    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse($this->auth->check());
    }

    public function testCheckReturnsTrueAfterLogin(): void
    {
        $user = $this->activeUser();
        $this->users->method('findByEmail')->willReturn($user);
        $this->auth->attempt('user@test.com', 'secret123');

        $this->assertTrue($this->auth->check());
    }

    // -------------------------------------------------------------------------
    // user()
    // -------------------------------------------------------------------------

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->auth->user());
    }

    public function testUserReturnsUserFromDatabase(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $user = $this->activeUser();
        $this->users->method('findById')->with(10)->willReturn($user);

        $result = $this->auth->user();
        $this->assertSame(10, $result['id']);
    }

    // -------------------------------------------------------------------------
    // logout()
    // -------------------------------------------------------------------------

    public function testLogoutClearsSession(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->auth->logout();
        $this->assertArrayNotHasKey('auth_user_id', $_SESSION);
    }

    public function testCheckReturnsFalseAfterLogout(): void
    {
        $user = $this->activeUser();
        $this->users->method('findByEmail')->willReturn($user);
        $this->auth->attempt('user@test.com', 'secret123');

        $this->auth->logout();
        $this->assertFalse($this->auth->check());
    }

    // -------------------------------------------------------------------------
    // isAdmin()
    // -------------------------------------------------------------------------

    public function testIsAdminReturnsFalseWhenNotLoggedIn(): void
    {
        $this->users->method('findById')->willReturn(null);
        $this->assertFalse($this->auth->isAdmin());
    }

    public function testIsAdminReturnsTrueForAdminUser(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, true));
        $this->assertTrue($this->auth->isAdmin());
    }

    public function testIsAdminReturnsFalseForRegularUser(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->assertFalse($this->auth->isAdmin());
    }

    // -------------------------------------------------------------------------
    // managedStoreIds()
    // -------------------------------------------------------------------------

    public function testManagedStoreIdsEmptyWhenNotLoggedIn(): void
    {
        $this->users->method('findById')->willReturn(null);
        $this->assertSame([], $this->auth->managedStoreIds());
    }

    public function testManagedStoreIdsEmptyForGlobalAdmin(): void
    {
        // Admin global → retourne [] (pas de restriction)
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, true));
        $this->assertSame([], $this->auth->managedStoreIds());
    }

    public function testManagedStoreIdsForManagerRole(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->storeUsers->method('findByUser')->with(10)->willReturn([
            ['store_id' => 1, 'role' => 'manager'],
            ['store_id' => 2, 'role' => 'staff'],
            ['store_id' => 3, 'role' => 'admin'],
        ]);

        $ids = $this->auth->managedStoreIds();
        sort($ids);
        $this->assertSame([1, 3], $ids); // seulement manager et admin
    }

    public function testManagedStoreIdsEmptyForStaffOnly(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->storeUsers->method('findByUser')->willReturn([
            ['store_id' => 1, 'role' => 'staff'],
        ]);

        $this->assertSame([], $this->auth->managedStoreIds());
    }

    // -------------------------------------------------------------------------
    // isManager()
    // -------------------------------------------------------------------------

    public function testIsManagerTrueForGlobalAdmin(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, true));
        $this->assertTrue($this->auth->isManager());
    }

    public function testIsManagerTrueForStoreManager(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->storeUsers->method('findByUser')->willReturn([
            ['store_id' => 1, 'role' => 'manager'],
        ]);
        $this->assertTrue($this->auth->isManager());
    }

    public function testIsManagerFalseForStaffOnly(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->storeUsers->method('findByUser')->willReturn([
            ['store_id' => 1, 'role' => 'staff'],
        ]);
        $this->assertFalse($this->auth->isManager());
    }
}
