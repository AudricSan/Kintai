<?php
/** @var array $stats */
/** @var array $shifts_today */
/** @var array $pending_timeoff */
/** @var array $pending_swaps */
/** @var array $users_map  id → display_name */
$users_map ??= [];
?>

<!-- KPI Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--primary">👥</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['users'] ?></div>
            <div class="stat-card__label"><?= __('users_plural') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--success">🏬</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['stores'] ?></div>
            <div class="stat-card__label"><?= __('stores_plural') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--warning">📅</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['shifts_today'] ?></div>
            <div class="stat-card__label"><?= __('shifts_today') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--danger">⏳</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['pending_requests'] ?></div>
            <div class="stat-card__label"><?= __('pending_requests') ?></div>
        </div>
    </div>
</div>

<!-- Navigation rapide -->
<div class="quick-nav">
    <a href="<?= $BASE_URL ?>/admin/users" class="quick-nav-card">
        <span class="quick-nav-card__icon">👤</span>
        <span class="quick-nav-card__label"><?= __('manage_users') ?></span>
    </a>
    <a href="<?= $BASE_URL ?>/admin/stores" class="quick-nav-card">
        <span class="quick-nav-card__icon">🏬</span>
        <span class="quick-nav-card__label"><?= __('manage_stores') ?></span>
    </a>
    <a href="<?= $BASE_URL ?>/admin/shifts" class="quick-nav-card">
        <span class="quick-nav-card__icon">📋</span>
        <span class="quick-nav-card__label"><?= __('manage_shifts') ?></span>
    </a>
    <a href="<?= $BASE_URL ?>/admin/shift-types" class="quick-nav-card">
        <span class="quick-nav-card__icon">🏷️</span>
        <span class="quick-nav-card__label"><?= __('shift_types') ?></span>
    </a>
    <a href="<?= $BASE_URL ?>/admin/timeoff" class="quick-nav-card">
        <span class="quick-nav-card__icon">🌴</span>
        <span class="quick-nav-card__label"><?= __('timeoff_requests') ?></span>
    </a>
    <a href="<?= $BASE_URL ?>/admin/swap-requests" class="quick-nav-card">
        <span class="quick-nav-card__icon">🔄</span>
        <span class="quick-nav-card__label"><?= __('swap_requests') ?></span>
    </a>
</div>

<?php
$sort       ??= 'start_asc';
$_qs = fn(string $s): string => '?' . http_build_query(['sort' => $s]);
$_icon = function (string $field) use ($sort): string {
    [$f, $d] = explode('_', $sort, 2);
    if ($f !== $field) return '<span class="sort-icon-dim">↕</span>';
    return $d === 'asc' ? '↑' : '↓';
};
$_href = function (string $field) use ($sort, $_qs): string {
    [$f, $d] = explode('_', $sort, 2);
    $dir = ($f === $field && $d === 'asc') ? 'desc' : 'asc';
    return $_qs("{$field}_{$dir}");
};
?>
<!-- Shifts du jour -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('shifts_of_day') ?> — <?= date('d/m/Y') ?></span>
        <a href="<?= $BASE_URL ?>/admin/shifts" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($shifts_today)): ?>
        <div class="empty-state"><?= __('no_shift_today') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><a href="<?= $_href('store') ?>" class="sort-link"><?= __('store') ?> <?= $_icon('store') ?></a></th>
                        <th><a href="<?= $_href('user') ?>" class="sort-link"><?= __('user') ?> <?= $_icon('user') ?></a></th>
                        <th><a href="<?= $_href('start') ?>" class="sort-link"><?= __('start') ?> <?= $_icon('start') ?></a></th>
                        <th><a href="<?= $_href('end') ?>" class="sort-link"><?= __('end') ?> <?= $_icon('end') ?></a></th>
                        <th><a href="<?= $_href('pause') ?>" class="sort-link"><?= __('pause') ?> <?= $_icon('pause') ?></a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts_today as $shift): ?>
                        <tr>
                            <td><?= (int) $shift['id'] ?></td>
                            <td><?= htmlspecialchars((string) ($shift['store_name'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($shift['user_name'] ?? '—')) ?></td>
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

<!-- Demandes en attente -->
<div class="dash-two-col card--mt">

    <div class="card">
        <div class="card-header">
            <span><?= __('pending_timeoff') ?></span>
            <a href="<?= $BASE_URL ?>/admin/timeoff" class="card-header-link"><?= __('view_all') ?></a>
        </div>
        <?php if (empty($pending_timeoff)): ?>
            <div class="empty-state"><?= __('no_pending_request') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th><?= __('user') ?></th><th><?= __('type') ?></th><th><?= __('from') ?></th><th><?= __('to') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_timeoff as $req): ?>
                            <tr>
                                <td><?= (int) $req['id'] ?></td>
                                <td><?= htmlspecialchars($users_map[(int)($req['user_id'] ?? 0)] ?? ('User #' . (int)($req['user_id'] ?? 0))) ?></td>
                                <td><span class="badge badge--pending"><?= htmlspecialchars((string) ($req['type'] ?? '—')) ?></span></td>
                                <td><?= htmlspecialchars((string) ($req['start_date'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($req['end_date'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <span><?= __('pending_swaps') ?></span>
            <a href="<?= $BASE_URL ?>/admin/swap-requests" class="card-header-link"><?= __('view_all') ?></a>
        </div>
        <?php if (empty($pending_swaps)): ?>
            <div class="empty-state"><?= __('no_pending_swap') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th><?= __('requester') ?></th><th><?= __('target') ?></th><th><?= __('shifts') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_swaps as $swap): ?>
                            <tr>
                                <td><?= (int) $swap['id'] ?></td>
                                <td><?= htmlspecialchars($users_map[(int)($swap['requester_id'] ?? 0)] ?? ('User #' . (int)($swap['requester_id'] ?? 0))) ?></td>
                                <td><?= htmlspecialchars($users_map[(int)($swap['target_id'] ?? 0)] ?? ('User #' . (int)($swap['target_id'] ?? 0))) ?></td>
                                <td>#<?= (int) ($swap['requester_shift_id'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>
