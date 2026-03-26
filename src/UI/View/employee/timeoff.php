<?php
/** @var array[] $requests   timeoff_requests */
/** @var string  $today      Y-m-d */

$today = date('Y-m-d');

$typeLabels = [
    'vacation' => __('vacation'), 'sick' => __('sick'), 'personal' => __('personal'),
    'unpaid' => __('unpaid'), 'other' => __('other'),
];
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('my_timeoff') ?></h2>
</div>

<!-- Flash messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert--success mb-sm">
        <?php if ($_GET['success'] === 'created'): ?><?= __('timeoff_request_sent') ?>
        <?php elseif ($_GET['success'] === 'cancelled'): ?><?= __('timeoff_cancelled') ?>
        <?php endif; ?>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert--danger mb-sm">
        <?php if ($_GET['error'] === 'not_pending'): ?><?= __('error_not_pending') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Formulaire création -->
<div class="card card--mb">
    <div class="card-header"><h3 class="card-title"><?= __('new_request') ?></h3></div>
    <div class="card-body">
        <form method="POST" action="<?= $BASE_URL ?>/employee/timeoff" class="timeoff-form">
            <div class="timeoff-form__date">
                <label class="form-label" for="to_type"><?= __('type') ?></label>
                <select id="to_type" name="type" class="form-control" required>
                    <?php foreach ($typeLabels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="timeoff-form__short">
                <label class="form-label" for="to_start"><?= __('start') ?></label>
                <input id="to_start" type="date" name="start_date" class="form-control" required value="<?= $today ?>">
            </div>
            <div class="timeoff-form__short">
                <label class="form-label" for="to_end"><?= __('end') ?></label>
                <input id="to_end" type="date" name="end_date" class="form-control" value="<?= $today ?>">
            </div>
            <div class="timeoff-form__grow">
                <label class="form-label" for="to_reason"><?= __('reason') ?> (<?= __('optional') ?? 'optionnel' ?>)</label>
                <input id="to_reason" type="text" name="reason" class="form-control" placeholder="<?= __('reason_placeholder') ?>">
            </div>
            <div>
                <button type="submit" class="btn btn--primary"><?= __('send') ?? 'Envoyer' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des demandes -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('type') ?></th>
                    <th><?= __('start') ?></th>
                    <th><?= __('end') ?></th>
                    <th><?= __('reason') ?></th>
                    <th><?= __('status') ?></th>
                    <th><?= __('sent_at') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="td-center td-muted"><?= __('no_pending_timeoff') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <?php
                        $status  = $r['status'] ?? 'pending';
                        $isPending = $status === 'pending';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($typeLabels[$r['type'] ?? ''] ?? ($r['type'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars($r['start_date'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['end_date'] ?? '—') ?></td>
                            <td class="td-muted text-italic"><?= htmlspecialchars($r['reason'] ?? '') ?></td>
                            <td>
                                <span class="badge badge--<?= htmlspecialchars($status) ?>">
                                    <?= match($status) {
                                        'pending'   => __('pending'),
                                        'approved'  => __('approved'),
                                        'refused'   => __('rejected'),
                                        'cancelled' => __('cancelled'),
                                        default     => htmlspecialchars($status),
                                    } ?>
                                </span>
                            </td>
                            <td class="text-sm-muted"><?= htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) ?></td>
                            <td>
                                <?php if ($isPending): ?>
                                    <form method="POST" action="<?= $BASE_URL ?>/employee/timeoff/<?= (int) $r['id'] ?>/cancel" class="form-inline" onsubmit="return confirm('<?= __('confirm_cancel_request') ?>')">
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
