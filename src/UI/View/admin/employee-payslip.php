<?php
/**
 * Fiche de paie individuelle — document HTML autonome (sans layout)
 *
 * @var array  $store
 * @var array  $user
 * @var array  $membership
 * @var int    $period
 * @var string $since
 * @var string $today
 * @var string $currency
 * @var array  $shiftRows      [date, type, start, end, gross_min, pause_min, net_min, rate, cost, has_rate]
 * @var int    $totalGrossMin
 * @var int    $totalNetMin
 * @var float  $totalCost
 * @var bool   $anyRate
 * @var string $BASE_URL
 */

function psHours(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}

function psDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

function psDow(string $date): string {
    $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $days[(int) $dt->format('w')] : '';
}

$empName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    ?: ($user['display_name'] ?? ($user['email'] ?? ''));

$role = __(['admin' => 'role_owner', 'manager' => 'role_manager', 'staff' => 'role_employee'][$membership['role'] ?? 'staff'] ?? 'role_employee');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payslip') ?> — <?= htmlspecialchars($empName) ?></title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/payslip.css">
</head>
<body>

<!-- Barre d'outils (écran uniquement) -->
<div class="ps-toolbar">
    <button onclick="window.print()">🖨 <?= __('print_payslip') ?></button>
    <?php
    $pdfHref = ($BASE_URL ?? '') . '/admin/stores/' . (int) $store['id']
        . '/employee-report/' . (int) $user['id'] . '/payslip/pdf?from=' . urlencode($since) . '&to=' . urlencode($today);
    ?>
    <a href="<?= htmlspecialchars($pdfHref) ?>" class="ps-pdf-btn">⬇ <?= __('download_pdf') ?></a>
    <a href="javascript:window.close()">✕ <?= __('close') ?></a>
    <span class="ps-toolbar__spacer">
        <?= __('print_hint') ?>
    </span>
</div>

<!-- Feuille A4 -->
<div class="ps-page">

    <!-- En-tête -->
    <div class="ps-header">
        <div>
            <div class="ps-brand">Kintai
                <small><?= htmlspecialchars($store['name'] ?? '') ?></small>
            </div>
            <?php if (!empty($store['address'])): ?>
                <p class="ps-brand-address"><?= htmlspecialchars($store['address']) ?></p>
            <?php endif; ?>
        </div>
        <div class="ps-doc-title">
            <h1><?= __('payslip_title') ?></h1>
            <p><?= __('payslip_ref') ?> <?= date('Ymd') ?>-<?= (int) $user['id'] ?></p>
            <p><?= __('payslip_issued_on') ?> <?= date('d/m/Y') ?></p>
        </div>
    </div>

    <!-- Bloc infos store + employé -->
    <div class="ps-info-grid">
        <div class="ps-info-block">
            <h3><?= __('payslip_employer') ?></h3>
            <p>
                <strong><?= htmlspecialchars($store['name'] ?? '') ?></strong><br>
                <?php if (!empty($store['address'])): ?>
                    <?= htmlspecialchars($store['address']) ?><br>
                <?php endif; ?>
                <?php if (!empty($store['city'])): ?>
                    <?= htmlspecialchars($store['city']) ?><br>
                <?php endif; ?>
                <?php if (!empty($store['phone'])): ?>
                    <?= htmlspecialchars($store['phone']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="ps-info-block">
            <h3><?= __('payslip_employee') ?></h3>
            <p>
                <strong><?= htmlspecialchars($empName) ?></strong><br>
                <?php if (!empty($user['employee_code'])): ?>
                    <?= __('payslip_employee_id') ?> <?= htmlspecialchars($user['employee_code']) ?><br>
                <?php endif; ?>
                <?= __('payslip_function') ?> <?= htmlspecialchars($role) ?><br>
                <?php if (!empty($membership['contract_type'])): ?>
                    <?= __('payslip_contract') ?> <?= htmlspecialchars($membership['contract_type']) ?><br>
                <?php endif; ?>
                <?php if (!empty($membership['hire_date'])): ?>
                    <?= __('payslip_hire_date') ?> <?= psDate($membership['hire_date']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Bandeau période -->
    <div class="ps-period-banner">
        <strong><?= __('payslip_period') ?></strong>
        <?= __('period_from_to', ['from' => psDate($since), 'to' => psDate($today)]) ?>
    </div>

    <!-- Tableau des shifts -->
    <?php if (empty($shiftRows)): ?>
        <p class="ps-empty"><?= __('payslip_no_shift') ?></p>
    <?php else: ?>
    <table class="ps-table">
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
            <?php foreach ($shiftRows as $row): ?>
            <tr>
                <td class="muted"><?= psDow($row['date']) ?></td>
                <td><?= psDate($row['date']) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td class="muted"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
                <td class="tr"><?= psHours($row['gross_min']) ?></td>
                <td class="tr muted"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
                <td class="tr"><?= psHours($row['net_min']) ?></td>
                <?php if ($anyRate): ?>
                <td class="tr muted"><?= $row['has_rate'] ? number_format($row['rate'], 2) . ' ' . $currency : '—' ?></td>
                <td class="tr"><?= $row['has_rate'] ? format_currency($row['cost'], $currency) : '—' ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="<?= $anyRate ? 4 : 4 ?>"><strong><?= __('total_row') ?></strong></td>
                <td class="tr"><?= psHours($totalGrossMin) ?></td>
                <td class="tr">—</td>
                <td class="tr"><?= psHours($totalNetMin) ?></td>
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
    <div class="ps-financials">
        <table class="ps-fin-table">
            <thead>
                <tr><th colspan="2"><?= __('payslip_summary') ?></th></tr>
            </thead>
            <tbody>
                <tr class="ps-fin-row-info">
                    <td><?= __('payslip_gross_hours') ?></td>
                    <td class="tr"><?= psHours($totalGrossMin) ?></td>
                </tr>
                <tr class="ps-fin-row-info">
                    <td><?= __('payslip_net_hours') ?></td>
                    <td class="tr"><?= psHours($totalNetMin) ?></td>
                </tr>
                <tr class="ps-fin-row-gross">
                    <td><?= __('gross_pay') ?></td>
                    <td class="tr"><?= format_currency($totalCost, $currency) ?></td>
                </tr>
                <?php if ($deductionsEnabled && !empty($deductions)): ?>
                <tr class="ps-fin-row-section">
                    <td colspan="2"><?= __('social_deductions') ?></td>
                </tr>
                <?php foreach ($deductions as $ded): ?>
                <tr class="ps-fin-row-ded">
                    <td>
                        <?= isset($ded['label_key']) ? __($ded['label_key']) : htmlspecialchars($ded['label'] ?? '') ?>
                        <?php if (!empty($ded['is_flat'])): ?>
                            <span class="ps-fin-rate">(<?= __('monthly_fixed') ?>)</span>
                        <?php elseif (isset($ded['rate'])): ?>
                            <span class="ps-fin-rate">(<?= number_format((float) $ded['rate'], 2) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="tr ps-fin-ded-amount">−<?= format_currency($ded['amount'], $currency) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="ps-fin-row-total-ded">
                    <td><?= __('total_deductions') ?></td>
                    <td class="tr">−<?= format_currency($totalDeductions, $currency) ?></td>
                </tr>
                <tr class="ps-fin-row-net">
                    <td><?= __('net_pay') ?></td>
                    <td class="tr"><?= format_currency($netPay, $currency) ?></td>
                </tr>
                <?php elseif ($deductionsEnabled): ?>
                <tr class="ps-fin-row-net">
                    <td><?= __('net_pay') ?></td>
                    <td class="tr"><?= format_currency($totalCost, $currency) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="ps-rate-info"><?= __('payslip_rate_info') ?></p>
    <?php else: ?>
    <div class="ps-summary">
        <div class="ps-summary-box">
            <div class="ps-summary-row">
                <span><?= __('payslip_gross_hours') ?></span>
                <span><?= psHours($totalGrossMin) ?></span>
            </div>
            <div class="ps-summary-row">
                <span><?= __('payslip_net_hours') ?></span>
                <span><?= psHours($totalNetMin) ?></span>
            </div>
        </div>
    </div>
    <p class="ps-no-rate"><?= __('payslip_no_rate_warning') ?></p>
    <?php endif; ?>

    <!-- Zone de signature -->
    <div class="ps-signatures">
        <div class="ps-sig-block">
            <p><?= __('payslip_sig_employer') ?></p>
            <div class="ps-sig-area"></div>
            <p><?= htmlspecialchars($store['name'] ?? '') ?></p>
        </div>
        <div class="ps-sig-block">
            <p><?= __('payslip_sig_employee') ?></p>
            <div class="ps-sig-area"></div>
            <p><?= htmlspecialchars($empName) ?></p>
        </div>
    </div>

    <!-- Pied de page -->
    <div class="ps-footer">
        <?= __('payslip_footer', ['date' => date('d/m/Y H:i')]) ?>
    </div>

</div>

<script>
    // Imprimer automatiquement si demandé
    <?php if ($autoprint ?? false): ?>
    window.addEventListener('load', () => window.print());
    <?php endif; ?>
</script>

</body>
</html>
