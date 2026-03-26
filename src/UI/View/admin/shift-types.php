<?php
/** @var array  $shift_types */
/** @var array  $stores_map   id → name */
/** @var string $sort */
$sort       ??= 'name_asc';
$stores_map ??= [];

function stSortUrl(string $key, string $current): string {
    $next = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    return '?sort=' . $next;
}
function stSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return '';
}
function stThSort(string $label, string $key, string $current): string {
    $icon   = stSortIcon($key, $current);
    $url    = stSortUrl($key, $current);
    return '<th><a href="' . $url . '" class="sort-link' . ($icon !== '' ? ' sort-link--active' : '') . '">'
        . $label . '<span class="text-primary">' . $icon . '</span></a></th>';
}
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'created' => __('shift_type_created'),
            'updated' => __('shift_type_updated'),
            'deleted' => __('shift_type_deleted'),
            default   => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_types') ?> <span class="page-count">(<?= count($shift_types) ?>)</span></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shift-types/create" class="btn btn--primary">+ <?= __('new_type') ?></a>
    </div>
</div>

<div class="card">
    <?php if (empty($shift_types)): ?>
        <div class="empty-state"><?= __('no_shift_type_found') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?= stThSort(__('store'),  'store',  $sort) ?>
                        <?= stThSort(__('code'),   'code',   $sort) ?>
                        <?= stThSort(__('name'),   'name',   $sort) ?>
                        <th><?= __('start') ?></th>
                        <th><?= __('end') ?></th>
                        <th><?= __('color') ?></th>
                        <?= stThSort(__('status'), 'status', $sort) ?>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shift_types as $type): ?>
                        <tr>
                            <td><?= (int) $type['id'] ?></td>
                            <td class="text-sm-muted">
                                <?= htmlspecialchars($stores_map[(int)($type['store_id']??0)] ?? ('#' . (int)($type['store_id']??0))) ?>
                            </td>
                            <td><code class="code-sm"><?= htmlspecialchars($type['code'] ?? '') ?></code></td>
                            <td><strong><?= htmlspecialchars($type['name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($type['start_time'] ?? '') ?></td>
                            <td><?= htmlspecialchars($type['end_time'] ?? '') ?></td>
                            <td>
                                <span class="color-preview">
                                    <span class="color-swatch" style="background:<?= htmlspecialchars($type['color'] ?? '#ccc') ?>"></span>
                                    <code class="color-code"><?= htmlspecialchars($type['color'] ?? '') ?></code>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($type['is_active'])): ?>
                                    <span class="badge badge--active"><?= __('active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--inactive"><?= __('inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= $BASE_URL ?>/admin/shift-types/<?= (int) $type['id'] ?>/edit" class="btn btn--ghost btn--sm"><?= __('edit') ?></a>
                                    <form method="POST" action="<?= $BASE_URL ?>/admin/shift-types/<?= (int) $type['id'] ?>/delete" class="form-inline">
                                        <button type="submit" class="btn btn--danger btn--sm"
                                                onclick="return confirm('<?= __('confirm_delete_shift_type') ?>')"><?= __('delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
