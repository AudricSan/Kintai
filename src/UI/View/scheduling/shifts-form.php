<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Flash;

/**
 * @var string $mode 'create'|'edit'
 * @var array  $shift
 * @var array  $all_users
 * @var array  $all_stores
 * @var array  $all_types
 * @var string $redirect_to
 */

$redirect_to = trim($redirect_to ?? '');
$action = $mode === 'edit'
    ? $BASE_URL . '/admin/shifts/' . (int) $shift['id'] . '/edit'
    : route_url('admin.shifts.create');
if ($redirect_to !== '') {
    $sep = str_contains($action, '?') ? '&' : '?';
    $action .= $sep . 'redirect_to=' . urlencode($redirect_to);
}

echo Flash::fromQuery('error', ['timeoff_conflict' => __('shift_timeoff_conflict')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'edit' ? __('edit_shift') : __('new_shift') ?></h2>
    <div class="page-header__actions">
        <a href="<?= route_url('admin.shifts') ?>" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<?php
ob_start();
?>
<form method="POST" action="<?= htmlspecialchars($action) ?>">
    <?= csrf_field() ?>
    <?php if ($redirect_to !== ''): ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">
    <?php endif; ?>
    <?php include __DIR__ . '/../_partials/_form-shift.php'; ?>
    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('new_shift'))->primary()->submit()->render() ?>
        <?php if ($mode === 'edit' && (!isset($store_features) || $store_features === null || in_array('open_shifts', (array) $store_features, true))): ?>
            <?php if (!(int)($shift['is_open'] ?? 0)): ?>
                <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/publish" class="form-inline">
                    <?= csrf_field() ?>
                    <?= Button::make(__('publish_to_bourse'))->warning()->submit()->render() ?>
                </form>
            <?php else: ?>
                <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/unpublish" class="form-inline">
                    <?= csrf_field() ?>
                    <?= Button::make(__('close_bourse'))->ghost()->submit()->render() ?>
                </form>
                <?= Badge::make(__('open_bourse'))->success()->render() ?>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= route_url('admin.shifts') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
        <?php if ($mode === 'edit'): ?>
            <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/delete" class="form-inline ml-auto">
                <?= csrf_field() ?>
                <?= Button::make(__('delete'))->danger()->attrs(['onclick' => "return confirm('" . __('confirm') . "')"])->submit()->render() ?>
            </form>
        <?php endif; ?>
    </div>
</form>
<?php
echo Card::make()->body(ob_get_clean())->render();
?>
