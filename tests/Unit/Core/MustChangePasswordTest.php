<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\AuthService;
use kintai\Core\Auth\PasswordPolicy;
use kintai\Core\Container;
use kintai\Core\Middleware\MustChangePasswordMiddleware;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Audit du 30/09/2026 (point 8) : le mot de passe par défaut « 0000 » restait valable indéfiniment. Tant
 * que l'utilisateur le garde (ou garde un mot de passe de moins de 8 caractères), il ne doit atteindre que
 * son profil.
 */
final class MustChangePasswordTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $row;
    private AuthService $auth;
    private Container $container;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        $this->row = [
            'id'            => 7,
            'email'         => 'emp@example.test',
            'is_active'     => 1,
            'deleted_at'    => null,
            'password_hash' => $this->hash(PasswordPolicy::DEFAULT_PASSWORD),
        ];

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturnCallback(fn() => $this->row);
        $users->method('findByEmail')->willReturnCallback(fn() => $this->row);
        $roleAssignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);

        $this->auth = new AuthService(
            $users,
            $this->createStub(StoreUserRepositoryInterface::class),
            $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
            $roleAssignments,
            $this->createStub(RememberTokenRepositoryInterface::class),
        );

        $this->container = new Container();
        $this->container->instance(AuthService::class, $this->auth);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function request(string $uri, string $method = 'GET'): Request
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php'];
        $_GET = $_POST = $_COOKIE = $_FILES = [];

        return new Request();
    }

    private function through(string $uri, string $method = 'GET'): Response
    {
        $middleware = new MustChangePasswordMiddleware($this->container);

        return $middleware->handle($this->request($uri, $method), fn() => Response::json(['ok' => true]));
    }

    public function testDefaultPasswordMustBeChangedAfterLogin(): void
    {
        $this->assertTrue($this->auth->attempt('emp@example.test', '0000'));
        $this->assertTrue($this->auth->mustChangePassword());
    }

    public function testShortPasswordMustBeChangedAtNextLogin(): void
    {
        $this->row['password_hash'] = $this->hash('abc123');
        $this->assertTrue($this->auth->attempt('emp@example.test', 'abc123'));
        $this->assertTrue($this->auth->mustChangePassword());
    }

    public function testStrongPasswordIsNotAskedToChange(): void
    {
        $strong = bin2hex(random_bytes(6));
        $this->row['password_hash'] = $this->hash($strong);
        $this->assertTrue($this->auth->attempt('emp@example.test', $strong));
        $this->assertFalse($this->auth->mustChangePassword());
    }

    public function testSessionWithoutPlaintextStillDetectsDefaultPassword(): void
    {
        // Session ouverte sans passer par attempt() (cookie « rester connecté », ancienne session).
        $_SESSION['auth_user_id'] = 7;
        $this->assertTrue($this->auth->mustChangePassword());
    }

    public function testSessionWithoutPlaintextAcceptsOtherPassword(): void
    {
        $this->row['password_hash'] = $this->hash(bin2hex(random_bytes(6)));
        $_SESSION['auth_user_id'] = 7;
        $this->assertFalse($this->auth->mustChangePassword());
    }

    public function testNotLoggedInIsNeverAskedToChange(): void
    {
        $this->assertFalse($this->auth->mustChangePassword());
    }

    public function testChangingPasswordClearsTheRequirement(): void
    {
        $this->auth->attempt('emp@example.test', '0000');
        $this->assertTrue($this->auth->mustChangePassword());

        $this->row['password_hash'] = $this->hash(bin2hex(random_bytes(6)));
        $this->auth->refreshSessionAfterPasswordChange($this->row);

        $this->assertFalse($this->auth->mustChangePassword());
    }

    public function testAdminResettingToDefaultPasswordRevokesTheSession(): void
    {
        $strong = bin2hex(random_bytes(6));
        $this->row['password_hash'] = $this->hash($strong);
        $this->auth->attempt('emp@example.test', $strong);
        $this->assertFalse($this->auth->mustChangePassword());

        // Un admin remet « 0000 » : l'empreinte change, la session est révoquée (l'utilisateur devra
        // se reconnecter, et sera alors forcé de changer).
        $this->row['password_hash'] = $this->hash('0000');
        $this->assertFalse($this->auth->mustChangePassword());
        $this->assertFalse($this->auth->check());
    }

    public function testMiddlewareRedirectsToProfileWhenPasswordMustChange(): void
    {
        $this->auth->attempt('emp@example.test', '0000');

        $response = $this->through('/employee/shifts');

        $this->assertSame(302, $response->status());
        $this->assertStringEndsWith('/profile?tab=info', ((fn() => $this->headers['Location'] ?? '')->call($response)));
    }

    #[DataProvider('allowedPaths')]
    public function testMiddlewareLetsEssentialPathsThrough(string $uri, string $method): void
    {
        $this->auth->attempt('emp@example.test', '0000');

        $this->assertSame(200, $this->through($uri, $method)->status());
    }

    /** @return array<string, array{string, string}> */
    public static function allowedPaths(): array
    {
        return [
            'profil'                     => ['/profile', 'GET'],
            'changement de mot de passe' => ['/profile/password', 'POST'],
            'déconnexion'                => ['/logout', 'POST'],
            'photo de profil'            => ['/avatar/7', 'GET'],
            'asset'                      => ['/assets/css/app.css', 'GET'],
            'API à jeton'                => ['/api/v1/shifts', 'GET'],
            'service worker'             => ['/sw.js', 'GET'],
            'changement de langue'       => ['/lang/ja', 'GET'],
            'vue mobile/bureau'          => ['/switch-device', 'POST'],
        ];
    }

    public function testMiddlewareBlocksOtherProfileActionsAndAdminPages(): void
    {
        $this->auth->attempt('emp@example.test', '0000');

        foreach ([['/profile/delete', 'POST'], ['/profile/export', 'GET'], ['/admin/shifts/timeline', 'GET']] as [$uri, $method]) {
            $this->assertSame(302, $this->through($uri, $method)->status(), "$method $uri devrait être redirigé");
        }
    }

    public function testMiddlewareLeavesEveryoneElseAlone(): void
    {
        $strong = bin2hex(random_bytes(6));
        $this->row['password_hash'] = $this->hash($strong);
        $this->auth->attempt('emp@example.test', $strong);

        $this->assertSame(200, $this->through('/employee/shifts')->status());
    }

    public function testMiddlewareLeavesAnonymousVisitorsAlone(): void
    {
        $this->assertSame(200, $this->through('/employee/shifts')->status());
    }
}
