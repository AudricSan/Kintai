<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\DashboardLayoutController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DashboardLayoutControllerTest extends TestCase
{
    private UserDashboardPrefsRepositoryInterface&MockObject $prefs;
    private DashboardLayoutController $controller;

    protected function setUp(): void
    {
        $this->prefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $this->controller = new DashboardLayoutController($this->prefs, new AuditLogger());
    }

    private function request(array $body): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 7]);
        (new \ReflectionProperty(Request::class, 'jsonBody'))->setValue($req, $body);
        return $req;
    }

    public function testManagerLayoutIsMergedIntoTheSavedOne(): void
    {
        // dashboard_alerts, store_stats_summary… ne sont pas affichés : ils gardent leur place.
        $this->prefs->method('getLayout')->with(7, 'admin')->willReturn(null);
        $this->prefs->expects($this->once())->method('saveLayout')->with(7, 'admin', [
            'order'  => ['shifts_today', 'dashboard_alerts', 'store_stats_summary', 'financial_overview', 'hr_absenteeism', 'kpi_counters'],
            'widths' => ['shifts_today' => 'half', 'kpi_counters' => 'full'],
        ]);

        $response = $this->controller->saveManager($this->request([
            'order'  => ['shifts_today', 'kpi_counters'],
            'widths' => ['shifts_today' => 'half', 'kpi_counters' => 'full'],
        ]));

        $this->assertSame(200, $response->status());
    }

    public function testEmployeeLayoutGoesToTheEmployeeDashboard(): void
    {
        $this->prefs->method('getLayout')->willReturn(null);
        $this->prefs->expects($this->once())->method('saveLayout')
            ->with(7, 'employee', ['order' => ['upcoming', 'shifts_today', 'monthly_stats'], 'widths' => []]);

        $this->controller->saveEmployee($this->request(['order' => ['upcoming', 'shifts_today', 'monthly_stats']]));
    }

    public function testResetForgetsTheLayout(): void
    {
        $this->prefs->expects($this->never())->method('getLayout');
        $this->prefs->expects($this->once())->method('saveLayout')->with(7, 'admin', null);

        $response = $this->controller->saveManager($this->request(['reset' => true]));

        $this->assertSame(['layout' => null], json_decode($response->body(), true));
    }

    public function testAddedWidgetGoesToTheEndWithoutResettingTheOthers(): void
    {
        // shifts_today était masqué ; les autres widgets gardent leur ordre et leur largeur.
        $saved = ['order' => ['hr_absenteeism', 'kpi_counters'], 'widths' => ['hr_absenteeism' => 'full', 'kpi_counters' => 'half']];
        $this->prefs->method('getEnabledWidgets')->with(7, 'admin')->willReturn(['kpi_counters', 'hr_absenteeism']);
        $this->prefs->method('getLayout')->with(7, 'admin')->willReturn($saved);
        $this->prefs->expects($this->once())->method('saveWidgets')->with(7, ['kpi_counters', 'hr_absenteeism', 'shifts_today'], 'admin');
        $this->prefs->expects($this->once())->method('saveLayout')->with(7, 'admin', [
            'order'  => ['hr_absenteeism', 'kpi_counters', 'dashboard_alerts', 'store_stats_summary', 'financial_overview', 'shifts_today'],
            'widths' => $saved['widths'],
        ]);

        $response = $this->controller->widgetsManager($this->request(['add' => 'shifts_today']));

        $this->assertSame(200, $response->status());
    }

    public function testRemovedWidgetLeavesTheLayoutUntouched(): void
    {
        // Sans préférences : tous les widgets du gérant sont affichés, on en retire un.
        $this->prefs->method('getEnabledWidgets')->willReturn(null);
        $this->prefs->expects($this->never())->method('saveLayout');
        $this->prefs->expects($this->once())->method('saveWidgets')
            ->with(7, ['kpi_counters', 'dashboard_alerts', 'store_stats_summary', 'financial_overview', 'hr_absenteeism'], 'admin');

        $this->controller->widgetsManager($this->request(['remove' => 'shifts_today']));
    }

    public function testEmployeeWidgetsGoToTheEmployeeDashboard(): void
    {
        $this->prefs->method('getEnabledWidgets')->with(7, 'employee')->willReturn(['upcoming']);
        $this->prefs->expects($this->once())->method('saveWidgets')->with(7, [], 'employee');

        $this->controller->widgetsEmployee($this->request(['remove' => 'upcoming']));
    }

    public function testUnknownWidgetIsRejected(): void
    {
        $this->prefs->expects($this->never())->method('saveWidgets');
        $this->expectException(ValidationException::class);

        $this->controller->widgetsManager($this->request(['add' => 'not_a_widget']));
    }
}
