<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\SalaryReport;

use kintai\Bundles\SalaryReport\Controllers\Web\AdminSalaryReportController;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminSalaryReportControllerTest extends TestCase
{
    private SalaryReportRepositoryInterface&MockObject $salaryReports;
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private DailyReportRepositoryInterface&MockObject $dailyReports;
    private ShiftRepositoryInterface&MockObject $shifts;
    private LogRepositoryInterface&MockObject $logRepo;
    private AdminSalaryReportController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-salary-report-views';
        $this->ensureViewFile($viewDir, 'reports-salary-show');
        $this->ensureViewFile($viewDir, 'reports-salary-form');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('salary-report', $viewDir);

        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->dailyReports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);

        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new AdminSalaryReportController(
            $view,
            $this->stores,
            $this->users,
            $this->salaryReports,
            $this->storeUsers,
            $this->dailyReports,
            $this->shifts,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    public function testShowSalaryReportLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 1, 'target_month' => '2026-08']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'salary_report.viewed', 'salary_report', 30,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->showSalaryReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowSalaryReportOfAnotherStoreThrowsNotFound(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->salaryReports->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 2]);

        $this->expectException(NotFoundException::class);
        $this->controller->showSalaryReport($req);
    }

    public function testStoreSalaryReportRejectsDuplicateMonth(): void
    {
        $_POST = ['target_month' => '2026-08'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->salaryReports->method('findByStoreAndMonth')->with(1, '2026-08')
            ->willReturn(['id' => 99, 'store_id' => 1, 'target_month' => '2026-08']);
        $this->salaryReports->expects($this->never())->method('save');

        $response = $this->controller->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreSalaryReportSavesAndRedirects(): void
    {
        $_POST = [
            'target_month'          => '2026-08',
            'store_name'            => 'Store A',
            'person_in_charge'      => 'Manager X',
            'total_payment'         => '100000',
            'total_deductions'      => '',
            'income_tax_base'       => '',
            'withholding_tax'       => '',
            'residence_tax'         => '',
            'other_deductions'      => '',
            'net_payment'           => '',
            'active_employees'      => '3',
            'hand_delivered_salary' => '',
            'staff_man_hours'       => '',
            'staff_total_payment'   => '',
            'staff_avg_hourly_wage' => '',
            'employee_work_hours'   => '',
            'new_hires'             => '0',
            'resigned_staff'        => '0',
            'hire_registrations'    => '',
            'remarks'               => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->salaryReports->method('findByStoreAndMonth')->with(1, '2026-08')->willReturn(null);
        $this->salaryReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['store_id'] === 1
                && $data['target_month'] === '2026-08'
                && $data['total_payment'] === 100000.0
                && $data['active_employees'] === 3
        ))->willReturn(['id' => 55]);

        $response = $this->controller->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateSalaryReportMergesPostFieldsAndRedirects(): void
    {
        $_POST = [
            'store_name'            => 'Store A',
            'person_in_charge'      => 'Manager Y',
            'total_payment'         => '200000',
            'total_deductions'      => '0',
            'income_tax_base'       => '0',
            'withholding_tax'       => '0',
            'residence_tax'         => '0',
            'other_deductions'      => '0',
            'net_payment'           => '0',
            'active_employees'      => '4',
            'hand_delivered_salary' => '0',
            'staff_man_hours'       => '0',
            'staff_total_payment'   => '0',
            'staff_avg_hourly_wage' => '0',
            'employee_work_hours'   => '',
            'new_hires'             => '0',
            'resigned_staff'        => '0',
            'hire_registrations'    => '',
            'remarks'               => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->method('findById')->with(30)
            ->willReturn(['id' => 30, 'store_id' => 1, 'target_month' => '2026-08']);

        $this->salaryReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 30
                && $data['person_in_charge'] === 'Manager Y'
                && $data['total_payment'] === 200000.0
        ));

        $response = $this->controller->updateSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteSalaryReportDeletesAndLogs(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 1]);
        $this->salaryReports->expects($this->once())->method('delete')->with(30);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'salary_report.deleted', 'salary_report', 30,
            $this->anything(), $this->anything(), $this->anything(),
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->deleteSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testCreateSalaryReportPresetsFromDailyReportsAndShifts(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->method('findByStore')->with(1)->willReturn([]);

        $this->dailyReports->method('findByStoreAndDateRange')->willReturn([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => '10000'],
            ['report_date' => date('Y-m') . '-02', 'status' => 'submitted', 'sales_total' => '5000'],
            ['report_date' => date('Y-m') . '-03', 'status' => 'draft', 'sales_total' => '9999'],
        ]);
        $this->shifts->method('findByStore')->with(1)->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'estimated_salary' => 6000, 'user_id' => 7],
        ]);
        $this->users->method('findById')->with(7)->willReturn(['id' => 7, 'display_name' => 'Employé']);

        $response = $this->controller->createSalaryReport($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // calculateSalaryPreset — mode de saisie cumulatif du rapport journalier (item 4)
    // -------------------------------------------------------------------------

    public function testCalculateSalaryPresetSumsRawValuesInPerDayMode(): void
    {
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports->method('findByStoreAndDateRange')->willReturn([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => date('Y-m') . '-02', 'status' => 'validated', 'sales_total' => 2000],
        ]);
        $this->shifts->method('findByStore')->willReturn([]);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser);

        $this->assertSame(3000.0, $preset['total_payment']);
    }

    public function testCalculateSalaryPresetUsesDailyDeltasInCumulativeInputMode(): void
    {
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => json_encode(['cumulative_mode' => 'cumulative_input'])];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        // Saisies cumulées depuis le début du mois : la dernière valeur (3300) EST le total du mois,
        // sommer les 3 lignes brutes donnerait 6400 (faux).
        $this->dailyReports->method('findByStoreAndDateRange')->willReturn([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => date('Y-m') . '-02', 'status' => 'validated', 'sales_total' => 2100],
            ['report_date' => date('Y-m') . '-03', 'status' => 'validated', 'sales_total' => 3300],
        ]);
        $this->shifts->method('findByStore')->willReturn([]);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser);

        $this->assertSame(3300.0, $preset['total_payment']);
    }

    private function invokeCalculateSalaryPreset(array $store, string $targetMonth, array $authUser): array
    {
        $method = new \ReflectionMethod($this->controller, 'calculateSalaryPreset');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $store, $targetMonth, $authUser);
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }
}
