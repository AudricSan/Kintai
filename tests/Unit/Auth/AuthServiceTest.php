<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use kintai\Core\Auth\AuthService;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;

final class AuthServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $roleAssignments;
    private AuthService $auth;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['auth_user_id']);

        $this->users           = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers      = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores          = $this->createMock(StoreRepositoryInterface::class);
        $this->roles           = $this->createMock(RoleRepositoryInterface::class);
        $this->roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);

        $this->auth = new AuthService($this->users, $this->storeUsers, $this->stores, $this->roles, $this->roleAssignments);
    }

    /** Simule une affectation Owner (portée globale, rôle système) pour cet utilisateur. */
    private function grantOwnerRole(int $userId): void
    {
        $this->roleAssignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => $uid === $userId ? [
                ['id' => 1, 'user_id' => $userId, 'role_id' => 1, 'scope_type' => 'global', 'scope_id' => null],
            ] : []
        );
        $this->roles->method('findById')->with(1)->willReturn(['id' => 1, 'slug' => 'owner', 'is_system' => 1]);
    }

    /** Simule une affectation store-scope à un rôle non-système accordant $permissions. */
    private function grantStoreRole(int $userId, int $storeId, int $roleId, array $permissions): void
    {
        $this->roleAssignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => $uid === $userId ? [
                ['id' => 2, 'user_id' => $userId, 'role_id' => $roleId, 'scope_type' => 'store', 'scope_id' => $storeId],
            ] : []
        );
        $this->roles->method('findById')->with($roleId)->willReturn(['id' => $roleId, 'slug' => 'manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->with($roleId)->willReturn($permissions);
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

    public function testIsAdminReturnsTrueForOwnerRoleAssignment(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantOwnerRole(10);
        $this->assertTrue($this->auth->isAdmin());
    }

    public function testIsAdminReturnsFalseForRegularUser(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->roleAssignments->method('findByUser')->willReturn([]);
        $this->assertFalse($this->auth->isAdmin());
    }

    public function testIsAdminIgnoresLegacyIsAdminColumnWithoutRoleAssignment(): void
    {
        // La colonne users.is_admin brute n'est plus la source de vérité —
        // seule une affectation role_assignments de portée globale à un rôle
        // système compte désormais.
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, true));
        $this->roleAssignments->method('findByUser')->willReturn([]);
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
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantOwnerRole(10);
        $this->assertSame([], $this->auth->managedStoreIds());
    }

    public function testManagedStoreIdsForRoleGrantingPermissions(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantStoreRole(10, 1, 2, ['employees.view']);

        $ids = $this->auth->managedStoreIds();
        $this->assertSame([1], $ids);
    }

    public function testManagedStoreIdsEmptyForRoleWithoutPermissions(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantStoreRole(10, 1, 3, []); // rôle sans aucune permission (ex. Employé)

        $this->assertSame([], $this->auth->managedStoreIds());
    }

    // -------------------------------------------------------------------------
    // isManager()
    // -------------------------------------------------------------------------

    public function testIsManagerTrueForGlobalAdmin(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantOwnerRole(10);
        $this->assertTrue($this->auth->isManager());
    }

    public function testIsManagerTrueForRoleGrantingPermissions(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantStoreRole(10, 1, 2, ['employees.view']);
        $this->assertTrue($this->auth->isManager());
    }

    public function testIsManagerFalseForRoleWithoutPermissions(): void
    {
        $_SESSION['auth_user_id'] = 10;
        $this->users->method('findById')->willReturn($this->activeUser(10, false));
        $this->grantStoreRole(10, 1, 3, []);
        $this->assertFalse($this->auth->isManager());
    }
}
