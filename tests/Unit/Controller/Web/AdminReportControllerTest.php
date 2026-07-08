<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Container;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\Controller\Web\Staff\AdminReportController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminReportControllerTest extends TestCase
{
    private HiringReportRepositoryInterface&MockObject $hiringReports;
    private ResignationReportRepositoryInterface&MockObject $resignationReports;
    private SalaryReportRepositoryInterface&MockObject $salaryReports;
    private StoreRepositoryInterface&MockObject $stores;
    private LogRepositoryInterface&MockObject $logRepo;
    private AdminReportController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('staff.reports-hiring-show');
        $this->ensureViewFile('staff.reports-resignation-show');
        $this->ensureViewFile('staff.reports-salary-show');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());

        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);

        // AuditLogger::log() délègue à Log::record() -> LogRepositoryInterface::record() ;
        // on fournit un container pour pouvoir vérifier l'écriture de l'audit (cf. AdminControllerTest).
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new AdminReportController(
            $view,
            $this->stores,
            $this->createMock(UserRepositoryInterface::class),
            $this->hiringReports,
            $this->resignationReports,
            $this->salaryReports,
            $this->createMock(StoreUserRepositoryInterface::class),
            $this->createMock(DailyReportRepositoryInterface::class),
            $this->createMock(ShiftRepositoryInterface::class),
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

    public function testShowHiringReportLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->hiringReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Alice']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'hiring_report.viewed', 'hiring_report', 10,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->showHiringReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowResignationReportLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '20']);

        $this->resignationReports->method('findById')->with(20)->willReturn(['id' => 20, 'store_id' => 1, 'employee_name' => 'Bob']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'resignation_report.viewed', 'resignation_report', 20,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->showResignationReport($req);

        $this->assertSame(200, $response->status());
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

    public function testUpdateHiringReportMergesPostFieldsAndRedirects(): void
    {
        $_POST = [
            'user_id'         => '5',
            'employee_number' => 'E-42',
            'employee_name'   => 'Alice Martin',
            'notes'           => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->hiringReports->method('findById')->with(10)
            ->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Ancien nom']);

        $this->hiringReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 10
                && $data['user_id'] === 5
                && $data['employee_number'] === 'E-42'
                && $data['employee_name'] === 'Alice Martin'
                && $data['notes'] === null // champ vide → null
        ));

        $response = $this->controller->updateHiringReport($req);

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

    public function testShowReportOfAnotherStoreThrowsNotFound(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->hiringReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 2]);

        $this->expectException(\kintai\Core\Exceptions\NotFoundException::class);
        $this->controller->showHiringReport($req);
    }

    private function ensureViewFile(string $view): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($file);
    }
}
