<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Table;

/** @var array $user */
/** @var array $shifts_today */
/** @var array $upcoming */
/** @var array $pending_timeoff */
/** @var array $my_swaps */
/** @var array $pending_swaps */
/** @var array $stores_map  id → store name */
/** @var array|null $employee_month_stats */
/** @var array|null $active_clock */
/** @var array $enabled_widgets   widget_key → true (flipped) */
/** @var array $all_widgets       list of all widget keys */

$stats       = $employee_month_stats ?? null;
$stores_map  = $stores_map ?? [];
$activeClock = $active_clock ?? null;
$widgets     = $enabled_widgets ?? array_flip($all_widgets ?? []);

function widget_on(string $key, array $widgets): bool {
    return isset($widgets[$key]);
}

$feat = static fn(?string $f): bool =>
    $f === null || ($store_features ?? null) === null || in_array($f, (array) ($store_features ?? []), true);

$_empWidgetFeatMap = [
    'timeclock'       => 'timeclock',
    'pending_timeoff' => 'timeoff',
    'pending_swaps'   => 'swaps',
];
$all_widgets = array_values(array_filter(
    $all_widgets ?? [],
    fn(string $wk) => $feat($_empWidgetFeatMap[$wk] ?? null)
));
foreach ($_empWidgetFeatMap as $_wk => $_wf) {
    if (!$feat($_wf)) {
        unset($widgets[$_wk]);
    }
}
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('dashboard') ?></h2>
    <div class="page-header__actions">
        <?= Button::make(__('customize'))->ghost()->sm()->attrs(['onclick' => 'dashCustomizeToggle()'])->render() ?>
    </div>
</div>

<?php
ob_start();
?>
<form method="POST" action="<?= route_url('employee.dashboard.widgets') ?>">
    <?= csrf_field() ?>
    <div class="dash-widget-list">
        <?php
        $widgetLabels = [
            'timeclock'      => __('timeclock'),
            'shifts_today'   => __('my_shifts_today'),
            'upcoming'       => __('my_upcoming_shifts'),
            'monthly_stats'  => __('monthly_stats'),
            'pending_timeoff'=> __('my_pending_timeoff'),
            'pending_swaps'  => __('pending_swaps'),
        ];
        foreach ($all_widgets as $wk): ?>
        <label class="dash-widget-item">
            <input type="checkbox" name="widgets[<?= htmlspecialchars($wk) ?>]" value="1"
                   <?= widget_on($wk, $widgets) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars($widgetLabels[$wk] ?? $wk) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <div class="form-actions mt-sm">
        <?= Button::make(__('save'))->primary()->sm()->submit()->render() ?>
    </div>
</form>
<?php
echo Card::make()
    ->header('<span>' . __('dashboard_customize') . '</span> ' . Button::make(__('close'))->ghost()->sm()->attrs(['onclick' => 'dashCustomizeToggle()'])->render())
    ->body(ob_get_clean())
    ->attrs(['id' => 'dash-customize', 'class' => 'dash-customize-panel hidden'])
    ->render();
?>

<!-- ═══ Stat cards ═══ -->
<?php $_statCount = 2 + ($feat('timeoff') ? 1 : 0) + ($feat('swaps') ? 1 : 0); ?>
<div class="stat-grid" style="--stat-cols:<?= $_statCount ?>"><?php unset($_statCount); ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--primary">📅</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($shifts_today) ?></div>
            <div class="stat-card__label"><?= __('shifts_today') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--success">📋</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($upcoming) ?></div>
            <div class="stat-card__label"><?= __('shifts_upcoming') ?></div>
        </div>
    </div>
    <?php if ($feat('timeoff')): ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--warning">🌴</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($pending_timeoff) ?></div>
            <div class="stat-card__label"><?= __('pending_timeoff') ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($feat('swaps')): ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--danger">🔄</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= count($pending_swaps) ?></div>
            <div class="stat-card__label"><?= __('swaps') ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (widget_on('timeclock', $widgets)): ?>
<?php
ob_start();
if ($activeClock !== null):
    echo Badge::make(__('clocked_in'))->success()->render();
    echo ' <span class="text-muted">' . __('clocked_in_since') . ' <strong>' . htmlspecialchars(substr($activeClock['clock_in_time'] ?? '', 11, 5)) . '</strong></span>';
    echo ' ' . Button::make(__('clock_out'))->danger()->sm()->link(route_url('employee.timeclock'))->render();
else:
    echo Badge::make(__('not_clocked_in'))->neutral()->render();
    echo ' ' . Button::make(__('clock_in'))->success()->sm()->link(route_url('employee.timeclock'))->render();
endif;
echo Card::make()
    ->header('<span>' . __('timeclock') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.timeclock'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>

<?php if (widget_on('monthly_stats', $widgets) && $stats !== null): ?>
<?php $cur = $stats['currency'] ?? 'JPY'; ?>
<?php
$navPrev = Button::make('←')->ghost()->sm()->attrs(['onclick' => "dashMonthNav('" . htmlspecialchars($stats['prev_month']) . "')", 'class' => 'dash-month-btn'])->render();
$navNext = Button::make('→')->ghost()->sm()->attrs([
    'onclick' => "dashMonthNav('" . htmlspecialchars($stats['next_month']) . "')",
    'class' => 'dash-month-btn' . ($stats['is_current'] ? ' dash-month-btn--disabled' : ''),
    'disabled' => $stats['is_current'] ? 'disabled' : null,
])->render();
$navToday = !$stats['is_current']
    ? Button::make(__('today'))->ghost()->sm()->attrs(['onclick' => "dashMonthNav('" . date('Y-m') . "')"])->render()
    : '';
$headerHtml = $navPrev
    . ' <strong class="dash-month-label">' . htmlspecialchars($stats['month_label']) . '</strong> '
    . $navNext . ' ' . $navToday;

ob_start();
?>
<div class="dash-stat">
    <div class="dash-stat__value"><?= number_format($stats['hours_month'], 1) ?> h</div>
    <div class="dash-stat__label"><?= __('hours_this_month') ?></div>
</div>
<div class="dash-stat">
    <div class="dash-stat__value dash-stat__value--neutral"><?= number_format($stats['hours_week'], 1) ?> h</div>
    <div class="dash-stat__label"><?= __('avg_per_week') ?></div>
</div>
<div class="dash-stat dash-stat--wide">
    <?php if ($stats['has_rate']): ?>
        <div class="dash-stat__value dash-stat__value--success"><?= format_currency((float) $stats['estimated_pay'], $cur) ?></div>
    <?php else: ?>
        <div class="dash-stat__value--muted">—</div>
    <?php endif; ?>
    <div class="dash-stat__label"><?= __('estimated_pay') ?></div>
</div>
<?php if (!empty($stats['shift_details'])): ?>
<div class="dash-stat-actions">
    <?= Button::make(__('details') . ' ▼')->ghost()->sm()->attrs(['id' => 'dash-detail-toggle', 'onclick' => 'dashDetailToggle()'])->render() ?>
</div>
<?php endif; ?>
<?php
$bodyContent = ob_get_clean();
$detailContent = '';
if (!empty($stats['shift_details'])) {
    ob_start();
    ?>
    <?php
    $totalNet = 0; $totalPay = 0.0;
    $dt = Table::make()->data($stats['shift_details'])
        ->column(__('date'), fn($r) => '<span class="td-nowrap">' . htmlspecialchars($r['date_label'] ?? '') . '</span>')
        ->column(__('schedule'), fn($r) => '<span class="td-nowrap font-mono">' . htmlspecialchars($r['start'] ?? '') . '–' . htmlspecialchars($r['end'] ?? '') . '</span>')
        ->column(__('type'), fn($r) => htmlspecialchars($r['type_name'] ?? ''))
        ->column(__('net'), function($r) {
            $html = '<span class="td-nowrap">' . htmlspecialchars($r['net_hours_fmt'] ?? '') . '</span>';
            if (($r['pause_min'] ?? 0) > 0) {
                $html .= ' <small class="text-muted">(' . __('pause') . ' ' . (int)$r['pause_min'] . ' min)</small>';
            }
            return $html;
        });
    if ($stats['has_rate']) {
        $dt->column(__('rate_h'), fn($r) => '<span class="td-muted">' . htmlspecialchars($r['rate_fmt'] ?? '') . '</span>')
           ->column(__('estimated_pay'), function($r) {
               return '<span class="' . ($r['has_rate'] ? 'pay-ok' : 'pay-none') . '">' . htmlspecialchars($r['pay_fmt'] ?? '') . '</span>';
           }, 'td-right');
    }
    $dt->footer(function($data) use ($totalNet, $stats, $cur) {
        $netTotal = 0;
        foreach ($data as $r) { $netTotal += (int) ($r['net_min'] ?? 0); }
        $th = intdiv($netTotal, 60); $tm = $netTotal % 60;
        $html = '<tr class="tr-total"><td colspan="3" class="td-muted">' . __('total') . '</td>';
        $html .= '<td class="td-nowrap">' . $th . 'h' . str_pad((string)$tm, 2, '0', STR_PAD_LEFT) . '</td>';
        if ($stats['has_rate']) {
            $html .= '<td></td><td class="pay-ok">' . format_currency((float)$stats['estimated_pay'], $cur) . '</td>';
        }
        $html .= '</tr>';
        return $html;
    });
    echo $dt->render();
    ?>
<?php
    $detailContent = '<div id="dash-detail" class="dash-detail-wrap">' . ob_get_clean() . '</div>';
}
echo Card::make()
    ->header($headerHtml)
    ->body($bodyContent . $detailContent)
    ->render();
?>
<?php endif; ?>

<?php if (widget_on('shifts_today', $widgets)): ?>
<?php
ob_start();
if (empty($shifts_today)):
    echo '<div class="empty-state">' . __('no_shift_today') . '</div>';
else:
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($shifts_today)
        ->column(__('store'), fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('Store #' . (int)($s['store_id'] ?? 0))))
        ->column(__('start'), fn($s) => htmlspecialchars((string) ($s['start_time'] ?? '—')))
        ->column(__('end'), fn($s) => htmlspecialchars((string) ($s['end_time'] ?? '—')))
        ->column(__('pause'), fn($s) => (int) ($s['pause_minutes'] ?? 0) . ' min')
        ->column(__('notes'), fn($s) => htmlspecialchars((string) ($s['notes'] ?? '')))
        ->render() ?>
</div>
<?php
endif;
echo Card::make()
    ->header('<span>' . __('my_shifts_today') . ' — ' . date('d/m/Y') . '</span>')
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>

<?php if (widget_on('upcoming', $widgets)): ?>
<?php
ob_start();
if (empty($upcoming)):
    echo '<div class="empty-state">' . __('no_upcoming_shift') . '</div>';
else:
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($upcoming)
        ->column(__('date'), fn($s) => htmlspecialchars((string) ($s['shift_date'] ?? '—')))
        ->column(__('store'), fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('Store #' . (int)($s['store_id'] ?? 0))))
        ->column(__('start'), fn($s) => htmlspecialchars((string) ($s['start_time'] ?? '—')))
        ->column(__('end'), fn($s) => htmlspecialchars((string) ($s['end_time'] ?? '—')))
        ->render() ?>
</div>
<?php
endif;
echo Card::make()
    ->header('<span>' . __('my_upcoming_shifts') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.shifts.day'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>

<?php if (widget_on('pending_timeoff', $widgets) && !empty($pending_timeoff)): ?>
<?php
ob_start();
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($pending_timeoff)
        ->column(__('type'), fn($r) => htmlspecialchars((string) ($r['type'] ?? '—')))
        ->column(__('from'), fn($r) => htmlspecialchars((string) ($r['start_date'] ?? '—')))
        ->column(__('to'), fn($r) => htmlspecialchars((string) ($r['end_date'] ?? '—')))
        ->column(__('status'), fn($r) => Badge::make(__('pending'))->pending()->render())
        ->render() ?>
</div>
<?php
echo Card::make()
    ->header('<span>' . __('my_pending_timeoff') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.timeoff'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>

<?php if (widget_on('pending_swaps', $widgets) && !empty($pending_swaps)): ?>
<?php
ob_start();
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($pending_swaps)
        ->column(__('date'), fn($s) => htmlspecialchars((string) ($s['shift_date'] ?? '—')))
        ->column(__('from'), fn($s) => htmlspecialchars((string) ($s['requester_name'] ?? '#' . ($s['requester_id'] ?? '?'))))
        ->column('', fn($s) => Button::make(__('view'))->outline()->sm()->link(route_url('employee.swaps'))->render())
        ->render() ?>
</div>
<?php
echo Card::make()
    ->header('<span>' . __('pending_swaps') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.swaps'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>

<script src="<?= $BASE_URL ?>/assets/js/modules/dashboard.js"></script>
