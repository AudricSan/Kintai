<?php /** @var array $logs */ /** @var array $users_map */ ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('audit_log') ?> <span class="page-count">(<?= count($logs) ?>)</span></h2>
</div>

<?php
// Libellés lisibles pour chaque action
$actionLabels = [
    'auth.login'                  => __('audit_auth_login'),
    'auth.login_failed'           => __('audit_auth_login_failed'),
    'auth.logout'                 => __('audit_auth_logout'),
    'user.created'                => __('audit_user_created'),
    'user.updated'                => __('audit_user_updated'),
    'user.deleted'                => __('audit_user_deleted'),
    'store.created'               => __('audit_store_created'),
    'store.updated'               => __('audit_store_updated'),
    'store.member_added'          => __('audit_store_member_added'),
    'store.member_role_updated'   => __('audit_store_member_role_updated'),
    'store.member_removed'        => __('audit_store_member_removed'),
    'shift.created'               => __('audit_shift_created'),
    'shift.updated'               => __('audit_shift_updated'),
    'shift.deleted'               => __('audit_shift_deleted'),
    'shifts.imported'             => __('audit_shifts_imported'),
    'shift_type.created'          => __('audit_shift_type_created'),
    'shift_type.updated'          => __('audit_shift_type_updated'),
    'shift_type.deleted'          => __('audit_shift_type_deleted'),
    'timeoff.created'             => __('audit_timeoff_created'),
    'timeoff.cancelled'           => __('audit_timeoff_cancelled'),
    'timeoff.approved'            => __('audit_timeoff_approved'),
    'timeoff.refused'             => __('audit_timeoff_refused'),
    'swap.created'                => __('audit_swap_created'),
    'swap.peer_accepted'          => __('audit_swap_peer_accepted'),
    'swap.peer_refused'           => __('audit_swap_peer_refused'),
    'swap.cancelled'              => __('audit_swap_cancelled'),
    'swap.approved'               => __('audit_swap_approved'),
    'swap.refused'                => __('audit_swap_refused'),
];

// Couleur de badge selon la catégorie
$actionColors = [
    'auth.'        => 'primary',
    'user.'        => 'warning',
    'store.'       => 'warning',
    'shift.creat'  => 'success',
    'shift.updat'  => 'primary',
    'shift.delet'  => 'danger',
    'shifts.'      => 'success',
    'shift_type.c' => 'success',
    'shift_type.u' => 'primary',
    'shift_type.d' => 'danger',
    'timeoff.creat'=> 'primary',
    'timeoff.canc' => 'warning',
    'timeoff.appr' => 'success',
    'timeoff.refu' => 'danger',
    'swap.creat'   => 'primary',
    'swap.peer_a'  => 'success',
    'swap.peer_r'  => 'danger',
    'swap.canc'    => 'warning',
    'swap.appr'    => 'success',
    'swap.refu'    => 'danger',
];

function auditBadgeColor(string $action, array $colors): string {
    foreach ($colors as $prefix => $color) {
        if (str_starts_with($action, $prefix)) {
            return $color;
        }
    }
    return 'primary';
}
?>

<div class="card">
    <?php if (empty($logs)): ?>
        <div class="empty-state"><?= __('empty_audit_log') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('date_hour') ?></th>
                        <th><?= __('user') ?></th>
                        <th><?= __('action') ?></th>
                        <th><?= __('resource') ?></th>
                        <th><?= __('details') ?></th>
                        <th><?= __('ip') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $entry): ?>
                        <?php
                        $action   = $entry['action'] ?? '';
                        $label    = $actionLabels[$action] ?? $action;
                        $color    = auditBadgeColor($action, $actionColors);
                        $userId   = (int) ($entry['user_id'] ?? 0);
                        $userName = $userId > 0 ? ($users_map[$userId] ?? '#' . $userId) : '—';

                        // Décoder les détails JSON
                        $detailsRaw = $entry['details'] ?? null;
                        $details    = $detailsRaw ? @json_decode($detailsRaw, true) : null;
                        ?>
                        <tr>
                            <td class="td-nowrap text-sm-muted">
                                <?= htmlspecialchars($entry['created_at'] ?? '') ?>
                            </td>
                            <td class="text-sm-muted">
                                <?= htmlspecialchars($userName) ?>
                            </td>
                            <td>
                                <span class="badge badge--<?= $color ?>">
                                    <?= htmlspecialchars($label) ?>
                                </span>
                            </td>
                            <td class="text-sm-muted">
                                <?php if (!empty($entry['resource_type'])): ?>
                                    <code><?= htmlspecialchars($entry['resource_type']) ?></code>
                                    <?php if ($entry['resource_id']): ?>
                                        <span class="text-dim">#<?= (int) $entry['resource_id'] ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-hint td-detail">
                                <?php if ($details): ?>
                                    <?php foreach ($details as $k => $v): ?>
                                        <?php if ($v !== null && $v !== ''): ?>
                                            <span class="detail-tag">
                                                <strong><?= htmlspecialchars($k) ?></strong>:
                                                <?= htmlspecialchars((string) $v) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-hint td-nowrap">
                                <?= htmlspecialchars($entry['ip_address'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
