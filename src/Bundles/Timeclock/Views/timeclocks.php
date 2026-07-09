<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Badge;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;
use kintai\UI\Components\FilterBar;

/** @var array[]  $entries */
/** @var array    $users_map */
/** @var array    $stores_map */
/** @var string   $filter_date */

$users_map   ??= [];
$stores_map  ??= [];
$filter_date ??= date('Y-m-d');

echo Flash::fromQuery('success', [
    'edited' => __('timeclock_edited'),
    'deleted' => __('timeclock_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('timeclocks') ?> <span class="page-count">(<?= count($entries) ?>)</span></h2>
</div>

<?= FilterBar::make()
    ->action(route_url('admin.timeclocks'))
    ->date('date', __('date'), $filter_date)
    ->select('store_id', __('store'), $stores_map, $_GET['store_id'] ?? '', __('all_stores'))
    ->render()
?>

<div class="card">
<?= Table::make()
    ->data($entries)
    ->emptyMessage(__('no_timeclock_entries'))
    ->column('#', fn($e) => (string) (int) ($e['id'] ?? 0))
    ->column(__('employee'), fn($e) =>
        '<a href="' . htmlspecialchars($BASE_URL . '/admin/users/' . (int)($e['user_id'] ?? 0) . '/edit') . '">'
        . htmlspecialchars($users_map[(int)($e['user_id'] ?? 0)] ?? '—') . '</a>')
    ->column(__('store'), fn($e) => htmlspecialchars($stores_map[(int)($e['store_id'] ?? 0)] ?? '—'))
    ->column(__('clock_in'), fn($e) => htmlspecialchars(substr($e['clock_in_time'] ?? '', 0, 16)))
    ->column(__('clock_out'), function($e) {
        if (empty($e['clock_out_time'])) {
            return Badge::make(__('timeclock_active'))->warning()->render();
        }
        return htmlspecialchars(substr($e['clock_out_time'], 0, 16));
    })
    ->column(__('duration'), function($e) {
        if (!empty($e['clock_out_time']) && $e['duration_minutes'] !== null && $e['duration_minutes'] !== '') {
            $m = (int) $e['duration_minutes'];
            return floor($m / 60) . 'h' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);
        }
        return '—';
    })
    ->column(__('status'), function($e) {
        return empty($e['clock_out_time'])
            ? Badge::make(__('clocked_in'))->success()->render()
            : Badge::make(__('clocked_out'))->inactive()->render();
    })
    ->column('', function($e) use ($BASE_URL, $filter_date) {
        $id = (int) ($e['id'] ?? 0);
        $html = '<div class="btn-group">';
        $html .= '<button type="button" class="btn btn--ghost btn--sm js-tc-edit"'
            . ' data-id="' . $id . '"'
            . ' data-clock-in="' . htmlspecialchars(substr($e['clock_in_time'] ?? '', 0, 16)) . '"'
            . ' data-clock-out="' . htmlspecialchars(substr($e['clock_out_time'] ?? '', 0, 16)) . '">'
            . __('edit') . '</button>';
        $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeclocks/' . $id . '/delete?date=' . urlencode($filter_date)) . '"'
            . ' onsubmit="return confirm(\'' . htmlspecialchars(__('confirm_delete_timeclock'), ENT_QUOTES) . '\')">'
            . csrf_field() . Button::make(__('delete'))->danger()->sm()->submit()->render() . '</form>';
        $html .= '</div>';
        return $html;
    })
    ->render()
?></div>

<div id="tc-admin-meta" data-base-url="<?= htmlspecialchars($BASE_URL) ?>" hidden></div>

<div id="tc-edit-modal" class="modal">
    <div class="modal__backdrop js-tc-close"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 class="modal__title"><?= __('edit_timeclock') ?></h3>
            <button type="button" class="modal__close js-tc-close">✕</button>
        </div>
        <form id="tc-edit-form" method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_date" value="<?= htmlspecialchars($filter_date) ?>">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('clock_in') ?></label>
                    <input type="datetime-local" name="clock_in_time" id="tc-in" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('clock_out') ?> <small class="text-muted">(<?= __('leave_empty_if_active') ?>)</small></label>
                    <input type="datetime-local" name="clock_out_time" id="tc-out" class="form-control">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost js-tc-close"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<script src="<?= $BASE_URL ?>/assets/js/modules/timeclock-admin.js"></script>
