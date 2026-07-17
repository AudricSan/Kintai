<?php

declare(strict_types=1);

/**
 * Template HTML optimisé mPDF — 給与報告書 (Salary Report)
 * Rendu sans layout, utilisé uniquement pour la génération PDF serveur.
 *
 * @var array  $report
 * @var array  $store
 */

$currency = $store['currency'] ?? 'JPY';

$fmt = fn(mixed $v, string $def = '—') => $v !== null && $v !== '' ? htmlspecialchars((string) $v) : $def;
$cur = fn(mixed $v) => $v !== null && $v !== '' ? format_currency((float) $v, $currency) : '—';
$isEmployeeScoped = !empty($report['user_id']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-base.css');
?>
body { font-family: sans-serif; font-size: 10pt; color: #222; padding: 20px; }
h1 { font-size: 16pt; text-align: center; margin-bottom: 4px; }
.subtitle { text-align: center; font-size: 10pt; color: #666; margin-bottom: 20px; }
h2 { font-size: 12pt; border-bottom: 2px solid #333; padding-bottom: 4px; margin-top: 20px; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
th, td { padding: 5px 8px; text-align: left; border: 1px solid #ccc; }
th { background: #f0f0f0; font-weight: 600; }
.tr { text-align: right; }
.td-mono { font-family: 'Courier New', monospace; }
.sig-section { margin-top: 40px; }
.sig-section table { border: none; }
.sig-section td { border: none; padding: 20px 10px; }
.sig-line { border-top: 1px solid #222; width: 200px; margin-top: 40px; }
.footer { text-align: center; font-size: 8pt; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>

<h1><?= __('sr_pdf_title') ?></h1>
<p class="subtitle">
    <?= htmlspecialchars($store['name'] ?? '') ?> —
    <?= $fmt($report['target_month'] ?? '') ?>
</p>

<!-.- Informations de base -->
<h2><?= __('sr_section_basic') ?></h2>
<table>
    <tr>
        <th style="width:30%"><?= __('sr_target_month') ?></th>
        <td><?= $fmt($report['target_month'] ?? '') ?></td>
    </tr>
    <tr>
        <th><?= __('store') ?></th>
        <td><?= $fmt($report['store_name'] ?? ($store['name'] ?? '')) ?></td>
    </tr>
    <?php if (!empty($report['user_id'])): ?>
    <tr>
        <th><?= __('employee_name') ?></th>
        <td><?= $fmt($report['employee_name'] ?? '') ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th><?= __('sr_person_in_charge') ?></th>
        <td><?= $fmt($report['person_in_charge'] ?? '') ?></td>
    </tr>
</table>

<!-.- Résumé financier -->
<h2><?= __('sr_section_financial') ?></h2>
<table>
    <tr>
        <th style="width:50%"><?= __('sr_total_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['total_payment'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('sr_total_deductions') ?></th>
        <td class="tr td-mono"><?= $cur($report['total_deductions'] ?? null) ?></td>
    </tr>
    <tr>
        <th style="padding-left:16px"><?= __('sr_income_tax_base') ?></th>
        <td class="tr td-mono"><?= $cur($report['income_tax_base'] ?? null) ?></td>
    </tr>
    <tr>
        <th style="padding-left:16px"><?= __('sr_withholding_tax') ?></th>
        <td class="tr td-mono"><?= $cur($report['withholding_tax'] ?? null) ?></td>
    </tr>
    <tr>
        <th style="padding-left:16px"><?= __('sr_residence_tax') ?></th>
        <td class="tr td-mono"><?= $cur($report['residence_tax'] ?? null) ?></td>
    </tr>
    <tr>
        <th style="padding-left:16px"><?= __('sr_other_deductions') ?></th>
        <td class="tr td-mono"><?= $cur($report['other_deductions'] ?? null) ?></td>
    </tr>
    <tr style="font-weight:bold; background:#f5f5f5">
        <th><?= __('sr_net_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['net_payment'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('sr_hand_delivered_salary') ?></th>
        <td class="tr td-mono"><?= $cur($report['hand_delivered_salary'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('sr_bank_transfer_salary') ?></th>
        <td class="tr td-mono"><?= $cur($report['bank_transfer_salary'] ?? null) ?></td>
    </tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('sr_active_employees') ?></th>
        <td class="tr td-mono"><?= $fmt($report['active_employees'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-.- Personnel -->
<h2><?= __('sr_section_staff') ?></h2>
<table>
    <tr>
        <th style="width:50%"><?= __('sr_staff_man_hours') ?></th>
        <td class="tr td-mono"><?= $fmt($report['staff_man_hours'] ?? null) ?> h</td>
    </tr>
    <tr>
        <th><?= __('sr_staff_total_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['staff_total_payment'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('sr_staff_avg_hourly_wage') ?></th>
        <td class="tr td-mono"><?= $cur($report['staff_avg_hourly_wage'] ?? null) ?>/h</td>
    </tr>
    <tr>
        <th><?= __('sr_employee_work_hours') ?></th>
        <td><?= nl2br($fmt($report['employee_work_hours'] ?? null, '')) ?></td>
    </tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('sr_new_hires') ?></th>
        <td class="tr td-mono"><?= $fmt($report['new_hires'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('sr_resigned_staff') ?></th>
        <td class="tr td-mono"><?= $fmt($report['resigned_staff'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($report['hire_registrations'])): ?>
    <tr>
        <th><?= __('sr_hire_registrations') ?></th>
        <td><?= nl2br($fmt($report['hire_registrations'], '')) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if (isset($shiftRows)): ?>
<!-.- Détail des shifts (employé) -->
<h2><?= __('sr_shift_detail') ?></h2>
<?php if (empty($shiftRows)): ?>
<p><?= __('payslip_no_shift') ?></p>
<?php else: ?>
<?php include __DIR__ . '/../../../UI/View/_partials/_payslip-helpers.php'; ?>
<table>
    <tr>
        <th><?= __('col_day') ?></th>
        <th><?= __('date') ?></th>
        <th><?= __('shift_type') ?></th>
        <th><?= __('schedule') ?></th>
        <th class="tr"><?= __('gross_h_col') ?></th>
        <th class="tr"><?= __('pause') ?></th>
        <th class="tr"><?= __('net_h_col') ?></th>
        <?php if ($anyRate): ?>
        <th class="tr"><?= __('col_rate_h') ?></th>
        <th class="tr"><?= __('amount') ?></th>
        <?php endif; ?>
    </tr>
    <?php foreach ($shiftRows as $row): ?>
    <tr>
        <td><?= payslip_dow($row['date']) ?></td>
        <td><?= payslip_date($row['date']) ?></td>
        <td><?= htmlspecialchars($row['type']) ?></td>
        <td><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
        <td class="tr td-mono"><?= payslip_hours($row['gross_min']) ?></td>
        <td class="tr td-mono"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
        <td class="tr td-mono"><?= payslip_hours($row['net_min']) ?></td>
        <?php if ($anyRate): ?>
        <td class="tr td-mono"><?= $row['has_rate'] ? number_format($row['rate'], 2) . ' ' . $currency : '—' ?></td>
        <td class="tr td-mono"><?= $row['has_rate'] ? format_currency($row['cost'], $currency) : '—' ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <tr style="font-weight:bold; background:#f5f5f5">
        <td colspan="4"><?= __('total_row') ?></td>
        <td class="tr td-mono"><?= payslip_hours($totalGrossMin) ?></td>
        <td></td>
        <td class="tr td-mono"><?= payslip_hours($totalNetMin) ?></td>
        <?php if ($anyRate): ?>
        <td></td>
        <td class="tr td-mono"><?= format_currency($totalCost, $currency) ?></td>
        <?php endif; ?>
    </tr>
</table>
<?php if ($deductionsEnabled && !empty($deductions)): ?>
<h2><?= __('payslip_summary') ?></h2>
<table>
    <tr>
        <th style="width:70%"><?= __('gross_pay') ?></th>
        <td class="tr td-mono"><?= format_currency($totalCost, $currency) ?></td>
    </tr>
    <?php foreach ($deductions as $ded): ?>
    <tr>
        <th style="width:70%">
            <?= isset($ded['label_key']) ? __($ded['label_key']) : htmlspecialchars($ded['label'] ?? '') ?>
            <?php if (!empty($ded['is_flat'])): ?> (<?= __('monthly_fixed') ?>)<?php elseif (isset($ded['rate'])): ?> (<?= number_format((float) $ded['rate'], 2) ?>%)<?php endif; ?>
        </th>
        <td class="tr td-mono">−<?= format_currency($ded['amount'], $currency) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="font-weight:bold">
        <th><?= __('total_deductions') ?></th>
        <td class="tr td-mono">−<?= format_currency($totalDeductions, $currency) ?></td>
    </tr>
    <tr style="font-weight:bold; background:#f5f5f5">
        <th><?= __('net_pay') ?></th>
        <td class="tr td-mono"><?= format_currency($netPay, $currency) ?></td>
    </tr>
</table>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($report['remarks'])): ?>
<!-.- Remarques -->
<h2><?= __('sr_section_notes') ?></h2>
<p><?= nl2br($fmt($report['remarks'], '')) ?></p>
<?php endif; ?>

<!-.- Signature -->
<div class="sig-section">
    <table>
        <tr>
            <td style="text-align:center">
                <div><?= __('sr_pdf_prepared_by') ?></div>
                <div class="sig-line"></div>
                <div><?= $fmt($report['person_in_charge'] ?? '') ?></div>
            </td>
            <td style="text-align:center">
                <div><?= __('sr_pdf_approved_by') ?></div>
                <div class="sig-line"></div>
                <div><?= htmlspecialchars($store['name'] ?? '') ?></div>
            </td>
            <td style="text-align:center">
                <div><?= __('sr_pdf_date') ?></div>
                <div class="sig-line"></div>
                <div><?= date('Y/m/d') ?></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    <?= __('sr_pdf_footer', ['store' => htmlspecialchars($store['name'] ?? ''), 'date' => date('Y/m/d H:i')]) ?>
</div>

</body>
</html>
