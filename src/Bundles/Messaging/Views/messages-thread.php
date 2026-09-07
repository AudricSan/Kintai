<?php
/** @var array    $thread        Le fil de discussion */
/** @var array[]  $messages      Messages triés chronologiquement */
/** @var array[]  $participants  Participants du fil */
/** @var array    $users_map     id → nom */
/** @var array    $stores_map    id → nom du store */
/** @var int      $user_id       ID de l'utilisateur connecté */
/** @var string   $base_path     '/admin/messages' ou '/employee/messages' */
/** @var bool     $can_manage    true en contexte admin/manager (lien vers la fiche utilisateur) */

$participantNames = array_map(
    fn($p) => htmlspecialchars($users_map[(int) $p['user_id']] ?? '?'),
    $participants
);
$_canManage = $can_manage ?? true;
?>

<?= \kintai\UI\Components\Flash::fromQuery('success', ['sent' => __('message_sent'), 'replied' => __('message_replied')])->render() ?>

<div class="page-header">
    <h2 class="page-header__title"><?= htmlspecialchars($thread['subject'] ?? '') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL . $base_path ?>" class="btn btn--ghost">← <?= __('back') ?></a>
        <form method="POST" action="<?= $BASE_URL . $base_path ?>/<?= (int) $thread['id'] ?>/delete"
              data-confirm="<?= htmlspecialchars(__('confirm_delete_thread'), ENT_QUOTES) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--danger btn--sm"><?= __('delete_thread') ?></button>
        </form>
    </div>
</div>

<div class="text-sm-muted mb-sm">
    <?= __('store') ?>: <strong><?= htmlspecialchars($stores_map[(int) ($thread['store_id'] ?? 0)] ?? '—') ?></strong>
    &nbsp;·&nbsp;
    <?= __('participants') ?>: <?= implode(', ', $participantNames) ?>
</div>

<div id="msg-meta"
     data-thread-id="<?= (int) $thread['id'] ?>"
     data-user-id="<?= (int) $user_id ?>"
     data-base-url="<?= htmlspecialchars($BASE_URL) ?>"
     data-reply-url="<?= htmlspecialchars($BASE_URL . $base_path) ?>/<?= (int) $thread['id'] ?>"
     hidden></div>

<!-- Messages -->
<div class="msg-thread" id="msg-thread">
    <?php foreach ($messages as $msg): ?>
        <?php $isMine = (int) $msg['sender_id'] === $user_id; ?>
        <div class="msg-bubble <?= $isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
            <div class="msg-bubble__meta">
                <span class="msg-bubble__author"><?php if ($_canManage): ?><a href="<?= $BASE_URL ?>/admin/users/<?= (int) $msg['sender_id'] ?>/edit"><?= htmlspecialchars($users_map[(int) $msg['sender_id']] ?? '?') ?></a><?php else: ?><?= htmlspecialchars($users_map[(int) $msg['sender_id']] ?? '?') ?><?php endif; ?></span>
                <span class="msg-bubble__time"><?= htmlspecialchars(substr($msg['sent_at'] ?? '', 0, 16)) ?></span>
                <?php if ($isMine): ?>
                    <form method="POST"
                          action="<?= $BASE_URL . $base_path ?>/<?= (int) $thread['id'] ?>/message/<?= (int) $msg['id'] ?>/delete"
                          class="msg-delete-form"
                          data-confirm="<?= htmlspecialchars(__('confirm_delete_message'), ENT_QUOTES) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-msg-delete" title="<?= __('delete_message') ?>">✕</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="msg-bubble__body"><?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Réponse -->
<div class="card mt-sm">
    <div class="card-body">
        <form id="reply-form" method="POST" action="<?= $BASE_URL . $base_path ?>/<?= (int) $thread['id'] ?>">
            <?= csrf_field() ?>
            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('reply') ?></label>
                    <textarea name="body" class="form-control" rows="4" required placeholder="<?= __('message_reply_placeholder') ?>"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= __('send') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?= $BASE_URL ?>/assets/js/modules/message-stream.js"></script>
