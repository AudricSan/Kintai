<?php
/** @var array  $auth_user */
/** @var array  $my_shifts */
/** @var array  $my_timeoff */
/** @var array  $my_swaps */

$today    = date('Y-m-d');
$in7days  = date('Y-m-d', strtotime('+7 days'));

// Filtrer : shifts du jour
$shiftsToday = array_values(array_filter($my_shifts, fn($s) => ($s['shift_date'] ?? '') === $today));

// Shifts à venir (hors aujourd'hui, dans les 7 prochains jours)
$shiftsUpcoming = array_values(array_filter($my_shifts, fn($s) =>
    ($s['shift_date'] ?? '') > $today && ($s['shift_date'] ?? '') <= $in7days
));
usort($shiftsUpcoming, fn($a, $b) => ($a['shift_date'] ?? '') <=> ($b['shift_date'] ?? ''));

// Demandes en attente
$pendingTimeoff = array_values(array_filter($my_timeoff, fn($r) => ($r['status'] ?? '') === 'pending'));
$pendingSwaps   = array_values(array_filter($my_swaps,   fn($r) => ($r['status'] ?? '') === 'pending'));
?>

<!-- Bandeau de bienvenue -->
<div class="staff-welcome">
    <div class="staff-welcome__avatar" style="background:<?= htmlspecialchars($auth_user['color'] ?? '#6366f1') ?>">
        <?= mb_strtoupper(mb_substr($auth_user['display_name'] ?? '?', 0, 1)) ?>
    </div>
    <div>
        <h2 class="staff-welcome__name"><?= __('hello_name', ['name' => htmlspecialchars($auth_user['first_name'] ?? $auth_user['display_name'] ?? '')]) ?></h2>
        <p class="staff-welcome__date"><?= strftime('%A %d %B %Y') !== false ? date('l d F Y') : date('d/m/Y') ?> — <?= date('H:i') ?></p>
    </div>
</div>

<!-- Mini KPIs -->
<div class="stat-grid stat-grid--3col card--mb">
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--primary">📅</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($shiftsToday) ?></div>
            <div class="stat-card__label"><?= __('shifts_today') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--warning">📆</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($shiftsUpcoming) ?></div>
            <div class="stat-card__label"><?= __('upcoming_7_days') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--danger">⏳</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($pendingTimeoff) + count($pendingSwaps) ?></div>
            <div class="stat-card__label"><?= __('pending_requests') ?></div>
        </div>
    </div>
</div>

<!-- Shifts du jour -->
<div class="card card--mb">
    <div class="card-header">
        <span><?= __('my_shifts_of_day') ?> — <?= date('d/m/Y') ?></span>
    </div>
    <?php if (empty($shiftsToday)): ?>
        <div class="empty-state"><?= __('no_shift_today') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th><?= __('start') ?></th><th><?= __('end') ?></th><th><?= __('pause') ?></th><th><?= __('duration') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftsToday as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['start_time'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['end_time'] ?? '—') ?></td>
                            <td><?= (int) ($s['pause_minutes'] ?? 0) ?> min</td>
                            <td><?= (int) ($s['duration_minutes'] ?? 0) ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Prochains shifts + demandes en attente -->
<div class="dash-two-col">

    <div class="card">
        <div class="card-header"><span><?= __('upcoming_shifts_7d') ?></span></div>
        <?php if (empty($shiftsUpcoming)): ?>
            <div class="empty-state"><?= __('no_shift_week') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th><?= __('date') ?></th><th><?= __('start') ?></th><th><?= __('end') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shiftsUpcoming as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['shift_date'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['start_time'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['end_time'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><span><?= __('my_pending_requests') ?></span></div>
        <?php if (empty($pendingTimeoff) && empty($pendingSwaps)): ?>
            <div class="empty-state"><?= __('no_pending_request') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th><?= __('type') ?></th><th><?= __('details') ?></th><th><?= __('status') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTimeoff as $r): ?>
                            <tr>
                                <td><?= __('timeoff_label') ?></td>
                                <td class="text-sm-muted"><?= htmlspecialchars($r['type'] ?? '') ?> — <?= mb_strtolower(__('from')) ?> <?= htmlspecialchars($r['start_date'] ?? '') ?> <?= mb_strtolower(__('to')) ?> <?= htmlspecialchars($r['end_date'] ?? '') ?></td>
                                <td><span class="badge badge--pending"><?= __('pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($pendingSwaps as $r): ?>
                            <tr>
                                <td><?= __('swap_label') ?></td>
                                <td class="text-sm-muted">Shift #<?= (int) ($r['requester_shift_id'] ?? 0) ?></td>
                                <td><span class="badge badge--pending"><?= __('pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>
