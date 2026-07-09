<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;
use kintai\UI\Components\Table;

/** @var array  $requests */
/** @var array  $users_map */
/** @var array  $stores_map */
/** @var string $sort */

$users_map  ??= [];
$stores_map ??= [];
$sort       ??= 'date_desc';

$statusLabels = [
    'pending'   => __('pending'),
    'approved'  => __('approved'),
    'refused'   => __('rejected'),
    'cancelled' => __('cancelled'),
];

echo Flash::fromQuery('success', [
    'approved' => __('approved'),
    'refused'  => __('rejected'),
    'created'  => __('timeoff_created_admin'),
    'deleted'  => __('timeoff_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('timeoff_requests') ?> <span class="page-count">(<?= count($requests) ?>)</span></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('new_timeoff'))->primary()->link($BASE_URL . '/admin/timeoff/create')->render() ?>
    </div>
</div>

<div class="card card--filters mb-sm">
<?= Table::make()
    ->data($requests)
    ->emptyMessage(__('no_timeoff_found'))
    ->currentSort($sort)
    ->column('#', fn($r) => (string) (int) $r['id'])
    ->column(__('store'), fn($r) => htmlspecialchars($stores_map[(int)($r['store_id'] ?? 0)] ?? ('ID ' . (int)($r['store_id'] ?? 0))))
    ->sortable(__('user'), 'user', fn($r) =>
        '<a href="' . htmlspecialchars($BASE_URL . '/admin/users/' . (int)($r['user_id'] ?? 0) . '/edit') . '">'
        . htmlspecialchars($users_map[(int)($r['user_id'] ?? 0)] ?? ('ID ' . (int)($r['user_id'] ?? 0))) . '</a>'
    )
    ->sortable(__('type'), 'type', fn($r) => htmlspecialchars($r['type'] ?? ''))
    ->sortable(__('from'), 'date', fn($r) => htmlspecialchars($r['start_date'] ?? ''))
    ->column(__('to'), fn($r) => htmlspecialchars($r['end_date'] ?? ''))
    ->sortable(__('status'), 'status', function($r) use ($statusLabels) {
        $st = $r['status'] ?? 'pending';
        return Badge::make($statusLabels[$st] ?? $st)->status($st)->render();
    })
    ->column(__('reason'), fn($r) => '<span class="td-reason">' . htmlspecialchars($r['reason'] ?? '—') . '</span>')
    ->column(__('actions'), function($r) use ($BASE_URL) {
        $status = $r['status'] ?? 'pending';
        $id = (int) $r['id'];
        $html = '<div class="btn-group">';
        if ($status === 'pending') {
            $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/approve') . '" class="form-inline">' . csrf_field()
                . Button::make(__('approve'))->success()->sm()->submit()->render() . '</form>';
            $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/refuse') . '" class="form-inline">' . csrf_field()
                . Button::make(__('reject'))->danger()->sm()->submit()->render() . '</form>';
        }
        $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/delete') . '" class="form-inline" onsubmit="return confirm(\'' . __('confirm_delete_timeoff') . '\')">' . csrf_field()
            . Button::make(__('delete'))->danger()->sm()->submit()->render() . '</form>';
        $html .= '</div>';
        return $html;
    })
    ->render()
?></div>
