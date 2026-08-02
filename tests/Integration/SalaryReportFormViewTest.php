<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class SalaryReportFormViewTest extends TestCase
{
    private function renderForm(array $store, array $report = []): string
    {
        $viewsDir = dirname(__DIR__, 2) . '/src/Bundles/SalaryReport/Views';
        $view = new ViewRenderer(dirname($viewsDir));
        $view->addNamespace('salary-report', $viewsDir);

        return $view->renderPartial('salary-report::reports-salary-form', [
            'store'    => $store,
            'mode'     => 'edit',
            'report'   => $report,
            'BASE_URL' => '',
            'managers' => [],
        ]);
    }

    public function testMoneyFieldsShowThousandsSeparatedPreview(): void
    {
        // Régression : un <input type="number"> ne peut pas afficher de séparateur de
        // milliers, ce qui rendait les gros montants (ex. 158269.64) illisibles. Chaque
        // champ monétaire doit avoir un aperçu formaté à côté.
        $html = $this->renderForm(
            ['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
            ['id' => 30, 'store_id' => 1, 'net_payment' => 158269.64, 'other_deductions' => 27057.8]
        );

        $this->assertStringContainsString('data-money-preview="net_payment"', $html);
        $this->assertStringContainsString('158,270円', $html);
        $this->assertStringContainsString('27,058円', $html);
    }

    public function testHourlyWagePreviewHasHourSuffix(): void
    {
        $html = $this->renderForm(
            ['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
            ['id' => 30, 'store_id' => 1, 'staff_avg_hourly_wage' => 1234.0]
        );

        $this->assertStringContainsString('data-money-preview="staff_avg_hourly_wage"', $html);
        $this->assertStringContainsString('1,234円/h', $html);
    }
}
