<?php
/** @var array $user */
/** @var array $shifts_today */
/** @var array $upcoming */
/** @var array $pending_timeoff */
/** @var array $my_swaps */
/** @var array $stores_map  id → store name */
/** @var array|null $employee_month_stats  partagé par EmployeeController::shareMonthStats() */
$stats      = $employee_month_stats ?? null;
$stores_map = $stores_map ?? [];
?>

<!-- Bienvenue -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--primary">📅</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($shifts_today) ?></div>
            <div class="stat-card__label"><?= __('shifts_today') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--success">📋</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($upcoming) ?></div>
            <div class="stat-card__label"><?= __('shifts_upcoming') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--warning">🌴</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($pending_timeoff) ?></div>
            <div class="stat-card__label"><?= __('pending_timeoff') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--danger">🔄</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($my_swaps) ?></div>
            <div class="stat-card__label"><?= __('swaps') ?></div>
        </div>
    </div>
</div>

<!-- Salaire du mois -->
<?php if ($stats !== null): ?>
<?php $cur = $stats['currency'] ?? 'JPY'; ?>
<div class="card card--mt">

    <!-- En-tête : navigation mois -->
    <div class="card-header dash-header">
        <button onclick="dashMonthNav('<?= htmlspecialchars($stats['prev_month']) ?>')"
                class="btn btn--ghost btn--sm dash-month-btn">←</button>
        <strong class="dash-month-label"><?= htmlspecialchars($stats['month_label']) ?></strong>
        <button onclick="dashMonthNav('<?= htmlspecialchars($stats['next_month']) ?>')"
                class="btn btn--ghost btn--sm dash-month-btn <?= $stats['is_current'] ? 'dash-month-btn--disabled' : '' ?>"
                <?= $stats['is_current'] ? 'disabled' : '' ?>>→</button>
        <?php if (!$stats['is_current']): ?>
            <button onclick="dashMonthNav('<?= date('Y-m') ?>')"
                    class="btn btn--ghost btn--sm dash-detail-btn"><?= __('today') ?></button>
        <?php endif; ?>
    </div>

    <!-- Chiffres clés -->
    <div class="card-body dash-stats-body">
        <div class="dash-stat">
            <div class="dash-stat__value"><?= number_format($stats['hours_month'], 1) ?> h</div>
            <div class="dash-stat__label"><?= __('hours_this_month') ?></div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat__value dash-stat__value--neutral"><?= number_format($stats['hours_week'], 1) ?> h</div>
            <div class="dash-stat__label"><?= __('avg_per_week') ?></div>
        </div>
        <div class="dash-stat dash-stat--wide">
            <?php if ($stats['has_rate']): ?>
                <div class="dash-stat__value dash-stat__value--success"><?= format_currency((float) $stats['estimated_pay'], $cur) ?></div>
            <?php else: ?>
                <div class="dash-stat__value--muted">—</div>
            <?php endif; ?>
            <div class="dash-stat__label"><?= __('estimated_pay') ?></div>
        </div>
        <!-- Bouton toggle détail -->
        <?php if (!empty($stats['shift_details'])): ?>
        <div class="dash-stat-actions">
            <button id="dash-detail-toggle" onclick="dashDetailToggle()"
                    class="btn btn--ghost btn--sm"><?= __('details') ?> ▼</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Table de détail (masquée par défaut) -->
    <?php if (!empty($stats['shift_details'])): ?>
    <div id="dash-detail" class="dash-detail-wrap">
        <div class="table-wrap">
            <table class="data-table dash-detail-table">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('schedule') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('net') ?></th>
                        <?php if ($stats['has_rate']): ?>
                        <th><?= __('rate_h') ?></th>
                        <th class="td-right"><?= __('estimated_pay') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalNet = 0; $totalPay = 0.0; ?>
                    <?php foreach ($stats['shift_details'] as $row): ?>
                    <?php $totalNet += $row['net_min'] ?? 0; $totalPay += $row['has_rate'] ? $row['rate'] * (($row['net_min'] ?? 0) / 60) : 0; ?>
                    <tr>
                        <td class="td-nowrap"><?= htmlspecialchars($row['date_label']) ?></td>
                        <td class="td-nowrap font-mono"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
                        <td><?= htmlspecialchars($row['type_name']) ?></td>
                        <td class="td-nowrap">
                            <?= htmlspecialchars($row['net_hours_fmt']) ?>
                            <?php if (($row['pause_min'] ?? 0) > 0): ?>
                                <small class="text-muted">(<?= __('pause') ?> <?= (int)$row['pause_min'] ?> min)</small>
                            <?php endif; ?>
                        </td>
                        <?php if ($stats['has_rate']): ?>
                        <td class="td-muted"><?= htmlspecialchars($row['rate_fmt']) ?></td>
                        <td class="<?= $row['has_rate'] ? 'pay-ok' : 'pay-none' ?>">
                            <?= htmlspecialchars($row['pay_fmt']) ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- Ligne totaux -->
                <tfoot>
                    <tr class="tr-total">
                        <td colspan="3" class="td-muted"><?= __('total') ?></td>
                        <td class="td-nowrap">
                            <?php $th = intdiv($totalNet, 60); $tm = $totalNet % 60; ?>
                            <?= $th ?>h<?= str_pad($tm, 2, '0', STR_PAD_LEFT) ?>
                        </td>
                        <?php if ($stats['has_rate']): ?>
                        <td></td>
                        <td class="pay-ok"><?= format_currency((float)$stats['estimated_pay'], $cur) ?></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function dashMonthNav(month) {
    var url = new URL(window.location.href);
    url.searchParams.set('month', month);
    window.location.href = url.toString();
}
var _dashDetailOpen = false;
function dashDetailToggle() {
    _dashDetailOpen = !_dashDetailOpen;
    var el = document.getElementById('dash-detail');
    el.classList.toggle('open', _dashDetailOpen);
    document.getElementById('dash-detail-toggle').textContent = _dashDetailOpen ? '<?= __('details') ?> ▲' : '<?= __('details') ?> ▼';
}
</script>
<?php endif; ?>

<!-- Shifts du jour -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('my_shifts_today') ?> — <?= date('d/m/Y') ?></span>
    </div>
    <?php if (empty($shifts_today)): ?>
        <div class="empty-state"><?= __('no_shift_today') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('store') ?></th>
                        <th><?= __('start') ?></th>
                        <th><?= __('end') ?></th>
                        <th><?= __('pause') ?></th>
                        <th><?= __('notes') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts_today as $shift): ?>
                        <tr>
                            <td><?= htmlspecialchars($stores_map[(int)($shift['store_id'] ?? 0)] ?? ('Store #' . (int)($shift['store_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars((string) ($shift['start_time'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($shift['end_time'] ?? '—')) ?></td>
                            <td><?= (int) ($shift['pause_minutes'] ?? 0) ?> min</td>
                            <td><?= htmlspecialchars((string) ($shift['notes'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Prochains shifts -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('my_upcoming_shifts') ?></span>
    </div>
    <?php if (empty($upcoming)): ?>
        <div class="empty-state"><?= __('no_upcoming_shift') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('store') ?></th>
                        <th><?= __('start') ?></th>
                        <th><?= __('end') ?></th>
                        <th><?= __('pause') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $shift): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($shift['shift_date'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars($stores_map[(int)($shift['store_id'] ?? 0)] ?? ('Store #' . (int)($shift['store_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars((string) ($shift['start_time'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($shift['end_time'] ?? '—')) ?></td>
                            <td><?= (int) ($shift['pause_minutes'] ?? 0) ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Congés en attente -->
<?php if (!empty($pending_timeoff)): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('my_pending_timeoff') ?></span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('type') ?></th>
                    <th><?= __('from') ?></th>
                    <th><?= __('to') ?></th>
                    <th><?= __('status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_timeoff as $req): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($req['type'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($req['start_date'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($req['end_date'] ?? '—')) ?></td>
                        <td><span class="badge badge--pending"><?= __('pending') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
