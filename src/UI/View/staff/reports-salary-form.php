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

$action = $mode === 'edit'
    ? $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/' . $reportId . '/edit'
    : $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/create';

$val = fn(string $key, string $default = '') => htmlspecialchars((string) ($report[$key] ?? $default));
?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= $mode === 'edit' ? __('sr_edit') : __('sr_new') ?> — <?= htmlspecialchars($store['name'] ?? '') ?>
    </h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary')->render() ?>
    </div>
</div>

<?php
ob_start();
?>
<form method="POST" action="<?= htmlspecialchars($action) ?>">
    <?= csrf_field() ?>

    <h3 class="form-section-title"><?= __('sr_section_basic') ?></h3>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_target_month') ?></label>
            <input type="month" name="target_month" class="form-control"
                   value="<?= $val('target_month') ?>" required>
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('store') ?></label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($store['name'] ?? '') ?>" readonly disabled>
            <input type="hidden" name="store_name" value="<?= htmlspecialchars($store['name'] ?? '') ?>">
        </div>
        <div class="form-group form-group--flex1">
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

    <h3 class="form-section-title"><?= __('sr_section_financial') ?></h3>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_total_payment') ?></label>
            <input type="number" name="total_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('total_payment', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_total_deductions') ?></label>
            <input type="number" name="total_deductions" class="form-control"
                   step="0.01" min="0" value="<?= $val('total_deductions', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_net_payment') ?></label>
            <input type="number" name="net_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('net_payment', '0') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_income_tax_base') ?></label>
            <input type="number" name="income_tax_base" class="form-control"
                   step="0.01" min="0" value="<?= $val('income_tax_base', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_withholding_tax') ?></label>
            <input type="number" name="withholding_tax" class="form-control"
                   step="0.01" min="0" value="<?= $val('withholding_tax', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_residence_tax') ?></label>
            <input type="number" name="residence_tax" class="form-control"
                   step="0.01" min="0" value="<?= $val('residence_tax', '0') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_other_deductions') ?></label>
            <input type="number" name="other_deductions" class="form-control"
                   step="0.01" min="0" value="<?= $val('other_deductions', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_hand_delivered_salary') ?></label>
            <input type="number" name="hand_delivered_salary" class="form-control"
                   step="0.01" min="0" value="<?= $val('hand_delivered_salary', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_active_employees') ?></label>
            <input type="number" name="active_employees" class="form-control"
                   step="1" min="0" value="<?= $val('active_employees', '0') ?>">
        </div>
    </div>

    <h3 class="form-section-title"><?= __('sr_section_staff') ?></h3>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_staff_man_hours') ?></label>
            <input type="number" name="staff_man_hours" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_man_hours', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_staff_total_payment') ?></label>
            <input type="number" name="staff_total_payment" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_total_payment', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_staff_avg_hourly_wage') ?></label>
            <input type="number" name="staff_avg_hourly_wage" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_avg_hourly_wage', '0') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= __('sr_employee_work_hours') ?></label>
        <textarea name="employee_work_hours" class="form-control" rows="3"><?= $val('employee_work_hours') ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_new_hires') ?></label>
            <input type="number" name="new_hires" class="form-control"
                   step="1" min="0" value="<?= $val('new_hires', '0') ?>">
        </div>
        <div class="form-group form-group--flex1">
            <label class="form-label"><?= __('sr_resigned_staff') ?></label>
            <input type="number" name="resigned_staff" class="form-control"
                   step="1" min="0" value="<?= $val('resigned_staff', '0') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= __('sr_hire_registrations') ?></label>
        <textarea name="hire_registrations" class="form-control" rows="3"><?= $val('hire_registrations') ?></textarea>
    </div>

    <h3 class="form-section-title"><?= __('sr_section_notes') ?></h3>

    <div class="form-group">
        <label class="form-label"><?= __('sr_remarks') ?></label>
        <textarea name="remarks" class="form-control" rows="4"><?= $val('remarks') ?></textarea>
    </div>

    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('sr_create'))->primary()->submit()->render() ?>
        <a href="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
<?php
$body = ob_get_clean();
echo Card::make()->body($body)->render();
?>
