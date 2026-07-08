<?php
/**
 * Template HTML optimisé mPDF — Fiche de paie individuelle
 * Rendu sans layout, utilisé uniquement pour la génération PDF serveur.
 *
 * @var array  $store
 * @var array  $user
 * @var array  $membership
 * @var int    $period
 * @var string $since
 * @var string $today
 * @var string $currency
 * @var array  $shiftRows
 * @var int    $totalGrossMin
 * @var int    $totalNetMin
 * @var float  $totalCost
 * @var bool   $anyRate
 */

include __DIR__ . '/../_partials/_payslip-helpers.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-base.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-payslip.css');
?>
</style>
</head>
<body>

<!-- En-tête -->
<table class="header-table">
  <tr>
    <td class="vt">
      <span class="brand">Kintai<span class="brand-store"><?= htmlspecialchars($store['name'] ?? '') ?></span></span>
      <?php if (!empty($store['address'])): ?>
        <div class="brand-address"><?= htmlspecialchars($store['address']) ?></div>
      <?php endif; ?>
    </td>
    <td class="doc-title vt">
      <span class="doc-title-main"><?= __('payslip_title') ?></span>
      <span class="doc-ref"><?= __('payslip_ref') ?> <?= date('Ymd') ?>-<?= (int) $user['id'] ?></span>
      <span class="doc-ref"><?= __('payslip_issued_on') ?> <?= date('d/m/Y') ?></span>
    </td>
  </tr>
</table>
<hr class="header-sep">

<!-- Bloc infos employeur + employé -->
<table class="info-outer">
  <tr>
    <td class="col-left">
      <div class="info-box">
        <div class="info-box-title"><?= __('payslip_employer') ?></div>
        <p>
          <strong><?= htmlspecialchars($store['name'] ?? '') ?></strong><br>
          <?php if (!empty($store['address'])): ?><?= htmlspecialchars($store['address']) ?><br><?php endif; ?>
          <?php if (!empty($store['city'])): ?><?= htmlspecialchars($store['city']) ?><br><?php endif; ?>
          <?php if (!empty($store['phone'])): ?><?= htmlspecialchars($store['phone']) ?><?php endif; ?>
        </p>
      </div>
    </td>
    <td class="col-right">
      <div class="info-box">
        <div class="info-box-title"><?= __('payslip_employee') ?></div>
        <p>
          <strong><?= htmlspecialchars($empName) ?></strong><br>
          <?php if (!empty($user['employee_code'])): ?><?= __('payslip_employee_id') ?> <?= htmlspecialchars($user['employee_code']) ?><br><?php endif; ?>
          <?= __('payslip_function') ?> <?= htmlspecialchars($role) ?><br>
          <?php if (!empty($membership['contract_type'])): ?><?= __('payslip_contract') ?> <?= htmlspecialchars($membership['contract_type']) ?><br><?php endif; ?>
          <?php if (!empty($membership['hire_date'])): ?><?= __('payslip_hire_date') ?> <?= payslip_date($membership['hire_date']) ?><?php endif; ?>
        </p>
      </div>
    </td>
  </tr>
</table>

<!-- Bandeau période -->
<div class="period-banner">
  <strong><?= __('payslip_period') ?></strong> <?= __('period_from_to', ['from' => payslip_date($since), 'to' => payslip_date($today)]) ?>
</div>

<!-- Tableau des shifts -->
<?php if (empty($shiftRows)): ?>
  <p class="empty-msg"><?= __('payslip_no_shift') ?></p>
<?php else: ?>
<table class="shifts-table">
  <thead>
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
  </thead>
  <tbody>
    <?php foreach ($shiftRows as $i => $row): ?>
    <tr class="<?= $i % 2 === 0 ? '' : 'even' ?>">
      <td class="muted"><?= payslip_dow($row['date']) ?></td>
      <td><?= payslip_date($row['date']) ?></td>
      <td><?= htmlspecialchars($row['type']) ?></td>
      <td class="muted"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
      <td class="tr"><?= payslip_hours($row['gross_min']) ?></td>
      <td class="tr muted"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
      <td class="tr"><?= payslip_hours($row['net_min']) ?></td>
      <?php if ($anyRate): ?>
      <td class="tr muted"><?= $row['has_rate'] ? number_format($row['rate'], 2) . ' ' . htmlspecialchars($currency) : '—' ?></td>
      <td class="tr"><?= $row['has_rate'] ? format_currency($row['cost'], $currency) : '—' ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4"><strong><?= __('total_row') ?></strong></td>
      <td class="tr"><?= payslip_hours($totalGrossMin) ?></td>
      <td class="tr">—</td>
      <td class="tr"><?= payslip_hours($totalNetMin) ?></td>
      <?php if ($anyRate): ?>
      <td class="tr">—</td>
      <td class="tr"><?= format_currency($totalCost, $currency) ?></td>
      <?php endif; ?>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- Bloc financier récapitulatif -->
<?php if ($anyRate): ?>
<table class="fin-table">
  <thead>
    <tr>
      <th colspan="2" class="fin-th"><?= __('payslip_summary') ?></th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="fin-td"><?= __('payslip_gross_hours') ?></td>
      <td class="fin-td-r"><?= payslip_hours($totalGrossMin) ?></td>
    </tr>
    <tr>
      <td class="fin-td"><?= __('payslip_net_hours') ?></td>
      <td class="fin-td-r"><?= payslip_hours($totalNetMin) ?></td>
    </tr>
    <tr>
      <td class="fin-total-l"><?= __('gross_pay') ?></td>
      <td class="fin-total-r"><?= format_currency($totalCost, $currency) ?></td>
    </tr>
    <?php if ($deductionsEnabled && !empty($deductions)): ?>
    <tr>
      <td colspan="2" class="fin-section-hdr"><?= __('social_deductions') ?></td>
    </tr>
    <?php foreach ($deductions as $ded): ?>
    <tr>
      <td class="fin-ded-name">
        <?= isset($ded['label_key']) ? __($ded['label_key']) : htmlspecialchars($ded['label'] ?? '') ?>
        <?php if (!empty($ded['is_flat'])): ?>
          <span class="fin-note"> (<?= __('monthly_fixed') ?>)</span>
        <?php elseif (isset($ded['rate'])): ?>
          <span class="fin-note"> (<?= number_format((float) $ded['rate'], 2) ?>%)</span>
        <?php endif; ?>
      </td>
      <td class="fin-ded-val">−<?= format_currency($ded['amount'], $currency) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
      <td class="fin-ded-tot-l"><?= __('total_deductions') ?></td>
      <td class="fin-ded-tot-r">−<?= format_currency($totalDeductions, $currency) ?></td>
    </tr>
    <tr>
      <td class="fin-net-l"><?= __('net_pay') ?></td>
      <td class="fin-net-r"><?= format_currency($netPay, $currency) ?></td>
    </tr>
    <?php elseif ($deductionsEnabled): ?>
    <tr>
      <td class="fin-net-l"><?= __('net_pay') ?></td>
      <td class="fin-net-r"><?= format_currency($totalCost, $currency) ?></td>
    </tr>
    <?php endif; ?>
  </tbody>
</table>
<?php else: ?>
<div class="summary-outer">
  <table class="summary-table">
    <tr><td><?= __('payslip_gross_hours') ?></td><td><?= payslip_hours($totalGrossMin) ?></td></tr>
    <tr><td><?= __('payslip_net_hours') ?></td><td><?= payslip_hours($totalNetMin) ?></td></tr>
  </table>
</div>
<?php endif; ?>

<?php if (!$anyRate): ?>
<div class="no-rate-box">
  <?= __('payslip_no_rate_warning') ?>
</div>
<?php else: ?>
<p class="rate-info">
  <?= __('payslip_rate_info') ?>
</p>
<?php endif; ?>

<!-- Zone de signature -->
<table class="sig-table">
  <tr>
    <td class="sig-col-left">
      <div class="sig-label"><?= __('payslip_sig_employer') ?></div>
      <div class="sig-area"></div>
      <div class="sig-name"><?= htmlspecialchars($store['name'] ?? '') ?></div>
    </td>
    <td class="sig-col-right">
      <div class="sig-label"><?= __('payslip_sig_employee') ?></div>
      <div class="sig-area"></div>
      <div class="sig-name"><?= htmlspecialchars($empName) ?></div>
    </td>
  </tr>
</table>

<!-- Pied de page -->
<div class="footer">
  <?= __('payslip_footer', ['date' => date('d/m/Y H:i')]) ?>
</div>

</body>
</html>
