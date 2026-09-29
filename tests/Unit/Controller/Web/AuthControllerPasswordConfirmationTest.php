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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * saveProfilePassword() et deleteAccount() doivent toujours vérifier le mot de
 * passe actuel : un password_hash vide ne dispense plus de cette vérification
 * (password_verify() contre '' renvoie false, donc l'action est refusée).
 */
final class AuthControllerPasswordConfirmationTest extends TestCase
{
    private const USER_ID = 7;

    private UserRepositoryInterface&MockObject $users;
    private AuthController $controller;
    private string $rightPassword;
    private string $wrongPassword;
    private string $newPassword;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user_id'] = self::USER_ID;

        // Valeurs générées à l'exécution : aucun mot de passe en clair dans le dépôt.
        $this->rightPassword = bin2hex(random_bytes(8));
        $this->wrongPassword = bin2hex(random_bytes(8));
        $this->newPassword   = bin2hex(random_bytes(8));

        $this->users = $this->createMock(UserRepositoryInterface::class);

        $storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);

        $auth = new AuthService(
            $this->users,
            $storeUsers,
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(RoleRepositoryInterface::class),
            $roleAssignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        );

        $this->controller = new AuthController(
            new ViewRenderer(sys_get_temp_dir()),
            $auth,
            new AuditLogger(),
            $this->users,
            $this->createMock(StoreRepositoryInterface::class),
            $storeUsers,
            $this->createMock(IcalTokenRepositoryInterface::class),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
            $this->createMock(AvailabilityRepositoryInterface::class),
            $this->createMock(LanguageRepositoryInterface::class),
            new AvatarImageOptimizer(),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    public function testPasswordChangeRefusedWhenStoredHashIsEmpty(): void
    {
        $this->users->method('findById')->willReturn(['id' => self::USER_ID, 'password_hash' => '']);
        $this->users->expects($this->never())->method('save');

        $_POST = ['current_password' => $this->wrongPassword, 'new_password' => $this->newPassword, 'confirm_password' => $this->newPassword];
        $this->controller->saveProfilePassword(new Request());
    }

    public function testPasswordChangeRefusedWhenCurrentPasswordIsWrong(): void
    {
        $this->users->method('findById')->willReturn([
            'id' => self::USER_ID, 'password_hash' => password_hash($this->rightPassword, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $this->users->expects($this->never())->method('save');

        $_POST = ['current_password' => $this->wrongPassword, 'new_password' => $this->newPassword, 'confirm_password' => $this->newPassword];
        $this->controller->saveProfilePassword(new Request());
    }

    public function testPasswordChangeAcceptedWithTheRightCurrentPassword(): void
    {
        $this->users->method('findById')->willReturn([
            'id' => self::USER_ID, 'password_hash' => password_hash($this->rightPassword, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $this->users->expects($this->once())->method('save')->with($this->callback(
            fn(array $u): bool => password_verify($this->newPassword, (string) $u['password_hash']),
        ));

        $_POST = ['current_password' => $this->rightPassword, 'new_password' => $this->newPassword, 'confirm_password' => $this->newPassword];
        $this->controller->saveProfilePassword(new Request());
    }

    public function testAccountDeletionRefusedWhenStoredHashIsEmpty(): void
    {
        $this->users->method('findById')->willReturn(['id' => self::USER_ID, 'password_hash' => '']);
        $this->users->expects($this->never())->method('save');

        $_POST = ['password' => $this->wrongPassword];
        $this->controller->deleteAccount(new Request());
    }

    public function testAccountDeletionRefusedWhenPasswordIsWrong(): void
    {
        $this->users->method('findById')->willReturn([
            'id' => self::USER_ID, 'password_hash' => password_hash($this->rightPassword, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $this->users->expects($this->never())->method('save');

        $_POST = ['password' => $this->wrongPassword];
        $this->controller->deleteAccount(new Request());
    }
}
