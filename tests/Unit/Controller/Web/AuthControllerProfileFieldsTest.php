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

/**
 * updateProfile() ne gérait jusqu'ici que la langue — il porte désormais aussi
 * les champs de coordonnées en auto-édition (phone/mobile_phone/postal_code/
 * address) et le profil enrichi du bundle TeamDirectory (bio/skills/
 * languages_spoken/hobbies/show_in_directory). Ce test vérifie que toutes ces
 * valeurs sont bien transmises telles quelles au repository.
 */
final class AuthControllerProfileFieldsTest extends TestCase
{
    private AuthController $controller;
    private $users;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userId = 7;
        $_SESSION['auth_user_id'] = $userId;

        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->users->method('findById')->with($userId)->willReturn([
            'id' => $userId, 'language' => 'fr', 'show_in_directory' => 1,
        ]);

        $storeUsers      = $this->createMock(StoreUserRepositoryInterface::class);
        $stores          = $this->createMock(StoreRepositoryInterface::class);
        $roles           = $this->createMock(RoleRepositoryInterface::class);
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);
        $rememberTokens = $this->createStub(RememberTokenRepositoryInterface::class);

        $auth = new AuthService($this->users, $storeUsers, $stores, $roles, $roleAssignments, $rememberTokens);

        $languages = $this->createMock(LanguageRepositoryInterface::class);
        $languages->method('findAllActive')->willReturn([['code' => 'fr']]);
        $languages->method('findDefault')->willReturn(['code' => 'fr']);

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
            $languages,
            new AvatarImageOptimizer(),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
        $_POST = [];
    }

    public function testPersistsContactAndEnrichedProfileFields(): void
    {
        $_POST = [
            'language'           => 'fr',
            'phone'              => '01 23 45 67 89',
            'mobile_phone'       => '06 00 00 00 00',
            'postal_code'        => '75001',
            'address'            => '1 rue de Test',
            'bio'                => 'Bonjour, je suis un test.',
            'skills'             => 'caisse, service client',
            'languages_spoken'   => 'français, anglais',
            'hobbies'            => 'lecture',
            'show_in_directory'  => '1',
            'share_email'        => '1',
            'share_phone'        => '1',
            'share_mobile_phone' => '1',
        ];

        $saved = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$saved) {
            $saved = $d;
            return $d;
        });

        $this->controller->updateProfile(new Request());

        $this->assertNotNull($saved);
        $this->assertSame('01 23 45 67 89', $saved['phone']);
        $this->assertSame('06 00 00 00 00', $saved['mobile_phone']);
        $this->assertSame('75001', $saved['postal_code']);
        $this->assertSame('1 rue de Test', $saved['address']);
        $this->assertSame('Bonjour, je suis un test.', $saved['bio']);
        $this->assertSame('caisse, service client', $saved['skills']);
        $this->assertSame('français, anglais', $saved['languages_spoken']);
        $this->assertSame('lecture', $saved['hobbies']);
        $this->assertSame(1, $saved['show_in_directory']);
        $this->assertSame(1, $saved['share_email']);
        $this->assertSame(1, $saved['share_phone']);
        $this->assertSame(1, $saved['share_mobile_phone']);
    }

    public function testShowInDirectoryDefaultsToDisabledWhenCheckboxUnchecked(): void
    {
        $_POST = ['language' => 'fr'];

        $saved = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$saved) {
            $saved = $d;
            return $d;
        });

        $this->controller->updateProfile(new Request());

        $this->assertSame(0, $saved['show_in_directory']);
    }

    public function testShareContactFlagsDefaultToDisabledWhenCheckboxesUnchecked(): void
    {
        $_POST = ['language' => 'fr'];

        $saved = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$saved) {
            $saved = $d;
            return $d;
        });

        $this->controller->updateProfile(new Request());

        $this->assertSame(0, $saved['share_email']);
        $this->assertSame(0, $saved['share_phone']);
        $this->assertSame(0, $saved['share_mobile_phone']);
    }
}
