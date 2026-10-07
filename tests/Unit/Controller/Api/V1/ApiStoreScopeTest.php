<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Auth\CredentialRevoker;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Auth\UserTargetGuard;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\ApiTokenRepositoryInterface;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\RememberTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\Log;
use kintai\Core\Services\PlanLimitService;
use kintai\Core\Services\StoreServiceInterface;
use kintai\UI\Controller\Api\V1\AvailabilityController;
use kintai\UI\Controller\Api\V1\IcalTokenController;
use kintai\UI\Controller\Api\V1\ShiftController;
use kintai\UI\Controller\Api\V1\StoreController;
use kintai\UI\Controller\Api\V1\UserController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026) : l'API du cœur ne bornait pas les routes `/{id}` au
 * magasin de la ressource — ApiPermissionMiddleware ne voit qu'un `store_id` fourni par le
 * client, et PermissionService::can(…, null) répond « oui » dès qu'une affectation accorde
 * la clé quelque part. Un gérant du magasin 5 pouvait donc modifier le magasin 6, réécrire
 * le mot de passe d'un Owner, ou écraser n'importe quelle ligne par un POST portant un `id`.
 */
final class ApiStoreScopeTest extends TestCase
{
    private const OWNER   = 1;
    private const MANAGER = 2;
    private const STORE_A = 5; // magasin du gérant
    private const STORE_B = 6; // autre magasin

    private PermissionService $permissions;
    private ShiftRepositoryInterface&MockObject $shifts;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;

    protected function setUp(): void
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturnCallback(fn(int $uid): array => match ($uid) {
            self::OWNER   => [['id' => 1, 'user_id' => self::OWNER, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null]],
            self::MANAGER => [['id' => 2, 'user_id' => self::MANAGER, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::STORE_A]],
            default       => [],
        });
        $assignments->method('findByScope')->willReturn([
            ['id' => 1, 'user_id' => self::OWNER, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);

        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturnCallback(fn(int $id): array => match ($id) {
            10      => ['id' => 10, 'is_system' => 1],
            default => ['id' => 20, 'is_system' => 0],
        });
        $roles->method('getPermissions')->willReturn([
            'shifts.view', 'shifts.create', 'shifts.update', 'shifts.delete',
            'employees.view', 'employees.update', 'employees.delete',
            'stores.view', 'stores.update',
        ]);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);

        $this->permissions = new PermissionService($assignments, $roles);
        $this->shifts      = $this->createMock(ShiftRepositoryInterface::class);
        $this->users       = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers  = $this->createMock(StoreUserRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    private function request(int $userId, array $params = [], ?array $json = null): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        $req->setRouteParams($params);
        if ($json !== null) {
            $ref = new \ReflectionProperty(Request::class, 'jsonBody');
            $ref->setValue($req, $json);
        }
        return $req;
    }

    private function userController(?CredentialRevoker $revoker = null): UserController
    {
        $planLimits = new PlanLimitService(
            $this->createStub(StoreRepositoryInterface::class),
            $this->users,
            new LicenseClientService($this->createStub(AppSettingsRepositoryInterface::class), ['base_url' => '', 'api_key' => '']),
        );
        return new UserController($this->users, new AuditLogger(), $planLimits, $this->permissions, $this->storeUsers, $revoker);
    }

    // ---------------------------------------------------------------- shifts

    public function testManagerCannotUpdateShiftOfAnotherStore(): void
    {
        $this->shifts->method('findById')->with(9)->willReturn(['id' => 9, 'store_id' => self::STORE_B]);
        $this->shifts->expects($this->never())->method('save');
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        // Le client déclare son propre magasin dans le corps pour passer la porte du middleware.
        $controller->update($this->request(self::MANAGER, ['id' => '9'], ['store_id' => self::STORE_A]));
    }

    public function testManagerCannotDeleteShiftOfAnotherStore(): void
    {
        $this->shifts->method('findById')->with(9)->willReturn(['id' => 9, 'store_id' => self::STORE_B]);
        $this->shifts->expects($this->never())->method('delete');
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->destroy($this->request(self::MANAGER, ['id' => '9']));
    }

    public function testManagerCannotMoveOwnShiftToAnotherStore(): void
    {
        $this->shifts->method('findById')->with(3)->willReturn(['id' => 3, 'store_id' => self::STORE_A]);
        $this->shifts->expects($this->never())->method('save');
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->update($this->request(self::MANAGER, ['id' => '3'], ['store_id' => self::STORE_B]));
    }

    public function testManagerCanUpdateShiftOfOwnStore(): void
    {
        $this->shifts->method('findById')->with(3)->willReturn(['id' => 3, 'store_id' => self::STORE_A]);
        $this->shifts->expects($this->once())->method('save')->willReturn(['id' => 3, 'store_id' => self::STORE_A]);
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $response = $controller->update($this->request(self::MANAGER, ['id' => '3'], ['note' => 'x']));

        $this->assertSame(200, $response->status());
    }

    public function testShiftCreationIgnoresIdFromBody(): void
    {
        $this->shifts->expects($this->once())->method('save')
            ->with($this->callback(fn(array $d): bool => !array_key_exists('id', $d) && $d['store_id'] === self::STORE_A))
            ->willReturn(['id' => 50, 'store_id' => self::STORE_A]);
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $response = $controller->store($this->request(self::MANAGER, [], ['id' => 9, 'store_id' => self::STORE_A]));

        $this->assertSame(201, $response->status());
    }

    public function testShiftCreationRequiresStoreId(): void
    {
        $this->shifts->expects($this->never())->method('save');
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $this->expectException(ValidationException::class);
        $controller->store($this->request(self::MANAGER, [], ['note' => 'sans magasin']));
    }

    public function testShiftCreationForAnotherStoreIsForbidden(): void
    {
        $this->shifts->expects($this->never())->method('save');
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(self::MANAGER, [], ['store_id' => self::STORE_B]));
    }

    public function testShiftIndexIsRestrictedToManagedStores(): void
    {
        $this->shifts->method('findByUser')->with(77)->willReturn([
            ['id' => 1, 'store_id' => self::STORE_A],
            ['id' => 2, 'store_id' => self::STORE_B],
        ]);
        $controller = new ShiftController($this->shifts, new AuditLogger(), $this->permissions);

        $response = $controller->index($this->requestWithQuery(self::MANAGER, ['user_id' => '77']));

        $ids = array_column(json_decode($response->body(), true)['data'], 'id');
        $this->assertSame([1], $ids);
    }

    private function requestWithQuery(int $userId, array $query): Request
    {
        $_GET = $query;
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    // --------------------------------------------------------- availabilities

    public function testAvailabilityCreationIgnoresIdAndChecksStore(): void
    {
        $availabilities = $this->createMock(AvailabilityRepositoryInterface::class);
        $availabilities->expects($this->never())->method('save');
        $controller = new AvailabilityController($availabilities, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(self::MANAGER, [], ['id' => 4, 'store_id' => self::STORE_B]));
    }

    // ----------------------------------------------------------------- stores

    public function testManagerCannotUpdateAnotherStore(): void
    {
        $stores = $this->createMock(StoreRepositoryInterface::class);
        $stores->method('findById')->with(self::STORE_B)->willReturn(['id' => self::STORE_B]);
        $stores->expects($this->never())->method('save');
        $controller = new StoreController($stores, $this->createStub(StoreServiceInterface::class), new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->update($this->request(self::MANAGER, ['id' => (string) self::STORE_B], ['name' => 'piraté']));
    }

    // ------------------------------------------------------------------ users

    public function testManagerCannotChangeOwnerPassword(): void
    {
        $this->users->method('findById')->with(self::OWNER)->willReturn(['id' => self::OWNER, 'email' => 'owner@example.com']);
        // L'Owner est (aussi) membre du magasin du gérant : seule la protection Owner doit le bloquer.
        $this->storeUsers->method('findByUser')->with(self::OWNER)->willReturn([['store_id' => self::STORE_A]]);
        $this->users->expects($this->never())->method('save');

        $this->expectException(ForbiddenException::class);
        $this->userController()->update($this->request(self::MANAGER, ['id' => (string) self::OWNER], ['password' => 'motdepasse-pirate']));
    }

    public function testManagerCannotUpdateEmployeeOfAnotherStore(): void
    {
        $this->users->method('findById')->with(30)->willReturn(['id' => 30]);
        $this->storeUsers->method('findByUser')->with(30)->willReturn([['store_id' => self::STORE_B]]);
        $this->users->expects($this->never())->method('save');

        $this->expectException(ForbiddenException::class);
        $this->userController()->update($this->request(self::MANAGER, ['id' => '30'], ['last_name' => 'X']));
    }

    public function testUpdateHashesPasswordNeverStoresRawHashAndRevokesCredentials(): void
    {
        $this->users->method('findById')->with(31)->willReturn(['id' => 31, 'is_active' => 1]);
        $this->storeUsers->method('findByUser')->with(31)->willReturn([['store_id' => self::STORE_A]]);
        $this->users->expects($this->once())->method('save')->with($this->callback(function (array $d): bool {
            return $d['id'] === 31
                && !array_key_exists('password', $d)
                && password_verify('nouveau-mot-de-passe', $d['password_hash']);
        }))->willReturn(['id' => 31, 'password_hash' => 'secret']);
        $remember = $this->createMock(RememberTokenRepositoryInterface::class);
        $remember->expects($this->once())->method('deleteByUserId')->with(31);
        $apiTokens = $this->createMock(ApiTokenRepositoryInterface::class);
        $apiTokens->expects($this->once())->method('deleteByUserId')->with(31);
        $revoker = new CredentialRevoker($remember, $apiTokens);

        $response = $this->userController($revoker)->update($this->request(
            self::MANAGER,
            ['id' => '31'],
            ['password' => 'nouveau-mot-de-passe', 'password_hash' => 'forgé', 'id' => 99]
        ));

        $this->assertArrayNotHasKey('password_hash', json_decode($response->body(), true));
    }

    public function testRawPasswordHashCannotBeInjectedOnUpdate(): void
    {
        $this->users->method('findById')->with(31)->willReturn(['id' => 31]);
        $this->storeUsers->method('findByUser')->with(31)->willReturn([['store_id' => self::STORE_A]]);
        $this->users->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('password_hash', $d)
        ))->willReturn(['id' => 31]);

        $this->userController()->update($this->request(self::MANAGER, ['id' => '31'], ['password_hash' => 'forgé']));
    }

    public function testUserCreationIgnoresIdAndSetsDefaultPassword(): void
    {
        $this->users->method('countActive')->willReturn(1);
        $this->users->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d) && !empty($d['password_hash'])
        ))->willReturn(['id' => 60, 'password_hash' => 'secret']);

        $response = $this->userController()->store($this->request(self::OWNER, [], ['id' => 1, 'email' => 'a@example.com']));

        $this->assertSame(201, $response->status());
        $this->assertArrayNotHasKey('password_hash', json_decode($response->body(), true));
    }

    public function testShowAndIndexNeverExposePasswordHash(): void
    {
        $this->users->method('findById')->with(31)->willReturn(['id' => 31, 'password_hash' => 'secret']);
        $this->users->method('findAll')->willReturn([['id' => 31, 'password_hash' => 'secret']]);
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => self::STORE_A]]);

        $show = $this->userController()->show($this->request(self::OWNER, ['id' => '31']));
        $this->assertArrayNotHasKey('password_hash', json_decode($show->body(), true));

        $index = $this->userController()->index($this->request(self::OWNER));
        $this->assertArrayNotHasKey('password_hash', json_decode($index->body(), true)['data'][0]);
    }

    public function testUserIndexIsRestrictedToManagedStoreMembers(): void
    {
        $this->users->method('findAll')->willReturn([['id' => 31], ['id' => 32], ['id' => self::MANAGER]]);
        $this->storeUsers->method('findByStore')->with(self::STORE_A)->willReturn([['user_id' => 31]]);

        $response = $this->userController()->index($this->request(self::MANAGER));

        $ids = array_column(json_decode($response->body(), true)['data'], 'id');
        $this->assertEqualsCanonicalizing([31, self::MANAGER], $ids);
    }

    // ------------------------------------------------------------ ical tokens

    public function testManagerCannotReadIcalTokensOfAnotherStoreEmployee(): void
    {
        $this->users->method('findById')->with(30)->willReturn(['id' => 30]);
        $this->storeUsers->method('findByUser')->with(30)->willReturn([['store_id' => self::STORE_B]]);
        $icalTokens = $this->createMock(IcalTokenRepositoryInterface::class);
        $icalTokens->expects($this->never())->method('findByUser');
        $controller = new IcalTokenController(
            $icalTokens,
            new UserTargetGuard($this->users, $this->storeUsers, $this->permissions),
            $this->storeUsers,
        );

        $this->expectException(ForbiddenException::class);
        $controller->index($this->request(self::MANAGER, ['user_id' => '30']));
    }
}
