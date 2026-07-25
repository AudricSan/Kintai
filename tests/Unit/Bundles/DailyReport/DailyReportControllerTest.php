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
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportMailService;
use kintai\Core\Services\DailyReportPdfService;
use kintai\Core\Services\DailyReportPermissionService;
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
    private DailyReportController $controller;

    protected function setUp(): void
    {
        $this->shifts     = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->users      = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);

        $translations = new TranslationService(
            $this->createStub(TranslationRepositoryInterface::class),
            $this->createStub(LanguageRepositoryInterface::class),
        );
        $permissions = new DailyReportPermissionService(new PermissionService(
            $this->createStub(RoleAssignmentRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
        ));
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

        $this->controller = new DailyReportController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->createMock(DailyReportRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->storeUsers,
            $this->users,
            $permissions,
            $pdfService,
            $mailService,
            new AuditLogger(),
            $translations,
            $this->shifts,
            $this->shiftTypes,
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
}
