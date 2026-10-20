<?php
/**
 * Contenu du panneau de la cloche. Partagé par la topbar (chargement de page) et par
 * NotificationController::poll() (rafraîchissement sans rechargement), pour ne pas dupliquer ce HTML en JS.
 *
 * @var array[] $notif_items   Dernières notifications (lues ou non)
 * @var int     $notif_unread  Nombre de non lues
 */
$notif_items  = $notif_items ?? [];
$notif_unread = (int) ($notif_unread ?? 0);
?>
<div class="notif-dropdown__header">
    <span><?= __('notifications') ?></span>
    <div class="notif-dropdown__header-actions">
        <?php if ($notif_unread > 0): ?>
            <form method="POST" action="<?= route_url('notifications.read_all') ?>" class="notif-mark-read-form" data-notif-ajax>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--xs"><?= __('mark_all_read') ?></button>
            </form>
        <?php endif; ?>
        <?php if (!empty($notif_items)): ?>
            <form method="POST" action="<?= route_url('notifications.delete_all') ?>" class="notif-mark-read-form" data-notif-ajax data-confirm="<?= htmlspecialchars(__('delete_all_notifications_confirm'), ENT_QUOTES) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--xs"><?= __('delete_all_notifications') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if (empty($notif_items)): ?>
    <div class="notif-dropdown__empty"><?= __('no_notifications') ?></div>
<?php else: ?>
    <?php foreach ($notif_items as $_n): ?>
        <?php $_nRead = (int) ($_n['is_read'] ?? 0); ?>
        <a href="<?= route_url('notifications.open', ['id' => (int) $_n['id']]) ?>"
           class="notif-entry<?= $_nRead ? '' : ' notif-entry--unread' ?>">
            <span class="notif-entry__icon"><?= notification_type_icon($_n['type'] ?? '') ?></span>
            <div class="notif-entry__body">
                <div class="notif-entry__title"><?= htmlspecialchars(notification_type_label($_n['type'] ?? '')) ?></div>
                <div class="notif-entry__text"><?= htmlspecialchars(notification_body($_n)) ?></div>
                <div class="notif-entry__time"><?= htmlspecialchars(substr($_n['created_at'] ?? '', 0, 16)) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
    <div class="notif-dropdown__footer">
        <a href="<?= route_url('notifications.index') ?>"><?= __('notifications') ?> →</a>
    </div>
<?php endif; ?>
