<?php
/**
 * @var array  $store
 * @var int    $period
 * @var string $since
 * @var string $today
 * @var string $currency
 * @var array  $usersMap
 * @var array  $membersMap
 * @var int[]  $memberIds
 * @var array  $employeeStats   uid → [shifts, gross_hours, net_hours, cost, work_days, abs_days, swaps, max_consec, has_rate]
 * @var array  $storeTypesMap
 * @var int    $totalShifts
 * @var float  $totalNetHours
 * @var float  $totalGrossHours
 * @var float  $totalCost
 * @var int    $totalAbsDays
 * @var int    $activeCount
 * @var bool   $anyHasRate
 */

function repStatCard(string $label, string $value, string $sub = '', string $color = 'var(--color-primary)'): string {
    return '<div class="sstat-card">'
        . '<div class="sstat-card__value" style="color:' . $color . '">' . htmlspecialchars($value) . '</div>'
        . '<div class="sstat-card__label">' . htmlspecialchars($label) . '</div>'
        . ($sub !== '' ? '<div class="sstat-card__sub">' . htmlspecialchars($sub) . '</div>' : '')
        . '</div>';
}

function repBarChart(array $data, string $color = 'var(--color-primary)', int $maxBarHeight = 80): string {
    if (!$data) return '<em class="sstat-empty">' . __('no_data') . '</em>';
    $max = max(array_values($data)) ?: 1;
    $out = '<div class="sstat-bar-chart">';
    foreach ($data as $label => $val) {
        $h = round($val / $max * $maxBarHeight);
        $out .= '<div class="sstat-bar-col">'
            . '<div class="sstat-bar" style="height:' . $h . 'px;background:' . $color . '" title="' . htmlspecialchars($label) . ': ' . round($val, 1) . '"></div>'
            . '<div class="sstat-bar-label">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }
    return $out . '</div>';
}

function repUserName(array $usersMap, int $uid): string {
    if (!isset($usersMap[$uid])) return '#' . $uid;
    $u = $usersMap[$uid];
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $name ?: ($u['display_name'] ?? ($u['email'] ?? '#' . $uid));
}

function repRoleLabel(string $role): string {
    return match ($role) {
        'admin'   => __('role_owner'),
        'manager' => __('role_manager'),
        default   => __('role_employee'),
    };
}

function repHoursFormat(float $hours): string {
    $h = intdiv((int) round($hours * 60), 60);
    $m = (int) round($hours * 60) % 60;
    return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('report_employees') ?> — <?= htmlspecialchars($store['name'] ?? '') ?>
        <span class="page-count">
            <?= __('period_from_to', ['from' => htmlspecialchars($since), 'to' => htmlspecialchars($today)]) ?>
        </span>
    </h2>
    <div class="page-header__actions">
        <button onclick="window.print()" class="btn btn--primary btn--sm erep-print-btn">⎙ <?= __('export_pdf') ?></button>
        <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/stats" class="btn btn--ghost btn--sm erep-no-print"><?= __('statistics') ?></a>
        <a href="<?= $BASE_URL ?>/admin/stores" class="btn btn--ghost btn--sm erep-no-print"><?= __('back') ?></a>
    </div>
</div>

<!-- Navigation des périodes -->
<div class="sstat-period-nav">
    <?php foreach ([7 => __('period_7d'), 30 => __('period_30d'), 90 => __('period_3m'), 180 => __('period_6m'), 365 => __('period_1y')] as $d => $label): ?>
        <a href="?period=<?= $d ?>" class="<?= $period == $d ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- Cartes résumé -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_employee_perf') ?></div>
    <div class="sstat-grid">
        <?= repStatCard(
            __('active_employees'),
            $activeCount . ' / ' . count($memberIds),
            __('on_period'),
            'var(--color-primary)'
        ) ?>
        <?= repStatCard(
            __('shifts_analyzed'),
            (string) $totalShifts,
            __('on_period')
        ) ?>
        <?= repStatCard(
            __('net_hours'),
            repHoursFormat($totalNetHours),
            number_format($totalNetHours, 1) . ' h ' . __('on_period')
        ) ?>
        <?= repStatCard(
            __('gross_hours'),
            repHoursFormat($totalGrossHours),
            __('on_period')
        ) ?>
        <?php if ($anyHasRate): ?>
        <?= repStatCard(
            __('total_cost'),
            format_currency($totalCost, $currency),
            __('on_period'),
            'var(--color-success)'
        ) ?>
        <?php if ($activeCount > 0): ?>
        <?= repStatCard(
            __('avg_per_active_emp'),
            format_currency(round($totalCost / $activeCount, 2), $currency),
            __('on_period')
        ) ?>
        <?php endif; ?>
        <?php endif; ?>
        <?= repStatCard(
            __('approved_days'),
            (string) $totalAbsDays,
            __('cumulated_days'),
            'var(--color-warning)'
        ) ?>
    </div>
</div>

<?php if (!$anyHasRate): ?>
<div class="alert alert--info">
    <?= __('no_rate_cost_hint') ?>
</div>
<?php endif; ?>

<!-- Tableau détaillé par employé -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_employee_perf') ?></div>

    <?php if (empty($employeeStats)): ?>
        <p class="sstat-empty"><?= __('no_employee_shift') ?></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table erep-table">
            <thead>
                <tr>
                    <th><?= __('employee_name') ?></th>
                    <th><?= __('role_col') ?></th>
                    <th class="td-right"><?= __('shifts_col') ?></th>
                    <th class="td-right"><?= __('work_days_col') ?></th>
                    <th class="td-right"><?= __('gross_h_col') ?></th>
                    <th class="td-right"><?= __('net_h_col') ?></th>
                    <?php if ($anyHasRate): ?>
                    <th class="td-right"><?= __('estimated_cost_col') ?></th>
                    <th class="td-right"><?= __('avg_cost_h') ?></th>
                    <?php endif; ?>
                    <th class="td-right"><?= __('absence_days_col') ?></th>
                    <th class="td-right"><?= __('swaps_col') ?></th>
                    <th class="td-right"><?= __('max_consec_col') ?></th>
                    <th class="erep-no-print"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employeeStats as $uid => $stat): ?>
                <?php
                    $uid        = (int) $uid;
                    $uName      = repUserName($usersMap, $uid);
                    $role       = $membersMap[$uid]['role'] ?? 'staff';
                    $isActive   = $stat['shifts'] > 0;
                    $avgCostH   = $stat['net_hours'] > 0 ? round($stat['cost'] / $stat['net_hours'], 2) : 0;
                    $payslipUrl = $BASE_URL . '/admin/stores/' . (int) $store['id']
                                . '/employee-report/' . $uid . '/payslip?period=' . $period;
                ?>
                <tr class="<?= $isActive ? '' : 'erep-row--inactive' ?>">
                    <td>
                        <a href="<?= $BASE_URL ?>/admin/users/<?= $uid ?>/edit" class="erep-emp-link">
                            <?= htmlspecialchars($uName) ?>
                        </a>
                        <?php if (!$isActive): ?>
                            <span class="badge badge--muted ml-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge--<?= $role === 'admin' ? 'primary' : ($role === 'manager' ? 'warning' : 'default') ?>"><?= repRoleLabel($role) ?></span></td>
                    <td class="td-right td-mono"><?= $stat['shifts'] ?></td>
                    <td class="td-right td-mono"><?= $stat['work_days'] ?></td>
                    <td class="td-right td-mono"><?= repHoursFormat($stat['gross_hours']) ?></td>
                    <td class="td-right td-mono <?= $stat['net_hours'] > 0 ? 'erep-val--highlight' : '' ?>"><?= repHoursFormat($stat['net_hours']) ?></td>
                    <?php if ($anyHasRate): ?>
                    <td class="td-right td-mono <?= $stat['cost'] > 0 ? 'erep-val--cost' : 'td-muted' ?>">
                        <?= $stat['has_rate'] ? format_currency($stat['cost'], $currency) : '<span title="' . htmlspecialchars(__('no_rate_set')) . '">—</span>' ?>
                    </td>
                    <td class="td-right td-muted td-mono">
                        <?= ($stat['has_rate'] && $stat['net_hours'] > 0) ? format_currency($avgCostH, $currency) : '—' ?>
                    </td>
                    <?php endif; ?>
                    <td class="td-right td-mono <?= $stat['abs_days'] > 3 ? 'erep-val--warn' : '' ?>"><?= $stat['abs_days'] > 0 ? $stat['abs_days'] : '—' ?></td>
                    <td class="td-right td-mono"><?= $stat['swaps'] > 0 ? $stat['swaps'] : '—' ?></td>
                    <td class="td-right td-mono <?= $stat['max_consec'] >= 6 ? 'erep-val--warn' : '' ?>"><?= $stat['max_consec'] > 0 ? $stat['max_consec'] : '—' ?></td>
                    <td class="erep-no-print td-nowrap">
                        <?php $statsUrl = $BASE_URL . '/admin/stores/' . (int) $store['id']
                            . '/employee-report/' . $uid . '/stats?period=' . $period; ?>
                        <a href="<?= htmlspecialchars($statsUrl) ?>"
                           class="btn btn--ghost btn--sm" title="<?= __('employee_stats') ?>">
                            📊 <?= __('view_stats') ?>
                        </a>
                        <?php
                            $payslipBase = $BASE_URL . '/admin/stores/' . (int) $store['id']
                                . '/employee-report/' . $uid . '/payslip?from=__FROM__&to=__TO__';
                        ?>
                        <button type="button"
                                class="btn btn--ghost btn--sm ps-period-trigger"
                                data-url="<?= htmlspecialchars($payslipBase) ?>"
                                title="<?= __('payslip') ?>">
                            🖨 <?= __('payslip') ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="erep-total-row">
                    <td colspan="2"><strong><?= __('total_row') ?></strong></td>
                    <td class="td-right td-mono"><strong><?= $totalShifts ?></strong></td>
                    <td class="td-right">—</td>
                    <td class="td-right td-mono"><strong><?= repHoursFormat($totalGrossHours) ?></strong></td>
                    <td class="td-right td-mono"><strong><?= repHoursFormat($totalNetHours) ?></strong></td>
                    <?php if ($anyHasRate): ?>
                    <td class="td-right td-mono erep-val--cost"><strong><?= format_currency($totalCost, $currency) ?></strong></td>
                    <td class="td-right">—</td>
                    <?php endif; ?>
                    <td class="td-right td-mono"><strong><?= $totalAbsDays > 0 ? $totalAbsDays : '—' ?></strong></td>
                    <td class="td-right">—</td>
                    <td class="td-right">—</td>
                    <td class="erep-no-print"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Graphiques -->
<?php
$chartHours = [];
$chartCosts = [];
foreach ($employeeStats as $uid => $stat) {
    if ($stat['shifts'] > 0) {
        $label = repUserName($usersMap, (int) $uid);
        $chartHours[$label] = $stat['net_hours'];
        if ($anyHasRate && $stat['has_rate']) {
            $chartCosts[$label] = $stat['cost'];
        }
    }
}
arsort($chartHours);
arsort($chartCosts);
?>

<?php if ($chartHours): ?>
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('hours_by_employee_chart') ?></div>
    <?= repBarChart($chartHours, 'var(--color-primary)') ?>
</div>
<?php endif; ?>

<?php if ($chartCosts): ?>
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('cost_by_employee_chart') ?></div>
    <?= repBarChart($chartCosts, 'var(--color-success)') ?>
</div>
<?php endif; ?>

<div class="erep-print-footer">
    <?= htmlspecialchars(__('report_employees')) ?> — <?= htmlspecialchars($store['name'] ?? '') ?> — <?= htmlspecialchars($since) ?> / <?= htmlspecialchars($today) ?>
</div>

<!-- Modale sélection de période — fiche de paie -->
<div id="ps-period-modal" class="ps-modal-overlay" hidden>
    <div class="ps-modal">
        <div class="ps-modal__header">
            <span>🖨 <?= __('payslip') ?> — <?= __('select_period') ?></span>
            <button type="button" class="ps-modal__close" onclick="psPeriodClose()">✕</button>
        </div>
        <div class="ps-modal__body">
            <!-- Sélecteur de mois rapide -->
            <div class="ps-modal__section-label"><?= __('quick_month') ?></div>
            <div class="ps-month-grid" id="ps-month-grid">
                <?php
                for ($i = 12; $i >= 0; $i--) {
                    $dt    = new \DateTime("first day of -$i months");
                    $from  = $dt->format('Y-m-01');
                    $to    = $dt->format('Y-m-t');
                    $label = $dt->format('M Y');
                    echo '<button type="button" class="ps-month-btn" data-from="' . $from . '" data-to="' . $to . '">'
                        . htmlspecialchars($label) . '</button>';
                }
                ?>
            </div>
            <!-- Dates personnalisées -->
            <div class="ps-modal__section-label"><?= __('custom_range') ?></div>
            <div class="ps-date-row">
                <label class="ps-date-label">
                    <?= __('from_date') ?>
                    <input type="date" id="ps-from" class="ps-date-input">
                </label>
                <span class="ps-date-sep">→</span>
                <label class="ps-date-label">
                    <?= __('to_date') ?>
                    <input type="date" id="ps-to" class="ps-date-input">
                </label>
            </div>
        </div>
        <div class="ps-modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" onclick="psPeriodClose()"><?= __('cancel') ?></button>
            <button type="button" class="btn btn--primary btn--sm" onclick="psPeriodOpen()">🖨 <?= __('open') ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    let _pendingUrl = '';

    // Pré-remplir les inputs avec le mois en cours
    const now      = new Date();
    const y        = now.getFullYear();
    const m        = String(now.getMonth() + 1).padStart(2, '0');
    const lastDay  = new Date(y, now.getMonth() + 1, 0).getDate();
    document.getElementById('ps-from').value = y + '-' + m + '-01';
    document.getElementById('ps-to').value   = y + '-' + m + '-' + String(lastDay).padStart(2, '0');

    // Surligner le mois courant par défaut
    const todayFrom = y + '-' + m + '-01';
    document.querySelectorAll('.ps-month-btn').forEach(function (btn) {
        if (btn.dataset.from === todayFrom) btn.classList.add('active');
    });

    // Clic sur un mois rapide
    document.getElementById('ps-month-grid').addEventListener('click', function (e) {
        const btn = e.target.closest('.ps-month-btn');
        if (!btn) return;
        document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('ps-from').value = btn.dataset.from;
        document.getElementById('ps-to').value   = btn.dataset.to;
    });

    // Changement manuel → désélectionner les mois rapides
    ['ps-from', 'ps-to'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        });
    });

    // Ouvrir la modale au clic sur un bouton trigger
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.ps-period-trigger');
        if (!btn) return;
        _pendingUrl = btn.dataset.url || '';
        document.getElementById('ps-period-modal').removeAttribute('hidden');
    });

    window.psPeriodClose = function () {
        document.getElementById('ps-period-modal').setAttribute('hidden', '');
        _pendingUrl = '';
    };

    window.psPeriodOpen = function () {
        const from = document.getElementById('ps-from').value;
        const to   = document.getElementById('ps-to').value;
        if (!from || !to || from > to) {
            alert('<?= addslashes(__('invalid_date_range') ?? 'Plage de dates invalide') ?>');
            return;
        }
        const url = _pendingUrl.replace('__FROM__', encodeURIComponent(from)).replace('__TO__', encodeURIComponent(to));
        window.open(url, '_blank');
        psPeriodClose();
    };

    document.getElementById('ps-period-modal').addEventListener('click', function (e) {
        if (e.target === this) psPeriodClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') psPeriodClose();
    });
}());
</script>
