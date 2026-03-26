<?php
/** @var array[]  $sent        swap_requests envoyées */
/** @var array[]  $received    swap_requests reçues */
/** @var array    $users_map   id → nom */
/** @var array    $shifts_map  id → shift */

$statusLabels = [
    'pending'   => ['label' => __('pending'),   'badge' => 'badge--warning'],
    'accepted'  => ['label' => __('accepted'),  'badge' => 'badge--active'],
    'refused'   => ['label' => __('rejected'),  'badge' => 'badge--danger'],
    'cancelled' => ['label' => __('cancelled'), 'badge' => 'badge--muted'],
];

function swapShiftLabel(array $shifts_map, int $shiftId): string {
    $s = $shifts_map[$shiftId] ?? null;
    if (!$s) return '#' . $shiftId;
    return htmlspecialchars(substr($s['start_time'] ?? '', 0, 5) . ' – ' . substr($s['end_time'] ?? '', 0, 5) . ' · ' . ($s['shift_date'] ?? ''));
}
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('swaps') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/employee/swaps/create" class="btn btn--primary btn--sm">+ <?= __('new_request') ?></a>
    </div>
</div>

<!-- Flash messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert--success mb-sm">
        <?php match ($_GET['success']) {
            'created'  => print __('swap_request_sent'),
            'accepted' => print __('swap_accepted_wait_manager'),
            'refused'  => print __('rejected'),
            'cancelled'=> print __('timeoff_cancelled'),
            default    => null,
        }; ?>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert--danger mb-sm"><?= __('action_impossible') ?? 'Action impossible dans l\'état actuel.' ?></div>
<?php endif; ?>

<!-- Demandes reçues (en attente de ma réponse) -->
<?php
$pendingReceived = array_values(array_filter(
    $received,
    fn($s) => ($s['status'] ?? '') === 'pending' && ($s['peer_accepted_at'] ?? null) === null
));
?>
<?php if (!empty($pendingReceived)): ?>
<div class="swap-card card card--mb">
    <div class="card-header swap-card__header">
        <h3 class="swap-card__title"><?= __('received_requests') ?> — <?= __('pending') ?> (<?= count($pendingReceived) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('from') ?></th>
                    <th><?= __('his_shift') ?></th>
                    <th><?= __('my_shift') ?></th>
                    <th><?= __('reason') ?></th>
                    <th><?= __('received_on') ?></th>
                    <th><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingReceived as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($users_map[(int)($s['requester_id'] ?? 0)] ?? '—') ?></td>
                        <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) ?></td>
                        <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) ?></td>
                        <td class="text-sm-muted text-italic"><?= htmlspecialchars($s['reason'] ?? '') ?></td>
                        <td class="text-sm-muted"><?= htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) ?></td>
                        <td class="swap-actions">
                            <form method="POST" action="<?= $BASE_URL ?>/employee/swaps/<?= (int)$s['id'] ?>/accept" class="form-inline">
                                <button type="submit" class="btn btn--primary btn--sm"><?= __('accept') ?></button>
                            </form>
                            <form method="POST" action="<?= $BASE_URL ?>/employee/swaps/<?= (int)$s['id'] ?>/refuse" class="form-inline" onsubmit="return confirm('<?= __('confirm') ?>?')">
                                <button type="submit" class="btn btn--danger btn--sm"><?= __('refuse') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Mes demandes envoyées -->
<div class="card card--mb">
    <div class="card-header"><h3 class="swap-card__title"><?= __('sent_requests') ?></h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('staff') ?></th>
                    <th><?= __('my_shift') ?></th>
                    <th><?= __('his_shift') ?></th>
                    <th><?= __('reason') ?></th>
                    <th><?= __('status') ?></th>
                    <th><?= __('sent_on') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sent)): ?>
                    <tr><td colspan="7" class="td-center td-muted"><?= __('no_swap_found') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($sent as $s): ?>
                        <?php
                        $status = $s['status'] ?? 'pending';
                        $isPeerPending    = $status === 'pending' && ($s['peer_accepted_at'] ?? null) === null;
                        $isManagerPending = $status === 'pending' && ($s['peer_accepted_at'] ?? null) !== null;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($users_map[(int)($s['target_id'] ?? 0)] ?? '—') ?></td>
                            <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) ?></td>
                            <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) ?></td>
                            <td class="text-sm-muted text-italic"><?= htmlspecialchars($s['reason'] ?? '') ?></td>
                            <td>
                                <?php if ($isPeerPending): ?>
                                    <span class="badge badge--warning"><?= __('wait_peer') ?></span>
                                <?php elseif ($isManagerPending): ?>
                                    <span class="badge badge--info"><?= __('wait_manager') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--<?= htmlspecialchars($status) ?>">
                                        <?= match($status) {
                                            'pending'   => __('pending'),
                                            'accepted'  => __('accepted'),
                                            'refused'   => __('rejected'),
                                            'cancelled' => __('cancelled'),
                                            default     => htmlspecialchars($status),
                                        } ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm-muted"><?= htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) ?></td>
                            <td>
                                <?php if ($isPeerPending): ?>
                                    <form method="POST" action="<?= $BASE_URL ?>/employee/swaps/<?= (int)$s['id'] ?>/cancel" class="form-inline" onsubmit="return confirm('<?= __('confirm_cancel_request') ?>')">
                                        <button type="submit" class="btn btn--ghost btn--sm"><?= __('cancel') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toutes les demandes reçues (historique) -->
<?php if (!empty($received)): ?>
<div class="card">
    <div class="card-header"><h3 class="swap-card__title"><?= __('received_requests') ?> — <?= __('history') ?></h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('from') ?></th>
                    <th><?= __('his_shift') ?></th>
                    <th><?= __('my_shift') ?></th>
                    <th><?= __('status') ?></th>
                    <th><?= __('date') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($received as $s): ?>
                    <?php
                    $status = $s['status'] ?? 'pending';
                    $isPeerPending    = $status === 'pending' && ($s['peer_accepted_at'] ?? null) === null;
                    $isManagerPending = $status === 'pending' && ($s['peer_accepted_at'] ?? null) !== null;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($users_map[(int)($s['requester_id'] ?? 0)] ?? '—') ?></td>
                        <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) ?></td>
                        <td class="text-sm"><?= swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) ?></td>
                        <td>
                            <?php if ($isPeerPending): ?>
                                <span class="badge badge--warning"><?= __('pending') ?></span>
                            <?php elseif ($isManagerPending): ?>
                                <span class="badge badge--info"><?= __('wait_manager') ?></span>
                            <?php else: ?>
                                <span class="badge badge--<?= htmlspecialchars($status) ?>">
                                    <?= match($status) {
                                        'pending'   => __('pending'),
                                        'accepted'  => __('accepted'),
                                        'refused'   => __('rejected'),
                                        'cancelled' => __('cancelled'),
                                        default     => htmlspecialchars($status),
                                    } ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm-muted"><?= htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
