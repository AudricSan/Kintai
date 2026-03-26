<?php
/** @var array  $requests */
/** @var array  $users_map   id → display_name */
/** @var array  $stores_map  id → store_name */
/** @var string $sort */
$users_map  ??= [];
$stores_map ??= [];
$sort       ??= 'date_desc';

function toSortUrl(string $key, string $current): string {
    $next = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    return '?sort=' . $next;
}
function toSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return '';
}
function toThSort(string $label, string $key, string $current): string {
    $icon   = toSortIcon($key, $current);
    $url    = toSortUrl($key, $current);
    $active = $icon !== '' ? ' sort-link--active' : '';
    return '<th><a href="' . $url . '" class="sort-link' . $active . '">'
        . $label . '<span class="text-primary">' . $icon . '</span></a></th>';
}
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'approved' => __('approved'),
            'refused'  => __('rejected'),
            default    => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('timeoff_requests') ?> <span class="page-count">(<?= count($requests) ?>)</span></h2>
</div>

<div class="card">
    <?php if (empty($requests)): ?>
        <div class="empty-state"><?= __('no_timeoff_found') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('store') ?></th>
                        <?= toThSort(__('user'),   'user',   $sort) ?>
                        <?= toThSort(__('type'),   'type',   $sort) ?>
                        <?= toThSort(__('from'),   'date',   $sort) ?>
                        <th><?= __('to') ?></th>
                        <?= toThSort(__('status'), 'status', $sort) ?>
                        <th><?= __('reason') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                        <?php $status = $req['status'] ?? 'pending'; ?>
                        <tr>
                            <td><?= (int) $req['id'] ?></td>
                            <td><?= htmlspecialchars($stores_map[(int)($req['store_id'] ?? 0)] ?? ('ID ' . (int)($req['store_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars($users_map[(int)($req['user_id'] ?? 0)] ?? ('ID ' . (int)($req['user_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars($req['type'] ?? '') ?></td>
                            <td><?= htmlspecialchars($req['start_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($req['end_date'] ?? '') ?></td>
                            <td>
                                <span class="badge badge--<?= htmlspecialchars($status) ?>">
                                    <?= match($status) {
                                        'pending'   => __('pending'),
                                        'approved'  => __('approved'),
                                        'refused'   => __('rejected'),
                                        'cancelled' => __('cancelled'),
                                        default     => htmlspecialchars($status),
                                    } ?>
                                </span>
                            </td>
                            <td class="td-reason">
                                <?= htmlspecialchars($req['reason'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <div class="btn-group">
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/timeoff/<?= (int) $req['id'] ?>/approve" class="form-inline">
                                            <button type="submit" class="btn btn--success btn--sm"><?= __('approve') ?></button>
                                        </form>
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/timeoff/<?= (int) $req['id'] ?>/refuse" class="form-inline">
                                            <button type="submit" class="btn btn--danger btn--sm"><?= __('reject') ?></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
