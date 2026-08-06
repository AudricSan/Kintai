<?php
/** @var array|null $timeoff */
/** @var array|null $swaps */
/** @var array|null $claims */
/** @var array $users_map */
/** @var array $stores_map */

$_total = (int) ($timeoff !== null ? count($timeoff) : 0)
        + (int) ($swaps   !== null ? count($swaps)   : 0)
        + (int) ($claims  !== null ? count($claims)  : 0);
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('requests') ?> <span class="page-count">(<?= $_total ?>)</span></h2>
</div>

<?php if ($timeoff === null && $swaps === null && $claims === null): ?>
    <div class="card card--mt">
        <div class="empty-state"><?= __('no_data') ?></div>
    </div>
<?php endif; ?>

<?php if ($timeoff !== null): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('pending_timeoff') ?></span>
        <a href="<?= route_url('admin.timeoff') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if ($timeoff === []): ?>
        <div class="empty-state"><?= __('no_pending_request') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr><th>#</th><th><?= __('user') ?></th><th><?= __('type') ?></th><th><?= __('from') ?></th><th><?= __('to') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeoff as $req): ?>
                        <tr class="tr--clickable" onclick="location.href='<?= route_url('admin.timeoff') ?>'">
                            <td data-label="#"><?= (int) $req['id'] ?></td>
                            <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars($users_map[(int) ($req['user_id'] ?? 0)] ?? ('User #' . (int) ($req['user_id'] ?? 0))) ?></td>
                            <td data-label="<?= htmlspecialchars(__('type')) ?>"><span class="badge badge--pending"><?= htmlspecialchars((string) ($req['type'] ?? '—')) ?></span></td>
                            <td data-label="<?= htmlspecialchars(__('from')) ?>"><?= htmlspecialchars((string) ($req['start_date'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('to')) ?>"><?= htmlspecialchars((string) ($req['end_date'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($swaps !== null): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('pending_swaps') ?></span>
        <a href="<?= route_url('admin.swap_requests') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if ($swaps === []): ?>
        <div class="empty-state"><?= __('no_pending_swap') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr><th>#</th><th><?= __('requester') ?></th><th><?= __('target') ?></th><th><?= __('shifts') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($swaps as $swap): ?>
                        <tr class="tr--clickable" onclick="location.href='<?= route_url('admin.swap_requests') ?>'">
                            <td data-label="#"><?= (int) $swap['id'] ?></td>
                            <td data-label="<?= htmlspecialchars(__('requester')) ?>"><?= htmlspecialchars($users_map[(int) ($swap['requester_id'] ?? 0)] ?? ('User #' . (int) ($swap['requester_id'] ?? 0))) ?></td>
                            <td data-label="<?= htmlspecialchars(__('target')) ?>"><?= htmlspecialchars($users_map[(int) ($swap['target_user_id'] ?? 0)] ?? ('User #' . (int) ($swap['target_user_id'] ?? 0))) ?></td>
                            <td data-label="<?= htmlspecialchars(__('shifts')) ?>">#<?= (int) ($swap['requester_shift_id'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($claims !== null): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('pending_claims') ?></span>
        <a href="<?= route_url('admin.open_shifts') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if ($claims === []): ?>
        <div class="empty-state"><?= __('no_pending_claims') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr><th><?= __('applicant') ?></th><th><?= __('store') ?></th><th><?= __('date') ?></th><th><?= __('start') ?></th><th><?= __('end') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($claims as $claim): ?>
                        <tr class="tr--clickable" onclick="location.href='<?= route_url('admin.open_shifts') ?>'">
                            <td data-label="<?= htmlspecialchars(__('applicant')) ?>"><?= htmlspecialchars($users_map[(int) ($claim['user_id'] ?? 0)] ?? ('User #' . (int) ($claim['user_id'] ?? 0))) ?></td>
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars($stores_map[(int) ($claim['store_id'] ?? 0)] ?? ('Store #' . (int) ($claim['store_id'] ?? 0))) ?></td>
                            <td data-label="<?= htmlspecialchars(__('date')) ?>"><?= htmlspecialchars((string) ($claim['shift_date'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('start')) ?>"><?= htmlspecialchars(substr((string) ($claim['start_time'] ?? ''), 0, 5) ?: '—') ?></td>
                            <td data-label="<?= htmlspecialchars(__('end')) ?>"><?= htmlspecialchars(substr((string) ($claim['end_time'] ?? ''), 0, 5) ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php unset($_total); ?>
