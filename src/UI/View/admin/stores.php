<?php
/** @var array  $stores */
/** @var string $sort */
$sort ??= 'name_asc';
$isOwner = !empty($auth_user['is_admin']);

function storeSortUrl(string $key, string $current): string {
    $next = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    return '?sort=' . $next;
}
function storeSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return '';
}
function storeThSort(string $label, string $key, string $current): string {
    $icon   = storeSortIcon($key, $current);
    $url    = storeSortUrl($key, $current);
    return '<th><a href="' . $url . '" class="sort-link' . ($icon !== '' ? ' sort-link--active' : '') . '">'
        . $label . '<span class="text-primary">' . $icon . '</span></a></th>';
}
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'created' => __('store_created'),
            'updated' => __('store_updated'),
            'deleted' => __('store_deleted'),
            default   => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('stores_plural') ?> <span class="page-count">(<?= count($stores) ?>)</span></h2>
    <div class="page-header__actions">
        <?php if ($isOwner): ?>
            <a href="<?= $BASE_URL ?>/admin/stores/create" class="btn btn--primary">+ <?= __('new_store') ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (empty($stores)): ?>
        <div class="empty-state"><?= __('no_store_found') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?= storeThSort(__('code'),     'code',   $sort) ?>
                        <?= storeThSort(__('name'),     'name',   $sort) ?>
                        <?= storeThSort(__('type'),     'type',   $sort) ?>
                        <th><?= __('timezone') ?></th>
                        <th><?= __('currency') ?></th>
                        <?= storeThSort(__('status'),   'status', $sort) ?>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td><?= (int) $store['id'] ?></td>
                            <td><code class="code-sm"><?= htmlspecialchars($store['code'] ?? '') ?></code></td>
                            <td><strong><?= htmlspecialchars($store['name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($store['type'] ?? '') ?></td>
                            <td class="text-sm-muted"><?= htmlspecialchars($store['timezone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($store['currency'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($store['is_active']) && empty($store['deleted_at'])): ?>
                                    <span class="badge badge--active"><?= __('active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--inactive"><?= __('inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/stats" class="btn btn--ghost btn--sm" title="<?= __('statistics') ?>">📊</a>
                                    <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/employee-report" class="btn btn--ghost btn--sm" title="<?= __('employee_report') ?>">👥</a>
                                    <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/edit" class="btn btn--ghost btn--sm"><?= __('edit') ?></a>
                                    <?php if ($isOwner): ?>
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/delete" class="form-inline"
                                              onsubmit="return confirm('<?= __('confirm_delete_store') ?>')">
                                            <button type="submit" class="btn btn--danger btn--sm"><?= __('delete') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
