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

$fmt = fn(?string $v, string $def = '—') => $v !== null && $v !== '' ? htmlspecialchars($v) : $def;
$cur = fn(?string $v) => $v !== null && $v !== '' ? format_currency((float) $v, $currency) : '—';
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
        <th><?= __('sr_active_employees') ?></th>
        <td class="tr td-mono"><?= $fmt($report['active_employees'] ?? null) ?></td>
    </tr>
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
    <tr>
        <th><?= __('sr_new_hires') ?></th>
        <td class="tr td-mono"><?= $fmt($report['new_hires'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('sr_resigned_staff') ?></th>
        <td class="tr td-mono"><?= $fmt($report['resigned_staff'] ?? null) ?></td>
    </tr>
    <?php if (!empty($report['hire_registrations'])): ?>
    <tr>
        <th><?= __('sr_hire_registrations') ?></th>
        <td><?= nl2br($fmt($report['hire_registrations'], '')) ?></td>
    </tr>
    <?php endif; ?>
</table>

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
