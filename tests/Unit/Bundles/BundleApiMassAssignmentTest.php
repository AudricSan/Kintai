<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles;

$fixtures = dirname(__DIR__, 2) . '/Fixtures/bundles';
require_once $fixtures . '/timeoff-1.0.0/src/Controllers/Api/TimeoffRequestController.php';

use kintai\Bundles\Installed\TimeOff\Controllers\Api\TimeoffRequestController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026) : les `store()`/`update()` des API de bundles fusionnaient le
 * JSON brut du client dans `save()`, qui fait un upsert dès qu'un `id` est présent. Un POST portant
 * un `id` écrasait donc la ligne de quelqu'un d'autre (contournant les contrôles d'objet de
 * `PUT /{id}`), et les routes de création en libre-service acceptaient `status`, `processed_by`,
 * `author_id`… tels quels (auto-approbation d'une demande de congé, par exemple).
 *
 * L'appelant (id 2) est un employé du magasin 5 ; le magasin 6 ne le concerne pas.
 */
final class BundleApiMassAssignmentTest extends TestCase
{
    private const ME        = 2;
    private const MY_STORE  = 5;
    private const OTHER     = 6;

    private PermissionService $permissions;
    private StoreUserRepositoryInterface $storeUsers;

    protected function setUp(): void
    {
        // Rôle de magasin accordant les clés de création/traitement sur le magasin 5 uniquement.
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => self::ME, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::MY_STORE],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 20, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn([
            'timeoff.create', 'timeoff.update',
        ]);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $this->permissions = new PermissionService($assignments, $roles);

        // Membre du magasin 5 seulement.
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $storeId, int $userId): ?array => $storeId === self::MY_STORE && $userId === self::ME
                ? ['store_id' => $storeId, 'user_id' => $userId]
                : null
        );
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => self::MY_STORE]]);
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    private function request(array $json, array $params = []): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => self::ME]);
        $req->setRouteParams($params);
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setValue($req, $json);
        return $req;
    }

    // ---------------------------------------------------------------- timeoff

    public function testTimeoffCreationCannotSelfApproveNorOverwriteAnotherRequest(): void
    {
        $repo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['status'] === 'pending'
                && !array_key_exists('processed_by', $d)
                && !array_key_exists('admin_note', $d)
                && $d['user_id'] === self::ME
                && $d['store_id'] === self::MY_STORE
                && $d['reason'] === 'vacances'
        ))->willReturn(['id' => 40]);
        $controller = new TimeoffRequestController($repo, new AuditLogger(), $this->permissions, $this->storeUsers);

        $response = $controller->store($this->request([
            'id' => 7, 'user_id' => self::ME, 'store_id' => self::MY_STORE, 'reason' => 'vacances',
            'status' => 'approved', 'processed_by' => 99, 'admin_note' => 'ok',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
        ]));

        $this->assertSame(201, $response->status());
    }

    public function testTimeoffCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new TimeoffRequestController($repo, new AuditLogger(), $this->permissions, $this->storeUsers);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['user_id' => self::ME, 'store_id' => self::OTHER]));
    }

    public function testTimeoffUpdateKeepsOwnerAndStoreAndRecordsWhoProcessedIt(): void
    {
        $repo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $repo->method('findById')->with(7)->willReturn(['id' => 7, 'user_id' => 9, 'store_id' => self::MY_STORE]);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => $d['id'] === 7
                && $d['status'] === 'approved'
                && $d['processed_by'] === self::ME
                && !array_key_exists('store_id', $d)
                && !array_key_exists('user_id', $d)
        ))->willReturn(['id' => 7]);
        $controller = new TimeoffRequestController($repo, new AuditLogger(), $this->permissions, $this->storeUsers);

        $controller->update($this->request(['status' => 'approved', 'store_id' => self::OTHER, 'user_id' => 1], ['id' => '7']));
    }
}
