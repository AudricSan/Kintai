<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Modal;

/**
 * @var string      $currentVersion
 * @var array|null  $updateInfo
 * @var string|null $updateCheckError
 * @var string      $updateChannel
 * @var array       $pendingMigs
 * @var int|null    $lastUpdateDurationSeconds
 * @var array       $releaseHistory
 * @var string      $repoReleasesUrl
 * @var string      $BASE_URL
 */

/** Aperçu texte brut des notes de version (markdown non interprété), tronqué pour le résumé. */
$notesSummary = trim((string) ($updateInfo['release_notes'] ?? ''));
$notesTruncated = mb_strlen($notesSummary) > 320;
if ($notesTruncated) {
    $notesSummary = mb_substr($notesSummary, 0, 320) . '…';
}

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
<?php elseif (!empty($updateCheckError)): ?>
    <div class="alert alert--warning mt-sm">
        <?= sprintf(__('backup_update_check_failed'), htmlspecialchars($updateCheckError)) ?>
    </div>
<?php else: ?>
    <p class="text-muted mt-sm"><?= __('backup_no_update_server') ?></p>
<?php endif; ?>
<?php echo Card::make()->header(__('backup_instance_version'))->body(ob_get_clean())->render(); ?>

<?php if ($notesSummary !== '' || $releaseHistory !== []): ?>
    <?php
    ob_start();
    ?>
    <?php if ($notesSummary !== ''): ?>
        <p class="update-notes-summary"><?= htmlspecialchars($notesSummary) ?></p>
    <?php else: ?>
        <p class="text-muted"><?= __('update_notes_empty') ?></p>
    <?php endif; ?>
    <p class="btn-group mt-sm">
        <?php if ($releaseHistory !== []): ?>
            <?= Button::make(__('update_notes_view_more'))->sm()->outline()->attrs(['onclick' => "openModal('update-notes-modal')"])->render() ?>
        <?php endif; ?>
        <?= Button::make(__('update_notes_github_btn'))->sm()->ghost()->link($repoReleasesUrl)->attrs(['target' => '_blank', 'rel' => 'noopener'])->render() ?>
    </p>
    <?php
    echo Card::make()->header(__('update_notes_summary_title'))->body(ob_get_clean())->render();
    ?>
<?php endif; ?>

<?php if ($releaseHistory !== []): ?>
    <?php
    ob_start();
    foreach ($releaseHistory as $release):
        $itemNotes = trim((string) $release['release_notes']);
        ?>
        <div class="update-notes-item">
            <div class="update-notes-item-header">
                <span class="update-notes-item-version">v<?= htmlspecialchars($release['version']) ?></span>
                <?php if ($release['published_at']): ?>
                    <span class="update-notes-item-date"><?= htmlspecialchars(date('d/m/Y', strtotime($release['published_at']))) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($itemNotes !== ''): ?>
                <p class="update-notes-item-body"><?= htmlspecialchars($itemNotes) ?></p>
            <?php else: ?>
                <p class="update-notes-item-body text-muted"><?= __('update_notes_empty') ?></p>
            <?php endif; ?>
            <?php if ($release['release_url']): ?>
                <a href="<?= htmlspecialchars($release['release_url']) ?>" target="_blank" rel="noopener"><?= __('backup_release_notes') ?></a>
            <?php endif; ?>
        </div>
    <?php endforeach;
    $notesModalBody = ob_get_clean();

    $notesModalFooter = Button::make(__('update_notes_github_btn'))->outline()->link($repoReleasesUrl)->attrs(['target' => '_blank', 'rel' => 'noopener'])->render();

    echo Modal::make('update-notes-modal')
        ->title(__('update_notes_modal_title'))
        ->body($notesModalBody)
        ->footer($notesModalFooter)
        ->wide()
        ->render();
    ?>
<?php endif; ?>

<script src="<?= $BASE_URL ?>/assets/js/modules/backup-update.js"></script>
