<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Extension;

use kintai\Core\Extension\DashboardLayout;
use PHPUnit\Framework\TestCase;

final class DashboardLayoutTest extends TestCase
{
    private const KEYS = ['kpi_counters', 'dashboard_alerts', 'shifts_today', 'pending_timeoff'];

    public function testWithoutSavedLayoutCoreWidgetsComeFirstThenBundlesWithDefaultWidths(): void
    {
        $this->assertSame(
            ['order' => self::KEYS, 'widths' => ['kpi_counters' => 'full', 'dashboard_alerts' => 'half', 'shifts_today' => 'full', 'pending_timeoff' => 'full']],
            DashboardLayout::resolve(self::KEYS, null, ['dashboard_alerts' => 'half']),
        );
    }

    public function testSavedOrderComesFirstAndNewWidgetsAreAppended(): void
    {
        // « old_widget » n'est plus proposé (bundle désactivé) ; « pending_timeoff » est apparu depuis.
        $layout = ['order' => ['shifts_today', 'old_widget', 'kpi_counters', 'dashboard_alerts'], 'widths' => ['shifts_today' => 'half']];

        $this->assertSame(['shifts_today', 'kpi_counters', 'dashboard_alerts', 'pending_timeoff'], DashboardLayout::order(self::KEYS, $layout));
        $this->assertSame('half', DashboardLayout::widths(self::KEYS, $layout)['shifts_today']);
    }

    public function testNormalizeDropsMalformedKeysDuplicatesAndWidths(): void
    {
        $this->assertSame(
            ['order' => ['kpi_counters', 'shifts_today'], 'widths' => ['shifts_today' => 'half']],
            DashboardLayout::normalize([
                'order'  => ['kpi_counters', 'Bad Key', 'kpi_counters', 42, 'shifts_today'],
                'widths' => ['shifts_today' => 'half', 'kpi_counters' => 'huge', 'Bad Key' => 'full'],
            ]),
        );
        $this->assertSame(['order' => [], 'widths' => []], DashboardLayout::normalize('nope'));
    }

    public function testUpdateKeepsHiddenWidgetsInTheirSlots(): void
    {
        // Affichés : kpi_counters, shifts_today, pending_timeoff ; dashboard_alerts est masqué (désactivé).
        $updated = DashboardLayout::update(self::KEYS, null, ['pending_timeoff', 'kpi_counters', 'shifts_today'], []);

        $this->assertSame(['pending_timeoff', 'dashboard_alerts', 'kpi_counters', 'shifts_today'], $updated['order']);
    }

    public function testUpdateKeepsWidgetsOfADisabledBundleAndMergesWidths(): void
    {
        $previous = ['order' => ['old_widget', 'kpi_counters', 'dashboard_alerts', 'shifts_today', 'pending_timeoff'], 'widths' => ['old_widget' => 'half']];

        $updated = DashboardLayout::update(
            self::KEYS,
            $previous,
            ['shifts_today', 'kpi_counters', 'dashboard_alerts', 'pending_timeoff', 'unknown_widget'],
            ['shifts_today' => 'half', 'unknown_widget' => 'half', 'kpi_counters' => 'huge'],
        );

        $this->assertSame(['old_widget', 'shifts_today', 'kpi_counters', 'dashboard_alerts', 'pending_timeoff'], $updated['order']);
        $this->assertSame(['old_widget' => 'half', 'shifts_today' => 'half'], $updated['widths']);
    }
}
