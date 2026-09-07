<?php
/** @var array[] $notifications */

$typeLabels = [
    'message_received'      => __('notif_message_received'),
    'timeoff_approved'      => __('notif_timeoff_approved'),
    'timeoff_refused'       => __('notif_timeoff_refused'),
    'swap_accepted'         => __('notif_swap_accepted'),
    'swap_refused'          => __('notif_swap_refused'),
    'shift_assigned'        => __('notif_shift_assigned'),
    'open_shift_published'  => __('notif_open_shift_published'),
    'shift_claim_submitted' => __('notif_shift_claim_submitted'),
    'shift_claim_approved'  => __('notif_shift_claim_approved'),
    'shift_claim_rejected'  => __('notif_shift_claim_rejected'),
    'shift_claim_withdrawn' => __('notif_shift_claim_withdrawn'),
    'daily_report_submitted' => __('notif_daily_report_submitted'),
    'daily_report_validated' => __('notif_daily_report_validated'),
    'feedback_submitted'    => __('notif_feedback_submitted'),
    'swap_requested'        => __('notif_swap_requested'),
    'swap_peer_accepted'    => __('notif_swap_peer_accepted'),
    'swap_peer_refused'     => __('notif_swap_peer_refused'),
    'swap_cancelled'        => __('notif_swap_cancelled'),
];

$typeIcons = [
    'message_received'      => '✉',
    'timeoff_approved'      => '✓',
    'timeoff_refused'       => '✗',
    'swap_accepted'         => '⇄',
    'swap_refused'          => '⇄',
    'shift_assigned'        => '📅',
    'open_shift_published'  => '📢',
    'shift_claim_submitted' => '🙋',
    'shift_claim_approved'  => '✓',
    'shift_claim_rejected'  => '✗',
    'shift_claim_withdrawn' => '⊘',
    'daily_report_submitted' => '📝',
    'daily_report_validated' => '✓',
    'feedback_submitted'    => '💬',
    'swap_requested'        => '⇄',
    'swap_peer_accepted'    => '⇄',
    'swap_peer_refused'     => '⇄',
    'swap_cancelled'        => '⇄',
];
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('notifications') ?></h2>
    <?php if (!empty($notifications)): ?>
    <div class="page-header__actions">
        <form method="POST" action="<?= route_url('notifications.read_all') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm"><?= __('mark_all_read') ?></button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="empty-state">
        <p><?= __('no_notifications') ?></p>
    </div>
<?php else: ?>
    <div class="notif-list">
        <?php foreach ($notifications as $n): ?>
            <?php $isRead = (int) ($n['is_read'] ?? 0); ?>
            <div class="notif-item<?= $isRead ? '' : ' notif-item--unread' ?>">
                <span class="notif-item__icon"><?= $typeIcons[$n['type'] ?? ''] ?? '•' ?></span>
                <div class="notif-item__content">
                    <div class="notif-item__title"><?= htmlspecialchars($typeLabels[$n['type'] ?? ''] ?? ($n['type'] ?? '')) ?></div>
                    <div class="notif-item__body"><?= htmlspecialchars($n['body'] ?? '') ?></div>
                    <div class="notif-item__time"><?= htmlspecialchars(substr($n['created_at'] ?? '', 0, 16)) ?></div>
                </div>
                <?php if (!$isRead): ?>
                <form method="POST" action="<?= $BASE_URL ?>/notifications/<?= (int) $n['id'] ?>/read" class="notif-item__action">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--xs" title="Marquer comme lu">✓</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
