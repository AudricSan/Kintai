<?php
/**
 * Page de statistiques individuelles d'un employé
 *
 * @var array  $store
 * @var array  $user
 * @var array  $membership
 * @var int    $period
 * @var string $since
 * @var string $today
 * @var string $currency
 * @var int    $totalShifts
 * @var float  $grossHours
 * @var float  $netHours
 * @var float  $cost
 * @var bool   $anyRate
 * @var int    $workDays
 * @var int    $absDays
 * @var int    $maxConsec
 * @var int    $swapCount
 * @var array  $monthlyHours   ['YYYY-MM' => float]
 * @var array  $typeHours      ['type name' => float]
 * @var array  $dowChart       ['Lun' => float, ...]
 * @var array  $recentShifts
 * @var array  $storeTypesMap
 * @var string $payslipUrl
 * @var string $pdfUrl
 * @var string $BASE_URL
 */

$empName  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    ?: ($user['display_name'] ?? ($user['email'] ?? ''));
$roleMap  = ['admin' => __('role_owner'), 'manager' => __('role_manager'), 'staff' => __('role_employee')];
$roleLabel = $roleMap[$membership['role'] ?? 'staff'] ?? __('role_employee');

function estatBar(array $data, string $color = 'var(--color-primary)', int $maxH = 80): string {
    if (!$data || max(array_values($data)) == 0) {
        return '<em class="sstat-empty">' . htmlspecialchars(__('no_data')) . '</em>';
    }
    $max = max(array_values($data)) ?: 1;
    $out = '<div class="sstat-bar-chart">';
    foreach ($data as $label => $val) {
        $h = round($val / $max * $maxH);
        $out .= '<div class="sstat-bar-col">'
            . '<div class="sstat-bar" style="height:' . $h . 'px;background:' . $color . '" title="'
            . htmlspecialchars($label) . ': ' . round($val, 1) . 'h"></div>'
            . '<div class="sstat-bar-label">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }
    return $out . '</div>';
}

function estatHours(float $hours): string {
    $h = intdiv((int) round($hours * 60), 60);
    $m = (int) round($hours * 60) % 60;
    return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}

function estatDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

$reportUrl = $BASE_URL . '/admin/stores/' . (int) $store['id'] . '/employee-report?period=' . $period;
?>

<div class="page-header">
  <h2 class="page-header__title">
    <?= __('employee_stats') ?> — <?= htmlspecialchars($empName) ?>
    <span class="page-count"><?= htmlspecialchars($store['name'] ?? '') ?></span>
  </h2>
  <div class="page-header__actions">
    <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn--primary btn--sm">
      ⬇ <?= __('download_pdf') ?>
    </a>
    <a href="<?= htmlspecialchars($payslipUrl) ?>" target="_blank" class="btn btn--ghost btn--sm">
      🖨 <?= __('payslip') ?>
    </a>
    <a href="<?= htmlspecialchars($reportUrl) ?>" class="btn btn--ghost btn--sm">← <?= __('back_to_report') ?></a>
  </div>
</div>

<!-- Navigation des périodes -->
<div class="sstat-period-nav">
  <?php foreach ([7 => __('period_7d'), 30 => __('period_30d'), 90 => __('period_3m'), 180 => __('period_6m'), 365 => __('period_1y')] as $d => $label): ?>
    <a href="?period=<?= $d ?>" class="<?= $period == $d ? 'active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<!-- Profil employé -->
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('employee_name') ?></div>
  <div class="estat-profile">
    <div class="estat-avatar" style="background:<?= htmlspecialchars($user['color'] ?? '#3B82F6') ?>">
      <?= strtoupper(substr($empName, 0, 1)) ?>
    </div>
    <div class="estat-profile-info">
      <div class="estat-profile-name"><?= htmlspecialchars($empName) ?></div>
      <div class="estat-profile-meta">
        <span class="badge badge--<?= ($membership['role'] ?? 'staff') === 'admin' ? 'primary' : (($membership['role'] ?? 'staff') === 'manager' ? 'warning' : 'default') ?>"><?= htmlspecialchars($roleLabel) ?></span>
        <?php if (!empty($user['employee_code'])): ?>
          <span class="estat-code">N° <?= htmlspecialchars($user['employee_code']) ?></span>
        <?php endif; ?>
        <span class="estat-email"><?= htmlspecialchars($user['email'] ?? '') ?></span>
      </div>
    </div>
  </div>
</div>

<!-- KPI cards -->
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('on_period') ?> — <?= htmlspecialchars($since) ?> / <?= htmlspecialchars($today) ?></div>
  <div class="sstat-grid">

    <div class="sstat-card">
      <div class="sstat-card__value text-primary"><?= $totalShifts ?></div>
      <div class="sstat-card__label"><?= __('shifts_col') ?></div>
      <div class="sstat-card__sub"><?= __('on_period') ?></div>
    </div>

    <div class="sstat-card">
      <div class="sstat-card__value text-primary"><?= $workDays ?></div>
      <div class="sstat-card__label"><?= __('kpi_work_days') ?></div>
      <div class="sstat-card__sub"><?= __('on_period') ?></div>
    </div>

    <div class="sstat-card">
      <div class="sstat-card__value"><?= estatHours($grossHours) ?></div>
      <div class="sstat-card__label"><?= __('gross_h_col') ?></div>
      <div class="sstat-card__sub"><?= number_format($grossHours, 1) ?> h</div>
    </div>

    <div class="sstat-card">
      <div class="sstat-card__value text-primary"><?= estatHours($netHours) ?></div>
      <div class="sstat-card__label"><?= __('net_h_col') ?></div>
      <div class="sstat-card__sub"><?= number_format($netHours, 1) ?> h</div>
    </div>

    <?php if ($anyRate): ?>
    <div class="sstat-card">
      <div class="sstat-card__value text-success"><?= format_currency($cost, $currency) ?></div>
      <div class="sstat-card__label"><?= __('estimated_cost_col') ?></div>
      <div class="sstat-card__sub"><?= __('on_period') ?></div>
    </div>
    <?php endif; ?>

    <div class="sstat-card">
      <div class="sstat-card__value <?= $absDays > 3 ? 'erep-val--warn' : '' ?>"><?= $absDays ?: '—' ?></div>
      <div class="sstat-card__label"><?= __('kpi_absence_days') ?></div>
      <div class="sstat-card__sub"><?= __('approved_days') ?></div>
    </div>

    <div class="sstat-card">
      <div class="sstat-card__value <?= $maxConsec >= 6 ? 'erep-val--warn' : '' ?>"><?= $maxConsec ?: '—' ?></div>
      <div class="sstat-card__label"><?= __('kpi_max_consec') ?></div>
      <div class="sstat-card__sub"><?= __('max_consec_col') ?></div>
    </div>

    <div class="sstat-card">
      <div class="sstat-card__value"><?= $swapCount ?: '—' ?></div>
      <div class="sstat-card__label"><?= __('swaps_col') ?></div>
      <div class="sstat-card__sub"><?= __('on_period') ?></div>
    </div>

  </div>
</div>

<?php if (!$anyRate): ?>
<div class="alert alert--info"><?= __('no_rate_cost_hint') ?></div>
<?php endif; ?>

<!-- Graphique mensuel (12 derniers mois) -->
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('monthly_hours_chart') ?></div>
  <?php
    $monthLabels = [];
    foreach (array_keys($monthlyHours) as $ym) {
        $dt = \DateTime::createFromFormat('Y-m', $ym);
        $monthLabels[$dt ? $dt->format('M Y') : $ym] = round($monthlyHours[$ym], 1);
    }
  ?>
  <?= estatBar($monthLabels, 'var(--color-primary)') ?>
</div>

<!-- Répartition par type de shift -->
<?php if (!empty($typeHours)): ?>
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('hours_by_type_chart') ?></div>
  <?= estatBar(array_map(fn($h) => round($h, 1), $typeHours), 'var(--color-warning)') ?>
</div>
<?php endif; ?>

<!-- Répartition par jour de semaine -->
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('hours_by_dow_chart') ?></div>
  <?= estatBar(array_map(fn($h) => round($h, 1), $dowChart), 'var(--color-info, #6366f1)') ?>
</div>

<!-- Shifts récents -->
<div class="sstat-section">
  <div class="sstat-section-title"><?= __('recent_shifts') ?></div>

  <?php if (empty($recentShifts)): ?>
    <p class="sstat-empty"><?= __('no_data') ?></p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th><?= __('date') ?></th>
          <th><?= __('shift_type') ?></th>
          <th><?= __('start_time') ?></th>
          <th><?= __('end_time') ?></th>
          <th class="td-right"><?= __('gross_h_col') ?></th>
          <th class="td-right"><?= __('net_h_col') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentShifts as $s): ?>
        <?php
          $tid     = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
          $typName = $tid ? ($storeTypesMap[$tid]['name'] ?? '—') : '—';
          $gMin    = (int) $s['duration_minutes'];
          $nMin    = max(0, $gMin - (int) $s['pause_minutes']);
        ?>
        <tr>
          <td><?= estatDate($s['shift_date']) ?></td>
          <td><?= htmlspecialchars($typName) ?></td>
          <td class="td-mono"><?= htmlspecialchars(substr($s['start_time'] ?? '00:00', 0, 5)) ?></td>
          <td class="td-mono"><?= htmlspecialchars(substr($s['end_time'] ?? '00:00', 0, 5)) ?></td>
          <td class="td-right td-mono"><?= estatHours($gMin / 60) ?></td>
          <td class="td-right td-mono"><?= estatHours($nMin / 60) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

