<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use kintai\Core\Container;
use kintai\Core\Router;
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/** Tableaux de bord : widgets rendus dans l'ordre et la largeur de la disposition de l'utilisateur. */
final class DashboardGridRenderTest extends TestCase
{
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->view = new ViewRenderer(dirname(__DIR__, 2) . '/src/UI/View');

        $router = new Router();
        foreach (['admin.dashboard.widgets', 'admin.dashboard.layout', 'admin.requests', 'admin.users', 'admin.stores', 'admin.shifts', 'admin.shift_types',
                  'employee.dashboard.widgets', 'employee.dashboard.layout', 'employee.shifts.day'] as $i => $name) {
            $router->post('/r' . $i, [\stdClass::class, 'x'], name: $name);
        }
        Container::getInstance()->instance(Router::class, $router);
    }

    /** @return list<array{string, string}> [clé, largeur] des widgets, dans l'ordre du document */
    private static function items(string $html): array
    {
        preg_match_all('/data-widget-key="([a-z_]+)" data-widget-width="(half|full)"/', $html, $m, PREG_SET_ORDER);
        return array_map(static fn(array $x): array => [$x[1], $x[2]], $m);
    }

    public function testManagerWidgetsFollowTheLayout(): void
    {
        $html = $this->view->renderPartial('dashboard.index', [
            'BASE_URL'        => '',
            'stats'           => ['users' => 1, 'stores' => 1, 'shifts_today' => 0],
            'shifts_today'    => [],
            'enabled_widgets' => array_flip(['kpi_counters', 'shifts_today']),
            'all_widgets'     => HomeController::ADMIN_WIDGETS,
            'bundle_widgets'  => [['key' => 'pending_timeoff', 'html' => '<div class="card">bundle</div>']],
            'widget_layout'   => [
                'order'  => ['pending_timeoff', 'shifts_today', 'kpi_counters'],
                'widths' => ['pending_timeoff' => 'half', 'shifts_today' => 'half', 'kpi_counters' => 'full'],
            ],
        ]);

        $this->assertSame([['pending_timeoff', 'half'], ['shifts_today', 'half'], ['kpi_counters', 'full']], self::items($html));
        $this->assertStringContainsString('data-dash-reset', $html);
    }

    public function testHiddenWidgetsAreOfferedInTheAddMenuAndShownOnesCanBeRemoved(): void
    {
        $html = $this->view->renderPartial('employee.dashboard', [
            'BASE_URL'            => '',
            'shifts_today'        => [],
            'upcoming'            => [],
            'enabled_widgets'     => array_flip(['shifts_today']),
            'all_widgets'         => ['shifts_today', 'upcoming', 'monthly_stats'],
            'widget_layout'       => ['order' => ['shifts_today', 'upcoming', 'monthly_stats'], 'widths' => []],
        ]);

        preg_match_all('/data-dash-add="([a-z_]+)"/', $html, $m);
        $this->assertSame(['upcoming', 'monthly_stats'], $m[1]);
        $this->assertSame(1, substr_count($html, 'data-dash-remove'));
        $this->assertStringContainsString('data-widgets-url=', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
    }

    public function testFinancialAndHrWidgetsAreIndependentHalfWidthWidgetsByDefault(): void
    {
        $html = $this->view->renderPartial('dashboard.index', [
            'BASE_URL'           => '',
            'stats'              => [],
            'enabled_widgets'    => array_flip(['financial_overview', 'hr_absenteeism']),
            'all_widgets'        => HomeController::ADMIN_WIDGETS,
            'financial_overview' => ['current_month' => 1.0, 'previous_month' => 1.0, 'delta_pct' => null, 'currency' => 'EUR', 'currency_style' => 'symbol', 'trend_by_month' => []],
            'hr_stats'           => ['abs_rate' => 0.0, 'timeoff_taken' => 0, 'late_count' => 0, 'overtime_weeks' => 0],
            'widget_layout'      => ['order' => HomeController::ADMIN_WIDGETS, 'widths' => HomeController::ADMIN_WIDGET_WIDTHS],
        ]);

        $this->assertSame([['financial_overview', 'half'], ['hr_absenteeism', 'half']], self::items($html));
        $this->assertStringNotContainsString('dash-two-col', $html);
    }

    public function testEmployeeWidgetsFollowTheLayout(): void
    {
        $html = $this->view->renderPartial('employee.dashboard', [
            'BASE_URL'        => '',
            'shifts_today'    => [],
            'upcoming'        => [],
            'enabled_widgets' => array_flip(['shifts_today', 'upcoming']),
            'all_widgets'     => ['shifts_today', 'upcoming', 'monthly_stats', 'timeclock'],
            'bundle_widgets'  => [['key' => 'timeclock', 'html' => '<div class="card">pointage</div>']],
            'widget_layout'   => ['order' => ['timeclock', 'upcoming', 'shifts_today', 'monthly_stats'], 'widths' => ['upcoming' => 'half']],
        ]);

        $this->assertSame([['timeclock', 'full'], ['upcoming', 'half'], ['shifts_today', 'full']], self::items($html));
    }
}
