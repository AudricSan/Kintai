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

function pdfH(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}
function pdfD(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
function pdfDow(string $date): string {
    $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $dt   = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $days[(int) $dt->format('w')] : '';
}

$empName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    ?: ($user['display_name'] ?? ($user['email'] ?? ''));
$role = __(['admin' => 'role_owner', 'manager' => 'role_manager', 'staff' => 'role_employee'][$membership['role'] ?? 'staff'] ?? 'role_employee');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11pt; color: #222; margin: 0; padding: 0; }
table { border-collapse: collapse; width: 100%; }
h1 { margin: 0; padding: 0; }

/* En-tête */
.header-table { margin-bottom: 10pt; }
.brand { font-size: 18pt; font-weight: bold; color: #2c3e50; }
.brand-store { font-size: 8.5pt; font-weight: normal; color: #666; display: block; margin-top: 2pt; }
.brand-address { font-size: 8pt; color: #888; margin-top: 3pt; }
.doc-title { text-align: right; }
.doc-title-main { font-size: 14pt; font-weight: bold; color: #2c3e50; }
.doc-ref { font-size: 8.5pt; color: #666; display: block; margin-top: 2pt; }
.header-sep { border: none; border-top: 3pt solid #2c3e50; margin-bottom: 10pt; }

/* Infos employeur / employé */
.info-outer { margin-bottom: 10pt; }
.info-box { border: 1pt solid #ddd; padding: 6pt 8pt; }
.info-box-title { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #888; border-bottom: 1pt solid #eee; padding-bottom: 3pt; margin-bottom: 4pt; }
.info-box p { font-size: 9.5pt; line-height: 1.5; margin: 0; }
.info-box strong { color: #2c3e50; }

/* Bandeau période */
.period-banner { background: #eaf2fb; border-left: 4pt solid #3498db; padding: 4pt 8pt; margin-bottom: 10pt; font-size: 9.5pt; color: #2c3e50; }

/* Tableau shifts */
.shifts-table { font-size: 9pt; margin-bottom: 10pt; }
.shifts-table th { background: #2c3e50; color: #fff; padding: 3.5pt 5pt; text-align: left; font-size: 8.5pt; }
.shifts-table th.tr { text-align: right; }
.shifts-table td { padding: 3pt 5pt; border-bottom: 1pt solid #eee; }
.shifts-table td.tr { text-align: right; }
.shifts-table td.muted { color: #888; font-size: 8.5pt; }
.shifts-table .even td { background: #fafafa; }
.shifts-table tfoot td { background: #ecf0f1; font-weight: bold; border-top: 2pt solid #bdc3c7; }
.empty-msg { text-align: center; padding: 15pt; color: #888; font-style: italic; font-size: 9.5pt; }

/* Récapitulatif */
.summary-outer { text-align: right; margin-bottom: 10pt; }
.summary-table { width: 220pt; border: 1pt solid #ddd; }
.summary-table td { padding: 3pt 7pt; font-size: 9.5pt; border-bottom: 1pt solid #eee; }
.summary-table td:last-child { text-align: right; font-weight: 600; }
.summary-total td { background: #2c3e50; color: #fff; font-weight: bold; border: none; }

/* Notes */
.rate-info { font-size: 7.5pt; color: #888; font-style: italic; margin-bottom: 8pt; }
.no-rate-box { background: #fef9e7; border: 1pt solid #f9c84d; padding: 5pt 8pt; font-size: 8.5pt; color: #7d6608; margin-bottom: 8pt; }

/* Signatures */
.sig-table { margin-top: 18pt; }
.sig-label { font-size: 8.5pt; color: #555; border-top: 1pt solid #ccc; padding-top: 3pt; }
.sig-area { height: 25pt; }
.sig-name { font-size: 8.5pt; color: #555; }

/* Pied de page */
.footer { margin-top: 12pt; border-top: 1pt solid #ddd; padding-top: 5pt; font-size: 7pt; color: #bbb; text-align: center; }

/* Alignement vertical générique */
.vt { vertical-align: top; }

/* Colonnes infos employeur / employé */
.col-left  { width: 50%; vertical-align: top; padding-right: 6pt; }
.col-right { width: 50%; vertical-align: top; padding-left:  6pt; }

/* Tableau financier récapitulatif */
.fin-table   { width: 60%; margin-left: 40%; margin-bottom: 10pt; border-collapse: collapse; }
.fin-th      { background: #2c3e50; color: #fff; padding: 4pt 7pt; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: .5pt; }
.fin-td      { padding: 2.5pt 7pt; font-size: 9pt; color: #666; border-bottom: 1pt solid #f5f5f5; }
.fin-td-r    { padding: 2.5pt 7pt; font-size: 9pt; color: #666; text-align: right; border-bottom: 1pt solid #f5f5f5; }
.fin-total-l { padding: 4pt 7pt; font-weight: bold; background: #ecf0f1; border-top: 1pt solid #bdc3c7; border-bottom: 2pt solid #2c3e50; }
.fin-total-r { padding: 4pt 7pt; font-weight: bold; background: #ecf0f1; border-top: 1pt solid #bdc3c7; border-bottom: 2pt solid #2c3e50; text-align: right; }
.fin-section-hdr { padding: 3pt 7pt; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .5pt; color: #888; background: #f8f9fa; border-top: 3pt solid #ecf0f1; }
.fin-ded-name  { padding: 2.5pt 7pt 2.5pt 14pt; font-size: 9pt; border-bottom: 1pt solid #f0f0f0; }
.fin-note      { color: #999; font-size: 8pt; }
.fin-ded-val   { padding: 2.5pt 7pt; font-size: 9pt; text-align: right; color: #c0392b; border-bottom: 1pt solid #f0f0f0; }
.fin-ded-tot-l { padding: 3pt 7pt; font-weight: 600; background: #fdf2f0; border-top: 1pt solid #e8b4b0; border-bottom: 2pt solid #c0392b; color: #c0392b; }
.fin-ded-tot-r { padding: 3pt 7pt; font-weight: 600; background: #fdf2f0; border-top: 1pt solid #e8b4b0; border-bottom: 2pt solid #c0392b; color: #c0392b; text-align: right; }
.fin-net-l     { padding: 5pt 7pt; font-weight: bold; font-size: 10pt; background: #1a252f; color: #fff; }
.fin-net-r     { padding: 5pt 7pt; font-weight: bold; font-size: 11pt; background: #1a252f; color: #fff; text-align: right; }

/* Colonnes de signature */
.sig-col-left  { width: 50%; vertical-align: top; padding-right: 20pt; }
.sig-col-right { width: 50%; vertical-align: top; padding-left:  20pt; }
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
          <?php if (!empty($membership['hire_date'])): ?><?= __('payslip_hire_date') ?> <?= pdfD($membership['hire_date']) ?><?php endif; ?>
        </p>
      </div>
    </td>
  </tr>
</table>

<!-- Bandeau période -->
<div class="period-banner">
  <strong><?= __('payslip_period') ?></strong> <?= __('period_from_to', ['from' => pdfD($since), 'to' => pdfD($today)]) ?>
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
      <td class="muted"><?= pdfDow($row['date']) ?></td>
      <td><?= pdfD($row['date']) ?></td>
      <td><?= htmlspecialchars($row['type']) ?></td>
      <td class="muted"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
      <td class="tr"><?= pdfH($row['gross_min']) ?></td>
      <td class="tr muted"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
      <td class="tr"><?= pdfH($row['net_min']) ?></td>
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
      <td class="tr"><?= pdfH($totalGrossMin) ?></td>
      <td class="tr">—</td>
      <td class="tr"><?= pdfH($totalNetMin) ?></td>
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
      <td class="fin-td-r"><?= pdfH($totalGrossMin) ?></td>
    </tr>
    <tr>
      <td class="fin-td"><?= __('payslip_net_hours') ?></td>
      <td class="fin-td-r"><?= pdfH($totalNetMin) ?></td>
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
    <tr><td><?= __('payslip_gross_hours') ?></td><td><?= pdfH($totalGrossMin) ?></td></tr>
    <tr><td><?= __('payslip_net_hours') ?></td><td><?= pdfH($totalNetMin) ?></td></tr>
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
