<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * @var string      $currentVersion
 * @var array|null  $updateInfo
 * @var string      $updateChannel
 * @var array       $pendingMigs
 * @var int|null    $lastUpdateDurationSeconds
 * @var string      $BASE_URL
 */

$action = route_url('admin.update');
$channelAction = route_url('admin.update.channel');
$channels = ['release' => __('update_channel_release'), 'beta' => __('update_channel_beta'), 'alpha' => __('update_channel_alpha')];
?>
<?php $flashVal = $flash ?? ''; if ($flashVal !== ''): ?>
    <div class="alert alert--info mb-sm"><?= htmlspecialchars(urldecode($flashVal)) ?></div>
<?php endif; ?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('update_title') ?></h2>
</div>

<?php include __DIR__ . '/../_partials/_settings-tabs.php'; ?>

<?php
ob_start();
?>
<p class="mb-sm">
    <?= __('update_channel_label') ?>
    <span class="btn-group">
        <?php foreach ($channels as $value => $label): ?>
            <?php $needsConfirm = $value !== 'release' && $value !== $updateChannel; ?>
            <form method="POST" action="<?= htmlspecialchars($channelAction) ?>" class="d-inline"<?= $needsConfirm ? " onsubmit=\"return confirm('" . __('update_channel_switch_confirm') . "')\"" : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="<?= htmlspecialchars($value) ?>">
                <?= Button::make($label)->sm()->{$value === $updateChannel ? 'primary' : 'outline'}()->submit()->disabled($value === $updateChannel)->render() ?>
            </form>
        <?php endforeach; ?>
    </span>
</p>
<p>
    <?= __('backup_current_version') ?> <strong><?= htmlspecialchars($currentVersion) ?></strong>
    <?php if ($pendingMigs !== []): ?>
        <?= Badge::make(sprintf(__('backup_pending_migrations'), count($pendingMigs)))->warning()->render() ?>
        <form method="POST" action="<?= htmlspecialchars($action) ?>/migrate" class="d-inline">
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
            <form id="backup-update-form" method="POST" action="<?= htmlspecialchars($action) ?>/apply" class="mt-sm" data-stream-url="<?= htmlspecialchars($action) ?>/stream" onsubmit="return confirm('<?= __('backup_update_confirm') ?>')">
                <?= csrf_field() ?>
                <?= Button::make(__('backup_update_now_btn'))->danger()->submit()->render() ?>
            </form>
            <div id="backup-update-progress" class="hidden mt-sm" data-summary-template="<?= htmlspecialchars(__('backup_update_done_summary')) ?>">
                <div class="progress-bar"><div class="progress-bar__fill" id="backup-update-fill"></div></div>
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

<script src="<?= $BASE_URL ?>/assets/js/modules/backup-update.js"></script>
