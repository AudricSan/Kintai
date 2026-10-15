<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Request;
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
        $this->controller = new DashboardLayoutController($this->prefs);
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
            'order'  => ['shifts_today', 'quick_nav', 'dashboard_alerts', 'store_stats_summary', 'financial_overview', 'hr_absenteeism', 'kpi_counters'],
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
}
