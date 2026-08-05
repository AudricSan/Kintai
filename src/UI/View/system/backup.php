<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Table;

/**
 * @var array                                        $backups
 * @var string                                        $BASE_URL
 * @var array{type: 'success'|'danger', text: string}|null $flash
 */

$action = route_url('admin.backup');
?>
<?php if ($flash !== null): ?>
    <div class="alert alert--<?= $flash['type'] ?> mb-sm"><?= htmlspecialchars($flash['text']) ?></div>
<?php endif; ?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('backup_title') ?></h2>
</div>

<?php include __DIR__ . '/../_partials/_settings-tabs.php'; ?>

<?php
ob_start();
?>
<form method="POST" action="<?= htmlspecialchars($action) ?>/create" class="form-inline">
    <?= csrf_field() ?>
    <div class="form-group">
        <input type="text" name="note" class="form-control" placeholder="<?= __('backup_note_placeholder') ?>" maxlength="255">
    </div>
    <?= Button::make(__('backup_create_btn'))->primary()->submit()->render() ?>
</form>
<?php echo Card::make()->header(__('backup_create_card'))->body(ob_get_clean())->render(); ?>

<div class="card card--mb">
    <div class="card-header"><?= __('backup_existing') ?></div>
    <div class="card-body">
<?php if ($backups === []): ?>
    <p class="text-muted"><?= __('backup_none') ?></p>
<?php else:
    echo '<div class="mb-sm">'
        . '<form method="POST" action="' . htmlspecialchars($action) . '/delete-all" class="d-inline" data-confirm="' . htmlspecialchars(sprintf(__('backup_delete_all_confirm'), count($backups)), ENT_QUOTES) . '">'
        . csrf_field()
        . Button::make(sprintf(__('backup_delete_all_btn'), count($backups)))->danger()->sm()->submit()->render()
        . '</form></div>';
    echo Table::make()
        ->data($backups)
        ->column(__('backup_col_file'), fn($b) => '<code>' . htmlspecialchars($b['filename']) . '</code>')
        ->column(__('backup_col_date'), fn($b) => htmlspecialchars($b['created_at']))
        ->column(__('backup_col_size'), fn($b) => number_format((int)($b['size'] ?? 0) / 1024, 1) . ' ' . __('kb'))
        ->column(__('backup_col_note'), fn($b) => htmlspecialchars($b['note'] ?? ''))
        ->column(__('backup_col_actions'), function($b) use ($action) {
            return '<form method="POST" action="' . htmlspecialchars($action) . '/restore" class="d-inline">' . csrf_field()
                . '<input type="hidden" name="filename" value="' . htmlspecialchars($b['filename']) . '">'
                . Button::make(__('backup_restore_btn'))->sm()->warning()->attrs(['onclick' => "return confirm('" . __('backup_restore_confirm') . "')"])->submit()->render()
                . '</form>'
                . '<form method="POST" action="' . htmlspecialchars($action) . '/delete" class="d-inline" data-confirm="' . htmlspecialchars(__('backup_delete_confirm'), ENT_QUOTES) . '">' . csrf_field()
                . '<input type="hidden" name="filename" value="' . htmlspecialchars($b['filename']) . '">'
                . Button::make(__('backup_delete_btn'))->sm()->danger()->submit()->render()
                . '</form>';
        })
        ->render();
endif;
?>
    </div>
</div>

<div class="alert alert--warning">
    <strong><?= __('backup_warning_title') ?></strong> : <?= __('backup_warning_text') ?>
</div>

<?php
$resetAction = route_url('admin.reset.prepare');
$optionalCategories = [
    'stores'      => ['label' => __('reset_keep_stores'), 'hint' => __('reset_keep_stores_hint')],
    'shift_types' => ['label' => __('reset_keep_shift_types'), 'hint' => __('reset_keep_shift_types_hint')],
    'ui_prefs'    => ['label' => __('reset_keep_ui_prefs'), 'hint' => __('reset_keep_ui_prefs_hint')],
];
ob_start();
?>
<p class="text-muted mb-sm"><?= __('reset_intro') ?></p>
<form method="POST" action="<?= htmlspecialchars($resetAction) ?>" class="form-stack" onsubmit="return confirm('<?= __('reset_data_confirm') ?>')">
    <?= csrf_field() ?>
    <p class="form-label"><?= __('reset_keep_label') ?></p>
    <?php foreach ($optionalCategories as $key => $cat): ?>
        <div class="form-group">
            <label class="form-toggle">
                <input type="checkbox" name="keep[]" value="<?= htmlspecialchars($key) ?>" class="form-toggle__input">
                <span class="form-toggle__track"></span>
            </label>
            <span><?= htmlspecialchars($cat['label']) ?></span>
            <p class="form-hint"><?= htmlspecialchars($cat['hint']) ?></p>
        </div>
    <?php endforeach; ?>
    <div class="form-actions">
        <input type="hidden" name="mode" value="data">
        <?= Button::make(__('reset_data_btn'))->danger()->submit()->render() ?>
    </div>
</form>
<form method="POST" action="<?= htmlspecialchars($resetAction) ?>" class="mt-sm" onsubmit="return confirm('<?= __('reset_factory_confirm') ?>')">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="factory">
    <?= Button::make(__('reset_factory_btn'))->danger()->submit()->render() ?>
    <p class="form-hint"><?= __('reset_factory_hint') ?></p>
</form>
<?php echo Card::make()->header(__('reset_title'))->body(ob_get_clean())->render(); ?>
