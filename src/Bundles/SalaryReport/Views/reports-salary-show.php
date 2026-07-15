<?php

declare(strict_types=1);

use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * @var array  $store
 * @var array  $report
 * @var string $BASE_URL
 */

$storeId = (int) $store['id'];
$reportId = (int) $report['id'];
$currency = $store['currency'] ?? 'JPY';
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/' . $reportId;

$fmt = fn(?string $v, string $def = '—') => $v !== null && $v !== '' ? htmlspecialchars($v) : $def;
$cur = fn(?string $v) => $v !== null && $v !== '' ? format_currency((float) $v, $currency) : '—';
$num = fn(?string $v) => $v !== null && $v !== '' ? htmlspecialchars($v) : '—';
?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= __('sr_title') ?> — <?= htmlspecialchars($report['target_month'] ?? '') ?>
        <span class="page-count"><?= htmlspecialchars($store['name'] ?? '') ?></span>
    </h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary')->render() ?>
        <?= Button::make(__('edit'))->primary()->sm()->link($base . '/edit')->render() ?>
        <?= Button::make(__('sr_pdf'))->ghost()->sm()->link($base . '/pdf')->render() ?>
        <form method="POST" action="<?= htmlspecialchars($base . '/delete') ?>" class="form-inline" onsubmit="return confirm('<?= __('sr_confirm_delete') ?>')">
            <?= csrf_field() ?>
            <?= Button::make(__('delete'))->danger()->sm()->submit()->render() ?>
        </form>
    </div>
</div>

<?php
$sections = [];

// Basic Info
ob_start();
?>
<table class="detail-table">
    <tr><th><?= __('sr_target_month') ?></th><td><?= $fmt($report['target_month'] ?? '') ?></td></tr>
    <tr><th><?= __('store') ?></th><td><?= $fmt($report['store_name'] ?? ($store['name'] ?? '')) ?></td></tr>
    <?php if (!empty($report['user_id'])): ?>
    <tr><th><?= __('employee_name') ?></th><td><?= $fmt($report['employee_name'] ?? '') ?></td></tr>
    <?php endif; ?>
    <tr><th><?= __('sr_person_in_charge') ?></th><td><?= $fmt($report['person_in_charge'] ?? '') ?></td></tr>
</table>
<?php $sections[__('sr_section_basic')] = ob_get_clean();

// Financial Summary
ob_start();
?>
<table class="detail-table">
    <tr><th><?= __('sr_total_payment') ?></th><td class="td-mono"><?= $cur($report['total_payment'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_total_deductions') ?></th><td class="td-mono"><?= $cur($report['total_deductions'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_income_tax_base') ?></th><td class="td-mono"><?= $cur($report['income_tax_base'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_withholding_tax') ?></th><td class="td-mono"><?= $cur($report['withholding_tax'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_residence_tax') ?></th><td class="td-mono"><?= $cur($report['residence_tax'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_other_deductions') ?></th><td class="td-mono"><?= $cur($report['other_deductions'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_net_payment') ?></th><td class="td-mono td-highlight"><?= $cur($report['net_payment'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_hand_delivered_salary') ?></th><td class="td-mono"><?= $cur($report['hand_delivered_salary'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_active_employees') ?></th><td class="td-mono"><?= $num($report['active_employees'] ?? null) ?></td></tr>
</table>
<?php $sections[__('sr_section_financial')] = ob_get_clean();

// Staff Summary
ob_start();
?>
<table class="detail-table">
    <tr><th><?= __('sr_staff_man_hours') ?></th><td class="td-mono"><?= $num($report['staff_man_hours'] ?? null) ?> h</td></tr>
    <tr><th><?= __('sr_staff_total_payment') ?></th><td class="td-mono"><?= $cur($report['staff_total_payment'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_staff_avg_hourly_wage') ?></th><td class="td-mono"><?= $cur($report['staff_avg_hourly_wage'] ?? null) ?><?= $report['staff_avg_hourly_wage'] !== null && $report['staff_avg_hourly_wage'] !== '' ? '/h' : '' ?></td></tr>
    <tr><th><?= __('sr_new_hires') ?></th><td class="td-mono"><?= $num($report['new_hires'] ?? null) ?></td></tr>
    <tr><th><?= __('sr_resigned_staff') ?></th><td class="td-mono"><?= $num($report['resigned_staff'] ?? null) ?></td></tr>
</table>

<?php if (!empty($report['employee_work_hours'])): ?>
<h4 class="detail-subtitle"><?= __('sr_employee_work_hours') ?></h4>
<div class="detail-text"><?= nl2br($fmt($report['employee_work_hours'], '')) ?></div>
<?php endif; ?>

<?php if (!empty($report['hire_registrations'])): ?>
<h4 class="detail-subtitle"><?= __('sr_hire_registrations') ?></h4>
<div class="detail-text"><?= nl2br($fmt($report['hire_registrations'], '')) ?></div>
<?php endif; ?>
<?php $sections[__('sr_section_staff')] = ob_get_clean();

// Notes
if (!empty($report['remarks'])):
ob_start();
?>
<div class="detail-text"><?= nl2br($fmt($report['remarks'], '')) ?></div>
<?php
$sections[__('sr_section_notes')] = ob_get_clean();
endif;

// Render cards
foreach ($sections as $title => $body):
    echo Card::make()->header('<span>' . htmlspecialchars($title) . '</span>')->body($body)->render();
endforeach;
?>
