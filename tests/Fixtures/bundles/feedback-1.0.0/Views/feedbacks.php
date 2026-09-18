<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;
use kintai\UI\Components\FilterBar;
use kintai\UI\Components\Pagination;

/** @var array[]  $feedbacks */
/** @var array    $users_map */
/** @var array    $stores_map */
/** @var array    $shifts_map */
/** @var array    $shift_types_map */
/** @var string   $filter_store */
/** @var string   $filter_category */
/** @var int      $current_page */
/** @var int      $total_pages */
/** @var int      $total */

$users_map        ??= [];
$stores_map       ??= [];
$shifts_map       ??= [];
$shift_types_map  ??= [];
$filter_store     ??= '';
$filter_category  ??= '';
$current_page     ??= 1;
$total_pages      ??= 1;
$total            ??= 0;

echo Flash::fromQuery('success', ['deleted' => __('feedback_deleted')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('feedbacks') ?> <span class="page-count">(<?= (int) $total ?>)</span></h2>
</div>

<?php include BASE_PATH . '/src/UI/View/_partials/_settings-tabs.php'; ?>

<?php
$catOptions = ['' => __('all_categories'), 'shift' => __('feedback_cat_shift'), 'schedule' => __('feedback_cat_schedule'), 'app' => __('feedback_cat_app'), 'other' => __('feedback_cat_other')];
echo FilterBar::make()
    ->action(route_url('admin.feedbacks'))
    ->select('store_id', __('store'), $stores_map, $filter_store, __('all_stores'))
    ->select('category', __('feedback_category'), $catOptions, $filter_category)
    ->render();
?>

<div class="card">
<?= Table::make()
    ->data($feedbacks)
    ->emptyMessage(__('no_feedback'))
    ->column(__('date_hour'), fn($fb) => htmlspecialchars($fb['created_at'] ?? ''))
    ->column(__('store'), fn($fb) => htmlspecialchars($stores_map[(int) ($fb['store_id'] ?? 0)] ?? '—'))
    ->column(__('employee'), function($fb) use ($BASE_URL, $users_map) {
        if (!empty($fb['anonymous'])) {
            return '<em class="text-dim">' . __('feedback_anonymous_author') . '</em>';
        }
        $uid = (int) ($fb['user_id'] ?? 0);
        return '<a href="' . htmlspecialchars($BASE_URL . '/admin/users/' . $uid . '/edit') . '">'
            . htmlspecialchars($users_map[$uid] ?? ('#' . $uid)) . '</a>';
    })
    ->column(__('feedback_category'), function($fb) use ($shifts_map, $shift_types_map) {
        $cat = $fb['category'] ?? 'other';
        $label = match($cat) {
            'shift'    => __('feedback_cat_shift'),
            'schedule' => __('feedback_cat_schedule'),
            'app'      => __('feedback_cat_app'),
            default    => __('feedback_cat_other'),
        };
        $color = match($cat) {
            'shift'    => 'primary',
            'schedule' => 'warning',
            'app'      => 'admin',
            default    => 'inactive',
        };
        $html = Badge::make($label)->variant($color)->render();
        $sid = (int) ($fb['shift_id'] ?? 0);
        if ($sid > 0 && isset($shifts_map[$sid])) {
            $s = $shifts_map[$sid];
            $tname = $shift_types_map[(int) ($s['shift_type_id'] ?? 0)] ?? '';
            $html .= '<div class="text-hint mt-xs">'
                . htmlspecialchars($s['shift_date'] ?? '') . ' '
                . htmlspecialchars($tname) . ' '
                . htmlspecialchars(substr($s['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($s['end_time'] ?? '', 0, 5))
                . '</div>';
        }
        return $html;
    })
    ->column(__('feedback_rating'), function($fb) {
        $r = $fb['rating'] ?? null;
        if ($r === null) return '<span class="text-dim">—</span>';
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $on = $i <= (int) $r ? ' fb-star--on' : '';
            $stars .= '<span class="fb-star' . $on . '">★</span>';
        }
        return '<span class="fb-stars" title="' . (int)$r . '/5">' . $stars . '</span>';
    })
    ->column(__('feedback_message'), function($fb) {
        $html = nl2br(htmlspecialchars($fb['message'] ?? ''));
        $context = array_filter([
            $fb['page_path'] ?? null,
            isset($fb['app_version']) ? 'v' . $fb['app_version'] : null,
            match ($fb['device_type'] ?? null) {
                'mobile'  => __('feedback_device_mobile'),
                'desktop' => __('feedback_device_desktop'),
                default   => null,
            },
        ]);
        if ($context !== []) {
            $html .= '<div class="text-hint mt-xs">' . htmlspecialchars(implode(' · ', $context)) . '</div>';
        }
        return $html;
    })
    ->column('', function($fb) use ($BASE_URL) {
        $id = (int) ($fb['id'] ?? 0);
        return '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/feedbacks/' . $id . '/delete') . '"'
            . ' data-confirm="' . htmlspecialchars(__('feedback_delete_confirm'), ENT_QUOTES) . '">'
            . csrf_field() . Button::make(__('delete'))->danger()->sm()->submit()->render() . '</form>';
    })
    ->render()
?></div>

<?php if ($total_pages > 1): ?>
    <?= Pagination::make($current_page, $total_pages, route_url('admin.feedbacks'))->render() ?>
<?php endif; ?>
