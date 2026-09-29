<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Table;

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

<?= \kintai\UI\Components\Flash::fromQuery('success', [
    'created' => __('timeoff_request_sent'), 'cancelled' => __('timeoff_cancelled'),
])->render() ?>
<?= \kintai\UI\Components\Flash::fromQuery('error', ['not_pending' => __('error_not_pending')])->render() ?>

<!-- Formulaire création -->
<?php
ob_start();
?>
<form method="POST" action="<?= route_url('employee.timeoff') ?>" class="timeoff-form">
    <?= csrf_field() ?>
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
        <?= Button::make(__('send') ?? 'Envoyer')->primary()->submit()->render() ?>
    </div>
</form>
<?php echo Card::make(__('new_request'))->body(ob_get_clean())->render(); ?>

<!-- Liste des demandes -->
<div class="card">
    <?php if (empty($requests)):
        echo '<p class="empty-state">' . __('no_pending_timeoff') . '</p>';
    else: ?>
    <?= Table::make()
        ->data($requests)
        ->column(__('type'), fn($r) => htmlspecialchars(($typeLabels[$r['type'] ?? ''] ?? ($r['type'] ?? '—'))))
        ->column(__('start'), fn($r) => htmlspecialchars($r['start_date'] ?? '—'))
        ->column(__('end'), fn($r) => htmlspecialchars($r['end_date'] ?? '—'))
        ->column(__('reason'), fn($r) => '<span class="td-muted text-italic">' . htmlspecialchars($r['reason'] ?? '') . '</span>')
        ->column(__('status'), function($r) {
            $status = $r['status'] ?? 'pending';
            return Badge::make(match($status) {
                'pending'   => __('pending'),
                'approved'  => __('approved'),
                'refused'   => __('rejected'),
                'cancelled' => __('cancelled'),
                default     => htmlspecialchars($status),
            })->status($status)->render();
        })
        ->column(__('sent_at'), fn($r) => '<span class="text-sm-muted">' . htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) . '</span>')
        ->column('', function($r) use ($BASE_URL) {
            $status = $r['status'] ?? 'pending';
            if ($status !== 'pending') return '';
            return '<form method="POST" action="' . $BASE_URL . '/employee/timeoff/' . (int) $r['id'] . '/cancel" class="form-inline" onsubmit="return confirm(\'' . __('confirm_cancel_request') . '\')">'
                . csrf_field()
                . Button::make(__('cancel'))->ghost()->sm()->submit()->render()
                . '</form>';
        })
        ->render() ?>
    <?php endif; ?>
</div>
