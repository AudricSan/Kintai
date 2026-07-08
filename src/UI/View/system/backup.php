<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Table;

/**
 * @var array       $backups
 * @var string      $currentVersion
 * @var array|null  $updateInfo
 * @var array       $pendingMigs
 * @var int|null    $lastUpdateDurationSeconds
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
<p>
    <?= __('backup_current_version') ?> <strong><?= htmlspecialchars($currentVersion) ?></strong>
    <?php if ($pendingMigs !== []): ?>
        <?= Badge::make(sprintf(__('backup_pending_migrations'), count($pendingMigs)))->warning()->render() ?>
        <form method="POST" action="<?= htmlspecialchars($action) ?>/migrate" style="display:inline">
            <?= csrf_field() ?>
            <?= Button::make(__('backup_run_migrations'))->sm()->primary()->submit()->render() ?>
        </form>
    <?php endif; ?>
</p>
<?php if ($updateInfo !== null): ?>
    <?php if ($updateInfo['has_update']): ?>
        <div class="alert alert--info mt-sm">
            <strong><?= __('backup_update_available') ?></strong> :
            <?= htmlspecialchars($updateInfo['current_version']) ?> →
            <strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong>
            <?php if ($updateInfo['release_url']): ?>
                <br><a href="<?= htmlspecialchars($updateInfo['release_url']) ?>" target="_blank" rel="noopener"><?= __('backup_release_notes') ?></a>
            <?php endif; ?>
            <p class="text-muted text-sm mt-sm">
                <?php if ($lastUpdateDurationSeconds !== null): ?>
                    <?= sprintf(__('backup_update_last_duration'), $lastUpdateDurationSeconds) ?>
                <?php else: ?>
                    <?= __('backup_update_no_estimate') ?>
                <?php endif; ?>
            </p>
            <form id="backup-update-form" method="POST" action="<?= htmlspecialchars($action) ?>/update" class="mt-sm" data-stream-url="<?= htmlspecialchars($action) ?>/update/stream" onsubmit="return confirm('<?= __('backup_update_confirm') ?>')">
                <?= csrf_field() ?>
                <?= Button::make(__('backup_update_now_btn'))->danger()->submit()->render() ?>
            </form>
            <div id="backup-update-progress" class="mt-sm" style="display:none" data-summary-template="<?= htmlspecialchars(__('backup_update_done_summary')) ?>">
                <div class="progress-bar"><div class="progress-bar__fill" id="backup-update-fill" style="width:0%"></div></div>
                <p class="text-sm mt-xs" id="backup-update-label"></p>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert--success mt-sm"><?= sprintf(__('backup_up_to_date'), htmlspecialchars($currentVersion)) ?></div>
    <?php endif; ?>
<?php else: ?>
    <p class="text-muted mt-sm"><?= __('backup_no_update_server') ?></p>
<?php endif; ?>
<?php echo Card::make()->header(__('backup_instance_version'))->body(ob_get_clean())->render(); ?>

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
        . '<form method="POST" action="' . htmlspecialchars($action) . '/delete-all" style="display:inline" onsubmit="return confirm(\'' . sprintf(__('backup_delete_all_confirm'), count($backups)) . '\')">'
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
            return '<form method="POST" action="' . htmlspecialchars($action) . '/restore" style="display:inline">' . csrf_field()
                . '<input type="hidden" name="filename" value="' . htmlspecialchars($b['filename']) . '">'
                . Button::make(__('backup_restore_btn'))->sm()->warning()->attrs(['onclick' => "return confirm('" . __('backup_restore_confirm') . "')"])->submit()->render()
                . '</form>'
                . '<form method="POST" action="' . htmlspecialchars($action) . '/delete" style="display:inline">' . csrf_field()
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

<script src="<?= $BASE_URL ?>/assets/js/modules/backup-update.js"></script>
