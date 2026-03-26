<?php
/** @var array  $swaps */
/** @var array  $users_map   id → display_name */
/** @var array  $stores_map  id → store_name */
/** @var string $sort */
$users_map  ??= [];
$stores_map ??= [];
$sort       ??= 'date_desc';

function swSortUrl(string $key, string $current): string {
    $next = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    return '?sort=' . $next;
}
function swSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return '';
}
function swThSort(string $label, string $key, string $current): string {
    $icon   = swSortIcon($key, $current);
    $url    = swSortUrl($key, $current);
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
    <h2 class="page-header__title"><?= __('swap_requests') ?> <span class="page-count">(<?= count($swaps) ?>)</span></h2>
</div>

<div class="card">
    <?php if (empty($swaps)): ?>
        <div class="empty-state"><?= __('no_swap_found') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('store') ?></th>
                        <?= swThSort(__('requester'), 'requester', $sort) ?>
                        <th><?= __('target') ?></th>
                        <th><?= __('requester_shift') ?></th>
                        <th><?= __('target_shift') ?></th>
                        <?= swThSort(__('status'), 'status', $sort) ?>
                        <?= swThSort(__('date'),   'date',   $sort) ?>
                        <th><?= __('reason') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($swaps as $swap): ?>
                        <?php $status = $swap['status'] ?? 'pending'; ?>
                        <tr>
                            <td><?= (int) $swap['id'] ?></td>
                            <td><?= htmlspecialchars($stores_map[(int)($swap['store_id'] ?? 0)] ?? ('ID ' . (int)($swap['store_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars($users_map[(int)($swap['requester_id'] ?? 0)] ?? ('ID ' . (int)($swap['requester_id'] ?? 0))) ?></td>
                            <td><?= htmlspecialchars($users_map[(int)($swap['target_id'] ?? 0)] ?? ('ID ' . (int)($swap['target_id'] ?? 0))) ?></td>
                            <td>#<?= (int) ($swap['requester_shift_id'] ?? 0) ?></td>
                            <td><?= isset($swap['target_shift_id']) ? '#' . (int) $swap['target_shift_id'] : '—' ?></td>
                            <td>
                                <span class="badge badge--<?= htmlspecialchars($status) ?>">
                                    <?= match($status) {
                                        'pending'   => __('pending'),
                                        'accepted'  => __('accepted'),
                                        'refused'   => __('rejected'),
                                        'cancelled' => __('cancelled'),
                                        default     => htmlspecialchars($status),
                                    } ?>
                                </span>
                            </td>
                            <td class="td-date-muted">
                                <?= htmlspecialchars(substr($swap['created_at'] ?? '', 0, 10)) ?>
                            </td>
                            <td class="td-reason">
                                <?= htmlspecialchars($swap['reason'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <div class="btn-group">
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/swap-requests/<?= (int) $swap['id'] ?>/approve" class="form-inline"
                                            <button type="submit" class="btn btn--success btn--sm"><?= __('approve') ?></button>
                                        </form>
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/swap-requests/<?= (int) $swap['id'] ?>/refuse" class="form-inline"
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
