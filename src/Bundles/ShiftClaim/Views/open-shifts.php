<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;

/**
 * Bourse aux shifts — partagée admin/manager (can_manage = true) et employé
 * (can_manage = false, ne voit que sa propre candidature).
 *
 * @var array[]    $shifts      Shifts ouverts (enrichis avec _claims en admin, _my_claim en employé)
 * @var array      $types_map   id → type
 * @var array      $stores_map  id → nom du store
 * @var array|null $users_map   id → nom (admin uniquement)
 * @var bool|null  $can_manage  null = contexte admin (toujours vrai)
 */

$_canManage = $can_manage ?? true;
$users_map  ??= [];
$types_map  ??= [];
$stores_map ??= [];

if ($_canManage) {
    Flash::fromQuery('success', [
        'published'      => __('open_shift_published'),
        'unpublished'    => __('open_shift_unpublished'),
        'claim_approved' => __('shift_claim_approved'),
        'claim_rejected' => __('shift_claim_rejected'),
    ])->render();
    echo Flash::fromQuery('error', ['default' => __('error_generic')])->render();

    $pending_claims = [];
    foreach ($shifts as $shift) {
        foreach ($shift['_claims'] ?? [] as $claim) {
            if (($claim['status'] ?? '') === 'pending') {
                $pending_claims[] = array_merge($claim, ['_shift' => $shift]);
            }
        }
    }
} else {
    echo Flash::fromQuery('success', [
        'claimed' => __('shift_claimed'), 'withdrawn' => __('shift_claim_withdrawn'),
    ])->render();
    echo Flash::fromQuery('error', [
        'already_claimed' => __('already_claimed'), 'forbidden' => __('action_impossible'),
    ])->render();
}
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('open_shifts') ?> <span class="page-count">(<?= count($shifts) ?>)</span></h2>
    <?php if ($_canManage): ?>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('publish_shift'))->primary()->link($BASE_URL . '/admin/open-shifts/publish')->render() ?>
    </div>
    <?php endif; ?>
</div>

<div class="card card--mb">
<?= Table::make()
    ->data($shifts)
    ->emptyMessage(__('no_open_shift'))
    ->column(__('date'), fn($s) => htmlspecialchars($s['shift_date'] ?? ''))
    ->column(__('schedule'), fn($s) => '<span class="td-mono">' . htmlspecialchars(substr($s['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($s['end_time'] ?? '', 0, 5)) . '</span>')
    ->column(__('store'), fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('ID ' . (int)($s['store_id'] ?? 0))))
    ->column(__('type'), function($s) use ($types_map) {
        $t = $types_map[(int)($s['shift_type_id'] ?? 0)] ?? null;
        if (!$t) return '—';
        return '<span class="type-badge" data-color="' . htmlspecialchars($t['color'] ?? '#888') . '">'
            . htmlspecialchars($t['name'] ?? '') . '</span>';
    })
    ->column(__('note'), fn($s) => htmlspecialchars($s['open_shift_note'] ?? ''))
    ->column($_canManage ? __('claims') : __('my_status'), function($s) use ($_canManage) {
        if ($_canManage) {
            $claims = $s['_claims'] ?? [];
            $nPending = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'pending'));
            if ($nPending > 0) {
                return Badge::make($nPending . ' ' . __('pending'))->warning()->render();
            }
            return '<span class="text-sm-muted">' . count($claims) . '</span>';
        }
        $status = $s['_my_claim']['status'] ?? null;
        return match ($status) {
            'pending'   => Badge::make(__('pending'))->warning()->render(),
            'approved'  => Badge::make(__('approved'))->success()->render(),
            'rejected'  => Badge::make(__('rejected'))->danger()->render(),
            'withdrawn' => Badge::make(__('withdrawn'))->neutral()->render(),
            default     => '<span class="text-sm-muted">—</span>',
        };
    })
    ->column(__('actions'), function($s) use ($BASE_URL, $_canManage) {
        $id = (int) $s['id'];
        if ($_canManage) {
            return '<div class="btn-group">'
                . '<a href="' . htmlspecialchars($BASE_URL . '/admin/shifts/' . $id . '/edit') . '" class="btn btn--ghost btn--sm">' . __('edit') . '</a>'
                . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/shifts/' . $id . '/unpublish') . '" class="form-inline">'
                . csrf_field()
                . '<button type="submit" class="btn btn--ghost btn--sm" onclick="return confirm(\'' . __('confirm') . '\')">' . __('close_bourse') . '</button>'
                . '</form></div>';
        }
        $status = $s['_my_claim']['status'] ?? null;
        if ($status === null || $status === 'withdrawn' || $status === 'rejected') {
            return '<form method="POST" action="' . $BASE_URL . '/employee/open-shifts/' . $id . '/claim" class="form-inline">' . csrf_field() . Button::make(__('claim_shift'))->primary()->sm()->submit()->render() . '</form>';
        }
        if ($status === 'pending') {
            return '<form method="POST" action="' . $BASE_URL . '/employee/open-shifts/' . $id . '/withdraw" class="form-inline" onsubmit="return confirm(\'' . __('confirm_cancel_request') . '\')">' . csrf_field() . Button::make(__('withdraw'))->ghost()->sm()->submit()->render() . '</form>';
        }
        return '<span class="text-sm-muted">—</span>';
    })
    ->render()
?></div>

<?php if ($_canManage && !empty($pending_claims)): ?>
<div class="card">
    <div class="card-header">
        <span><?= __('pending_claims') ?> <span class="page-count">(<?= count($pending_claims) ?>)</span></span>
    </div>
<?= Table::make()
    ->data($pending_claims)
    ->column(__('date'), fn($c) => htmlspecialchars($c['_shift']['shift_date'] ?? ''))
    ->column(__('schedule'), fn($c) => '<span class="td-mono">' . htmlspecialchars(substr($c['_shift']['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($c['_shift']['end_time'] ?? '', 0, 5)) . '</span>')
    ->column(__('store'), fn($c) => htmlspecialchars($stores_map[(int)($c['_shift']['store_id'] ?? 0)] ?? '—'))
    ->column(__('employee'), fn($c) => htmlspecialchars($c['_user_name'] ?? '—'))
    ->column(__('note'), fn($c) => htmlspecialchars($c['note'] ?? ''))
    ->column(__('claimed_at'), fn($c) => '<span class="td-date-muted">' . htmlspecialchars(substr($c['claimed_at'] ?? '', 0, 16)) . '</span>')
    ->column(__('actions'), function($c) use ($BASE_URL) {
        $id = (int) $c['id'];
        return '<div class="btn-group">'
            . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/open-shifts/' . $id . '/approve') . '" class="form-inline">'
            . csrf_field()
            . Button::make(__('approve'))->success()->sm()->submit()->render()
            . '</form>'
            . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/open-shifts/' . $id . '/reject') . '" class="form-inline">'
            . csrf_field()
            . Button::make(__('reject'))->danger()->sm()->submit()->render()
            . '</form></div>';
    })
    ->render()
?></div>
<?php endif; ?>

<script src="<?= $BASE_URL ?>/assets/js/modules/type-badges.js"></script>
