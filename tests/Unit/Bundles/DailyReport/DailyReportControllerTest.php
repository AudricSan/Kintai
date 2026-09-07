<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\DailyReport;

use kintai\Bundles\DailyReport\Controllers\Web\DailyReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Mail\MailerService;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportMailService;
use kintai\Core\Services\DailyReportPdfService;
use kintai\Core\Services\DailyReportPermissionService;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\TranslationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Deux employés de magasins différents peuvent partager le même nom de famille.
 * Un shift mal assigné (import Excel, correction manuelle...) au mauvais user_id
 * ne doit jamais faire apparaître un staff d'un autre magasin dans la section
 * "shifts à venir / du lendemain" du formulaire de rapport journalier.
 */
final class DailyReportControllerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private DailyReportRepositoryInterface&MockObject $reports;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private NotificationService&MockObject $notifs;
    private DailyReportController $controller;

    protected function setUp(): void
    {
        $this->shifts     = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->users      = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs     = $this->createMock(NotificationService::class);

        $translations = new TranslationService(
            $this->createStub(TranslationRepositoryInterface::class),
            $this->createStub(LanguageRepositoryInterface::class),
        );
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $permissionService = new PermissionService($this->assignments, $this->roles);
        $permissions = new DailyReportPermissionService($permissionService);
        $pdfService = new DailyReportPdfService(
            new ViewRenderer(sys_get_temp_dir()),
            $translations,
            $this->shifts,
            $this->shiftTypes,
            $this->users,
        );
        $mailService = new DailyReportMailService(
            $permissions,
            new MailerService(['driver' => 'native', 'from' => ['address' => 'test@kintai.test', 'name' => 'Kintai']]),
            $translations,
        );

        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);

        $this->controller = new DailyReportController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->reports,
            $this->stores,
            $this->storeUsers,
            $this->users,
            $permissions,
            $pdfService,
            $mailService,
            new AuditLogger(),
            $translations,
            $this->shifts,
            $this->shiftTypes,
            $this->notifs,
            $permissionService,
        );
    }

    public function testGetFormShiftRowsExcludesShiftsFromUsersNotMemberOfStore(): void
    {
        $storeId = 1;
        $date    = '2026-08-01';

        // Deux "Dupont" homonymes : #10 appartient au magasin 1, #20 appartient à un
        // autre magasin. Un import erroné a néanmoins créé un shift store_id=1 pour #20.
        $this->shifts->method('findByDate')->with($storeId, $date)->willReturn([
            ['user_id' => 10, 'shift_type_id' => 0, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60, 'deleted_at' => null],
            ['user_id' => 20, 'shift_type_id' => 0, 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60, 'deleted_at' => null],
        ]);
        $this->shiftTypes->method('findByStore')->with($storeId)->willReturn([]);

        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $sid, int $uid) => ($sid === $storeId && $uid === 10) ? ['id' => 1, 'store_id' => $sid, 'user_id' => $uid] : null
        );

        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'last_name' => 'Dupont', 'first_name' => 'Jean']],
            [20, ['id' => 20, 'last_name' => 'Dupont', 'first_name' => 'Marie']],
        ]);

        $method = new \ReflectionMethod($this->controller, 'getFormShiftRows');
        $method->setAccessible(true);
        $rows = $method->invoke($this->controller, $storeId, $date);

        $this->assertCount(1, $rows, 'le shift du user #20 (non membre du magasin) doit être exclu');
        $this->assertSame('Dupont Jean', $rows[0]['employee']);
    }

    public function testSubmitNotifiesManagersWithApprovePermission(): void
    {
        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'draft', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->method('findMembership')->with(1, 9)->willReturn(['store_id' => 1, 'user_id' => 9]);
        $this->storeUsers->method('findByStore')->with(1)->willReturn([
            ['user_id' => 9],  // l'auteur, avec seulement le droit de soumettre (pas de valider)
            ['user_id' => 20], // manager avec daily_reports.approve
        ]);
        $this->users->method('findById')->willReturnMap([
            [9, ['id' => 9]],
            [20, ['id' => 20]],
        ]);
        // Régression : soumettre son propre rapport n'est plus du libre-service, l'auteur a
        // donc ici besoin de daily_reports.submit (accordée seule, sans .approve) — voir
        // CHANGELOG.
        $this->assignments->method('findByUser')->willReturnMap([
            [9, [['id' => 2, 'user_id' => 9, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 1]]],
            [20, [['id' => 1, 'user_id' => 20, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1]]],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [2, ['id' => 2, 'is_system' => 0]],
            [3, ['id' => 3, 'is_system' => 0]],
        ]);
        $this->roles->method('getPermissions')->willReturnMap([
            [2, ['daily_reports.submit', 'daily_reports.approve']],
            [3, ['daily_reports.submit']],
        ]);

        $this->notifs->expects($this->once())->method('notifyMany')
            ->with([20], 'daily_report_submitted', $this->anything(), 10);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller->submit($req);
    }

    public function testValidateNotifiesAuthorButNotSelf(): void
    {
        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->assignments->method('findByUser')->with(20)->willReturn([
            ['id' => 1, 'user_id' => 20, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['daily_reports.approve']);

        $this->notifs->expects($this->once())->method('notify')
            ->with(9, 'daily_report_validated', $this->anything(), 10);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 20]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller->validate($req);
    }

    public function testValidateBySelfDoesNotSelfNotify(): void
    {
        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->method('findMembership')->with(1, 9)->willReturn(['store_id' => 1, 'user_id' => 9]);
        $this->assignments->method('findByUser')->with(9)->willReturn([
            ['id' => 1, 'user_id' => 9, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['daily_reports.approve']);

        $this->notifs->expects($this->never())->method('notify');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller->validate($req);
    }
}
