<?php

declare(strict_types=1);

use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * @var array  $store
 * @var string $mode    'create' | 'edit'
 * @var array  $report
 * @var string $BASE_URL
 * @var array  $managers
 */

$report ??= [];
$storeId = (int) $store['id'];
$reportId = (int) ($report['id'] ?? 0);
$currency = $store['currency'] ?? 'JPY';

$action = $mode === 'edit'
    ? $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/' . $reportId . '/edit'
    : $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/create';

$val = fn(string $key, string $default = '') => htmlspecialchars((string) ($report[$key] ?? $default));
$employeeId = (int) ($report['user_id'] ?? 0);
$isEmployeeScoped = $employeeId > 0;

/** Libellé de champ suffixé par son unité (devise du store, ou heures) — les montants nus sans unité sont une des choses qui rendaient ce formulaire dense et peu lisible. */
$moneyLabel = fn(string $key) => __($key) . ' (' . $currency . ')';
$hoursLabel = fn(string $key) => __($key) . ' (h)';
?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= $mode === 'edit' ? __('sr_edit') : __('sr_new') ?> — <?= htmlspecialchars($store['name'] ?? '') ?>
        <?php if ($isEmployeeScoped): ?> — <?= $val('employee_name') ?><?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary')->render() ?>
    </div>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>" class="form-stack">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= $employeeId ?>">
    <input type="hidden" name="employee_name" value="<?= $val('employee_name') ?>">
    <input type="hidden" name="store_name" value="<?= htmlspecialchars($store['name'] ?? '') ?>">
    <?php if ($isEmployeeScoped): ?>
    <!-- Champs sans objet pour un rapport lié à un seul employé (voir sr_employee_scope_hint) : conservés tels quels, non affichés. -->
    <input type="hidden" name="active_employees" value="<?= $val('active_employees', '0') ?>">
    <input type="hidden" name="new_hires" value="<?= $val('new_hires', '0') ?>">
    <input type="hidden" name="resigned_staff" value="<?= $val('resigned_staff', '0') ?>">
    <?php endif; ?>

    <?php
    ob_start();
    if ($isEmployeeScoped): ?>
    <p class="form-hint"><?= __('sr_employee_scope_hint') ?></p>
    <?php endif; ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('sr_target_month') ?></label>
            <input type="month" name="target_month" class="form-control"
                   value="<?= $val('target_month') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('sr_person_in_charge') ?></label>
            <select name="person_in_charge" class="form-control" required>
                <option value="">— <?= __('select') ?> —</option>
                <?php $selectedPic = $report['person_in_charge'] ?? ''; ?>
                <?php foreach ($managers ?? [] as $m): ?>
                    <?php $mName = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? '')) ?: ($m['display_name'] ?? $m['email'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($mName) ?>" <?= $mName === $selectedPic ? 'selected' : '' ?>><?= htmlspecialchars($mName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
    $basicBody = ob_get_clean();
    echo Card::make()->header(__('sr_section_basic'))->body($basicBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_total_payment') ?></label>
            <input type="number" name="total_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('total_payment', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_net_payment') ?></label>
            <input type="number" name="net_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('net_payment', '0') ?>">
        </div>
    </div>

    <h4 class="section-subtitle"><?= __('sr_section_deductions') ?></h4>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_income_tax_base') ?></label>
            <input type="number" name="income_tax_base" class="form-control"
                   step="0.01" min="0" value="<?= $val('income_tax_base', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_total_deductions') ?></label>
            <input type="number" name="total_deductions" class="form-control"
                   step="0.01" min="0" value="<?= $val('total_deductions', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_withholding_tax') ?></label>
            <input type="number" name="withholding_tax" class="form-control"
                   step="0.01" min="0" value="<?= $val('withholding_tax', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_residence_tax') ?></label>
            <input type="number" name="residence_tax" class="form-control"
                   step="0.01" min="0" value="<?= $val('residence_tax', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_other_deductions') ?></label>
            <input type="number" name="other_deductions" class="form-control"
                   step="0.01" min="0" value="<?= $val('other_deductions', '0') ?>">
        </div>
    </div>

    <h4 class="section-subtitle"><?= __('sr_section_payout') ?></h4>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_bank_transfer_salary') ?></label>
            <input type="number" name="bank_transfer_salary" class="form-control"
                   step="0.01" min="0" value="<?= $val('bank_transfer_salary', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_hand_delivered_salary') ?></label>
            <input type="number" name="hand_delivered_salary" class="form-control"
                   step="0.01" min="0" value="<?= $val('hand_delivered_salary', '0') ?>">
        </div>
    </div>
    <?php
    $finBody = ob_get_clean();
    echo Card::make()->header(__('sr_section_financial'))->body($finBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $hoursLabel('sr_staff_man_hours') ?></label>
            <input type="number" name="staff_man_hours" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_man_hours', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('sr_staff_total_payment') ?></label>
            <input type="number" name="staff_total_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_total_payment', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('sr_staff_avg_hourly_wage') ?> (<?= $currency ?>/h)</label>
            <input type="number" name="staff_avg_hourly_wage" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_avg_hourly_wage', '0') ?>">
        </div>
        <?php if (!$isEmployeeScoped): ?>
        <div class="form-group">
            <label class="form-label"><?= __('sr_active_employees') ?></label>
            <input type="number" name="active_employees" class="form-control"
                   step="1" min="0" value="<?= $val('active_employees', '0') ?>">
        </div>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('sr_employee_work_hours') ?></label>
        <textarea name="employee_work_hours" class="form-control" rows="3"><?= $val('employee_work_hours') ?></textarea>
    </div>

    <h4 class="section-subtitle"><?= __('sr_section_hr_movements') ?></h4>
    <?php if (!$isEmployeeScoped): ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('sr_new_hires') ?></label>
            <input type="number" name="new_hires" class="form-control"
                   step="1" min="0" value="<?= $val('new_hires', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('sr_resigned_staff') ?></label>
            <input type="number" name="resigned_staff" class="form-control"
                   step="1" min="0" value="<?= $val('resigned_staff', '0') ?>">
        </div>
    </div>
    <?php endif; ?>
    <div class="form-group">
        <label class="form-label"><?= __('sr_hire_registrations') ?></label>
        <textarea name="hire_registrations" class="form-control" rows="3"><?= $val('hire_registrations') ?></textarea>
    </div>
    <?php
    $staffBody = ob_get_clean();
    echo Card::make()->header(__('sr_section_staff'))->body($staffBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-group">
        <label class="form-label"><?= __('sr_remarks') ?></label>
        <textarea name="remarks" class="form-control" rows="4"><?= $val('remarks') ?></textarea>
    </div>
    <?php
    $notesBody = ob_get_clean();
    echo Card::make()->header(__('sr_section_notes'))->body($notesBody)->render();
    ?>

    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('sr_create'))->primary()->submit()->render() ?>
        <a href="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
