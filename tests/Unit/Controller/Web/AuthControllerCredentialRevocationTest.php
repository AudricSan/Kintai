<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Auth\CredentialRevoker;
use kintai\Core\Repositories\ApiTokenRepositoryInterface;
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
 * Changer son mot de passe depuis le profil : la session courante reste valide, mais toutes les
 * autres sessions, les cookies « rester connecté » et les jetons d'API sont révoqués.
 * Supprimer son compte purge aussi ces identifiants persistants.
 */
final class AuthControllerCredentialRevocationTest extends TestCase
{
    private const USER_ID = 7;

    private UserRepositoryInterface&MockObject $users;
    private RememberTokenRepositoryInterface&MockObject $remember;
    private ApiTokenRepositoryInterface&MockObject $api;
    private AuthService $auth;
    private AuthController $controller;
    private string $currentPassword;
    /** @var array<string, mixed> */
    private array $row;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['auth_user_id'] = self::USER_ID;

        $this->currentPassword = bin2hex(random_bytes(8));
        $this->row = [
            'id'            => self::USER_ID,
            'email'         => 'emp@example.test',
            'is_active'     => 1,
            'deleted_at'    => null,
            'password_hash' => password_hash($this->currentPassword, PASSWORD_BCRYPT, ['cost' => 4]),
        ];

        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->users->method('findById')->willReturnCallback(fn() => $this->row);
        // Le dépôt persiste ce que le contrôleur sauvegarde, comme la vraie base.
        $this->users->method('save')->willReturnCallback(function (array $data) {
            $this->row = array_merge($this->row, $data);
            return $this->row;
        });

        $storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);

        $this->remember = $this->createMock(RememberTokenRepositoryInterface::class);
        $this->api      = $this->createMock(ApiTokenRepositoryInterface::class);

        $this->auth = new AuthService(
            $this->users,
            $storeUsers,
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(RoleRepositoryInterface::class),
            $roleAssignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        );

        $this->controller = new AuthController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->auth,
            new AuditLogger(),
            $this->users,
            $this->createMock(StoreRepositoryInterface::class),
            $storeUsers,
            $this->createMock(IcalTokenRepositoryInterface::class),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
            $this->createMock(AvailabilityRepositoryInterface::class),
            $this->createMock(LanguageRepositoryInterface::class),
            new AvatarImageOptimizer(),
            new CredentialRevoker($this->remember, $this->api),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    private function changePassword(string $current, string $new): void
    {
        $_POST = ['current_password' => $current, 'new_password' => $new, 'confirm_password' => $new];
        $this->controller->saveProfilePassword(new Request());
    }

    public function testPasswordChangeRevokesRememberCookiesAndApiTokens(): void
    {
        $this->remember->expects($this->once())->method('deleteByUserId')->with(self::USER_ID);
        $this->api->expects($this->once())->method('deleteByUserId')->with(self::USER_ID)->willReturn(0);

        $this->changePassword($this->currentPassword, bin2hex(random_bytes(8)));
    }

    public function testCurrentSessionSurvivesItsOwnPasswordChange(): void
    {
        // La session est « liée » à l'ancien mot de passe (comme après une connexion).
        $this->assertTrue($this->auth->check());

        $this->changePassword($this->currentPassword, bin2hex(random_bytes(8)));

        $this->assertTrue($this->auth->check());
    }

    public function testAnotherDeviceSessionIsRevokedByThePasswordChange(): void
    {
        $this->assertTrue($this->auth->check());
        $otherDevice = $_SESSION; // session d'un autre appareil, liée à l'ancien mot de passe

        $this->changePassword($this->currentPassword, bin2hex(random_bytes(8)));

        $_SESSION = $otherDevice;
        $this->assertFalse($this->auth->check());
    }

    public function testWrongCurrentPasswordRevokesNothing(): void
    {
        $this->remember->expects($this->never())->method('deleteByUserId');
        $this->api->expects($this->never())->method('deleteByUserId');

        $this->changePassword(bin2hex(random_bytes(8)), bin2hex(random_bytes(8)));
    }

    public function testAccountDeletionPurgesRememberCookiesAndApiTokens(): void
    {
        $this->remember->expects($this->once())->method('deleteByUserId')->with(self::USER_ID);
        $this->api->expects($this->once())->method('deleteByUserId')->with(self::USER_ID)->willReturn(0);

        $_POST = ['password' => $this->currentPassword];
        $this->controller->deleteAccount(new Request());
    }
}
