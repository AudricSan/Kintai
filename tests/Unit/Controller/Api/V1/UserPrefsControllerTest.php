<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Auth\UserTargetGuard;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Tests\Support\ApiOwnerAuth;
use kintai\UI\Controller\Api\V1\UserPrefsController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Préférences d'affichage par l'API : listes de chaînes et type de tableau de bord contrôlés (audit M9). */
final class UserPrefsControllerTest extends TestCase
{
    private UserDashboardPrefsRepositoryInterface&MockObject $dashboard;
    private UserNavPrefsRepositoryInterface&MockObject $nav;
    private UserPrefsController $controller;

    protected function setUp(): void
    {
        $this->dashboard = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $this->nav       = $this->createMock(UserNavPrefsRepositoryInterface::class);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturn(['id' => 20]);
        $storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $storeUsers->method('findByUser')->willReturn([]);

        $this->controller = new UserPrefsController(
            $this->dashboard,
            $this->nav,
            new UserTargetGuard($users, $storeUsers, ApiOwnerAuth::permissions($this)),
        );
    }

    private function request(array $body): Request
    {
        $req = ApiOwnerAuth::owner(new Request());
        $req->setRouteParams(['user_id' => '20']);
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setValue($req, $body);
        return $req;
    }

    public function testDashboardWidgetsAreSaved(): void
    {
        $this->dashboard->expects($this->once())->method('saveWidgets')->with(20, ['stats', 'shifts'], 'employee');

        $response = $this->controller->saveDashboardPrefs($this->request(['dashboard_type' => 'employee', 'widgets' => ['stats', 'shifts']]));

        $this->assertSame(200, $response->status());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidDashboardPrefs(): array
    {
        return [
            'type inconnu'          => [['dashboard_type' => 'root', 'widgets' => []]],
            'widgets en texte'      => [['widgets' => 'stats']],
            'widgets non textuels'  => [['widgets' => [['nested' => true]]]],
        ];
    }

    #[DataProvider('invalidDashboardPrefs')]
    public function testInvalidDashboardPrefsAreRefused(array $body): void
    {
        $this->dashboard->expects($this->never())->method('saveWidgets');

        $this->expectException(ValidationException::class);
        $this->controller->saveDashboardPrefs($this->request($body));
    }

    public function testNavPrefsMustBeAListOfStrings(): void
    {
        $this->nav->expects($this->never())->method('saveHidden');

        $this->expectException(ValidationException::class);
        $this->controller->saveNavPrefs($this->request(['hidden' => ['a' => 'b']]));
    }
}
