<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Container;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\JsonLanguageRepository;
use kintai\Core\Repositories\JsonTranslationRepository;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\IcalService;
use kintai\Core\Services\Log;
use kintai\Core\Services\TranslationService;
use kintai\UI\Controller\Web\IcalController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Audit n°7 : le flux iCal d'un compte désactivé ou ayant quitté le magasin ne doit plus être servi. */
final class IcalControllerFeedTest extends TestCase
{
    private IcalTokenRepositoryInterface&MockObject $tokens;
    private ShiftRepositoryInterface&MockObject $shifts;
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private IcalController $controller;

    protected function setUp(): void
    {
        $this->tokens     = $this->createMock(IcalTokenRepositoryInterface::class);
        $this->shifts     = $this->createMock(ShiftRepositoryInterface::class);
        $this->stores     = $this->createMock(StoreRepositoryInterface::class);
        $this->users      = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);

        $this->tokens->method('findByToken')->with('tok')->willReturn(['id' => 1, 'user_id' => 9, 'store_id' => 3]);
        $this->stores->method('findById')->with(3)->willReturn(['id' => 3, 'code' => 'ST']);
        $this->shifts->method('findByUser')->willReturn([]);
        $this->shifts->method('findRecentDeletionsByUserAndStore')->willReturn([]);

        // __() lit le TranslationService du Container (voir IcalServiceTest).
        $translations = new TranslationService(
            new JsonTranslationRepository(dirname(__DIR__, 4) . '/lang', dirname(__DIR__, 4) . '/src/Bundles'),
            new JsonLanguageRepository(dirname(__DIR__, 4) . '/lang'),
        );
        Container::getInstance()->instance(TranslationService::class, $translations);

        $this->controller = new IcalController(
            new IcalService($translations),
            $this->tokens,
            $this->shifts,
            $this->createMock(ShiftTypeRepositoryInterface::class),
            $this->stores,
            $this->createMock(TimeoffRequestRepositoryInterface::class),
            $this->users,
            new AuthService(
                $this->users,
                $this->storeUsers,
                $this->stores,
                $this->createMock(RoleRepositoryInterface::class),
                $this->createMock(RoleAssignmentRepositoryInterface::class),
                $this->createMock(RememberTokenRepositoryInterface::class),
            ),
            new AuditLogger(),
            $this->storeUsers,
        );
    }

    protected function tearDown(): void
    {
        Log::reset();
        // Retire le TranslationService lié dans setUp() : il fausserait les tests qui attendent des clés brutes.
        $instances = new \ReflectionProperty(Container::class, 'instances');
        $instances->setAccessible(true);
        $bound = $instances->getValue(Container::getInstance());
        unset($bound[TranslationService::class]);
        $instances->setValue(Container::getInstance(), $bound);
    }

    private function request(): Request
    {
        $req = new Request();
        $req->setRouteParams(['token' => 'tok', 'store_id' => '3']);
        return $req;
    }

    public function testFeedRefusesADeactivatedAccount(): void
    {
        $this->users->method('findById')->with(9)->willReturn(['id' => 9, 'is_active' => 0]);
        $this->storeUsers->method('findMembership')->willReturn(['id' => 1]);

        $this->expectException(NotFoundException::class);
        $this->controller->feed($this->request());
    }

    public function testFeedRefusesAnEmployeeWhoLeftTheStore(): void
    {
        $this->users->method('findById')->with(9)->willReturn(['id' => 9, 'is_active' => 1]);
        $this->storeUsers->method('findMembership')->with(3, 9)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->feed($this->request());
    }

    public function testFeedServesAnActiveMember(): void
    {
        $this->users->method('findById')->with(9)->willReturn(['id' => 9, 'is_active' => 1]);
        $this->storeUsers->method('findMembership')->with(3, 9)->willReturn(['id' => 1]);

        $this->assertSame(200, $this->controller->feed($this->request())->status());
    }
}
