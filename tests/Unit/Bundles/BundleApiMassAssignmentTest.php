<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles;

$fixtures = dirname(__DIR__, 2) . '/Fixtures/bundles';
require_once $fixtures . '/timeoff-1.0.0/src/Controllers/Api/TimeoffRequestController.php';
require_once $fixtures . '/shift-swap-1.0.0/src/Controllers/Api/ShiftSwapRequestController.php';
require_once $fixtures . '/shift-claim-1.0.0/src/Controllers/Api/ShiftClaimController.php';
require_once $fixtures . '/feedback-1.0.0/src/Controllers/Api/FeedbackController.php';
require_once $fixtures . '/daily-report-1.0.0/src/Controllers/Api/DailyReportController.php';
require_once $fixtures . '/messaging-1.0.0/src/Controllers/Api/MessageController.php';
require_once $fixtures . '/timeclock-1.0.0/src/Controllers/Api/TimeclockController.php';

use kintai\Bundles\Installed\DailyReport\Controllers\Api\DailyReportController;
use kintai\Bundles\Installed\Feedback\Controllers\Api\FeedbackController;
use kintai\Bundles\Installed\Messaging\Controllers\Api\MessageController;
use kintai\Bundles\Installed\ShiftClaim\Controllers\Api\ShiftClaimController;
use kintai\Bundles\Installed\ShiftSwap\Controllers\Api\ShiftSwapRequestController;
use kintai\Bundles\Installed\TimeOff\Controllers\Api\TimeoffRequestController;
use kintai\Bundles\Installed\Timeclock\Controllers\Api\TimeclockController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportPermissionService;
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
            'timeoff.create', 'timeoff.update', 'swaps.create', 'swaps.update',
            'open_shifts.approve', 'feedbacks.update', 'daily_reports.create',
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

    // ------------------------------------------------------------- shift swap

    public function testSwapCreationIsForcedPendingAndIgnoresId(): void
    {
        $repo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['status'] === 'pending'
                && !array_key_exists('approved_by_id', $d)
                && $d['store_id'] === self::MY_STORE
                && $d['requester_id'] === self::ME
        ))->willReturn(['id' => 12]);
        $controller = new ShiftSwapRequestController($repo, new AuditLogger(), $this->permissions);

        $controller->store($this->request([
            'id' => 3, 'store_id' => self::MY_STORE, 'status' => 'approved', 'approved_by_id' => 99,
            'requester_shift_id' => 4, 'target_shift_id' => 5,
        ]));
    }

    public function testSwapCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new ShiftSwapRequestController($repo, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['store_id' => self::OTHER, 'requester_shift_id' => 4]));
    }

    // ------------------------------------------------------------ shift claim

    public function testClaimCreationIsForcedPendingAndIgnoresId(): void
    {
        $repo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['status'] === 'pending'
                && !array_key_exists('resolved_by', $d)
                && $d['user_id'] === self::ME
                && $d['shift_id'] === 8
        ))->willReturn(['id' => 2]);
        $controller = new ShiftClaimController($repo, $this->permissions, $this->storeUsers);

        $controller->store($this->request([
            'id' => 1, 'user_id' => self::ME, 'store_id' => self::MY_STORE, 'shift_id' => 8,
            'status' => 'approved', 'resolved_by' => 99,
        ]));
    }

    public function testClaimCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new ShiftClaimController($repo, $this->permissions, $this->storeUsers);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['user_id' => self::ME, 'store_id' => self::OTHER, 'shift_id' => 8]));
    }

    // --------------------------------------------------------------- feedback

    public function testFeedbackCreationForcesAuthorAndIgnoresId(): void
    {
        $repo = $this->createMock(FeedbackRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['user_id'] === self::ME
                && $d['store_id'] === self::MY_STORE
                && $d['message'] === 'bug'
        ))->willReturn(['id' => 3]);
        $controller = new FeedbackController($repo, $this->permissions, $this->storeUsers);

        $controller->store($this->request(['id' => 1, 'user_id' => 99, 'store_id' => self::MY_STORE, 'message' => 'bug', 'category' => 'bug']));
    }

    public function testFeedbackCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(FeedbackRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new FeedbackController($repo, $this->permissions, $this->storeUsers);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['store_id' => self::OTHER, 'message' => 'x']));
    }

    // ----------------------------------------------------------- daily report

    public function testDailyReportCreationForcesAuthorAndIgnoresId(): void
    {
        $repo   = $this->createMock(DailyReportRepositoryInterface::class);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturn(['id' => self::MY_STORE, 'name' => 'A']);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['author_id'] === self::ME
                && $d['status'] === 'draft'
                && $d['store_id'] === self::MY_STORE
                && !array_key_exists('validated_by', $d)
                && !array_key_exists('is_finalized', $d)
                && $d['notes'] === 'RAS'
        ))->willReturn(['id' => 6]);
        $controller = new DailyReportController($repo, $stores, $this->storeUsers, new DailyReportPermissionService($this->permissions));

        $controller->store($this->request([
            'id' => 1, 'author_id' => 99, 'store_id' => self::MY_STORE, 'notes' => 'RAS',
            'status' => 'validated', 'validated_by' => 99, 'is_finalized' => 1,
        ]));
    }

    // -------------------------------------------------------------- messaging

    public function testMessageCreationIgnoresIdSoItCannotHijackAnotherThreadsMessage(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 10]);
        $repo->method('findParticipant')->willReturn(['thread_id' => 10, 'user_id' => self::ME]);
        $repo->expects($this->once())->method('saveMessage')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['thread_id'] === 10
                && $d['sender_id'] === self::ME
                && $d['body'] === 'bonjour'
        ))->willReturn(['id' => 77]);
        $controller = new MessageController($repo, $this->storeUsers);

        $controller->addMessage($this->request(['id' => 55, 'thread_id' => 99, 'sender_id' => 1, 'body' => 'bonjour'], ['id' => '10']));
    }

    public function testParticipantCreationIgnoresIdAndOtherFields(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 10]);
        $repo->method('findParticipant')->willReturn(['thread_id' => 10, 'user_id' => self::ME]);
        $repo->expects($this->once())->method('saveParticipant')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['thread_id'] === 10
                && $d['user_id'] === self::ME
                && $d['is_read'] === 0
        ))->willReturn(['id' => 4]);
        $controller = new MessageController($repo, $this->storeUsers);

        $controller->addParticipant($this->request(['id' => 9, 'thread_id' => 99, 'user_id' => self::ME, 'is_read' => 1], ['id' => '10']));
    }

    public function testThreadCreationInAStoreTheCallerDoesNotBelongToIsForbidden(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->expects($this->never())->method('saveThread');
        $controller = new MessageController($repo, $this->storeUsers);

        $this->expectException(ForbiddenException::class);
        $controller->createThread($this->request(['store_id' => self::OTHER, 'subject' => 's']));
    }

    // --------------------------------------------------------------- timeclock

    public function testClockInInAStoreTheEmployeeDoesNotBelongToIsForbidden(): void
    {
        $repo = $this->createMock(TimeclockRepositoryInterface::class);
        $repo->method('findActiveByUser')->willReturn(null);
        $repo->expects($this->never())->method('save');
        $controller = new TimeclockController($repo, $this->storeUsers, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->clockIn($this->request(['user_id' => self::ME, 'store_id' => self::OTHER]));
    }

    public function testTimeclockUpdateCannotMoveTheEntryToAnotherStoreOrUser(): void
    {
        $repo = $this->createMock(TimeclockRepositoryInterface::class);
        $repo->method('findById')->with(3)->willReturn(['id' => 3, 'user_id' => 9, 'store_id' => self::MY_STORE]);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => $d['id'] === 3
                && !array_key_exists('store_id', $d)
                && !array_key_exists('user_id', $d)
        ))->willReturn(['id' => 3, 'user_id' => 9, 'store_id' => self::MY_STORE]);

        // Gestionnaire du magasin 5 : timeclock.update y est accordé pour ce test.
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => self::ME, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::MY_STORE],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 20, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['timeclock.update']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $controller = new TimeclockController($repo, $this->storeUsers, new AuditLogger(), new PermissionService($assignments, $roles));

        $controller->update($this->request(['note' => 'x', 'store_id' => self::OTHER, 'user_id' => 1], ['id' => '3']));
    }
}
