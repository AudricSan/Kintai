<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Table;

/**
 * @var array       $backups
 * @var string      $BASE_URL
 */

$action = route_url('admin.backup');
?>
<?php $flashVal = $flash ?? ''; if ($flashVal !== ''): ?>
    <div class="alert alert--info mb-sm"><?= htmlspecialchars(urldecode($flashVal)) ?></div>
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
        . '<form method="POST" action="' . htmlspecialchars($action) . '/delete-all" class="d-inline" onsubmit="return confirm(\'' . sprintf(__('backup_delete_all_confirm'), count($backups)) . '\')">'
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
                . '<form method="POST" action="' . htmlspecialchars($action) . '/delete" class="d-inline">' . csrf_field()
                . '<input type="hidden" name="filename" value="' . htmlspecialchars($b['filename']) . '">'
                . Button::make(__('backup_delete_btn'))->sm()->danger()->attrs(['onclick' => "return confirm('" . __('backup_delete_confirm') . "')"])->submit()->render()
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
