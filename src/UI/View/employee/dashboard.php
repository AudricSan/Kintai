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
// Widgets des bundles (Bundle::dashboardWidgets(), contrat 1.4) : HTML déjà rendu par le contrôleur, affiché en fin
// de page ; $widgetsAll = choix de l'utilisateur, y compris ces widgets (panneau « Personnaliser »).
$bundle_widgets     ??= [];
$bundle_widget_meta ??= ['labels' => [], 'permissions' => [], 'features' => []];
$widgetsAll  = $enabled_widgets_all ?? $widgets;

function widget_on(string $key, array $widgets): bool {
    return isset($widgets[$key]);
}

$feat = static fn(?string $f): bool =>
    feat_bundle($f) && (
        $f === null || ($store_features ?? null) === null || in_array($f, (array) ($store_features ?? []), true)
    );

// Permission-gating des widgets (même pattern que le dashboard admin, voir dashboard/index.php)
$can = $user_can ?? fn(string $k): bool => true;

$_empWidgetFeatMap = [
    'timeclock'       => 'timeclock',
    'pending_timeoff' => 'timeoff',
    'pending_swaps'   => 'swaps',
] + $bundle_widget_meta['features'];
$_empWidgetPermMap = $bundle_widget_meta['permissions'];
$all_widgets = array_values(array_filter(
    $all_widgets ?? [],
    fn(string $wk) => $feat($_empWidgetFeatMap[$wk] ?? null) && (!isset($_empWidgetPermMap[$wk]) || $can($_empWidgetPermMap[$wk]))
));
foreach ($_empWidgetFeatMap as $_wk => $_wf) {
    if (!$feat($_wf)) {
        unset($widgets[$_wk], $widgetsAll[$_wk]);
    }
}
foreach ($_empWidgetPermMap as $_wk => $_wp) {
    if (!$can($_wp)) {
        unset($widgets[$_wk], $widgetsAll[$_wk]);
    }
}

?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('dashboard') ?></h2>
    <div class="page-header__actions">
        <?= Button::make(__('customize'))->ghost()->sm()->attrs(['data-on-click' => 'dashCustomizeToggle'])->render() ?>
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
        ] + $bundle_widget_meta['labels'];
        foreach ($all_widgets as $wk): ?>
        <label class="dash-widget-item">
            <input type="checkbox" name="widgets[<?= htmlspecialchars($wk) ?>]" value="1"
                   <?= widget_on($wk, $widgetsAll) ? 'checked' : '' ?>>
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
    ->header('<span>' . __('dashboard_customize') . '</span> ' . Button::make(__('close'))->ghost()->sm()->attrs(['data-on-click' => 'dashCustomizeToggle'])->render())
    ->body(ob_get_clean())
    ->attrs(['id' => 'dash-customize', 'class' => 'dash-customize-panel hidden'])
    ->render();
?>

<!-- ═══ Stat cards ═══ -->
<?php $bundle_stats ??= []; // Cartes des bundles (congés, échanges… — Bundle::dashboardStats(), contrat 1.19). ?>
<?php $_statCount = 2 + count($bundle_stats); ?>
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
    <?php foreach ($bundle_stats as $_stat): ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--<?= htmlspecialchars($_stat['tone']) ?>"><?= htmlspecialchars($_stat['icon']) ?></div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= htmlspecialchars((string) $_stat['value']) ?></div>
            <div class="stat-card__label"><?= htmlspecialchars($_stat['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
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
<?php $cur = $stats['currency'] ?? 'JPY'; $curStyle = $stats['currency_symbol_style'] ?? 'kanji'; ?>
<?php
$navPrev = Button::make('←')->ghost()->sm()->attrs(['data-on-click' => 'dashMonthNav', 'data-args' => json_encode([$stats['prev_month']]), 'class' => 'dash-month-btn'])->render();
$navNext = Button::make('→')->ghost()->sm()->attrs([
    'data-on-click' => 'dashMonthNav', 'data-args' => json_encode([$stats['next_month']]),
    'class' => 'dash-month-btn' . ($stats['is_current'] ? ' dash-month-btn--disabled' : ''),
    'disabled' => $stats['is_current'] ? 'disabled' : null,
])->render();
$navToday = !$stats['is_current']
    ? Button::make(__('today'))->ghost()->sm()->attrs(['data-on-click' => 'dashMonthNav', 'data-args' => json_encode([date('Y-m')])])->render()
    : '';
$headerHtml = $navPrev
    . ' <strong class="dash-month-label">' . htmlspecialchars($stats['month_label']) . '</strong> '
    . $navNext . ' ' . $navToday;

ob_start();
?>
<div class="dash-stat">
    <div class="dash-stat__value"><?= number_format($stats['hours_month'], 1) ?> <?= __('hours_unit') ?></div>
    <div class="dash-stat__label"><?= __('hours_this_month') ?></div>
</div>
<div class="dash-stat">
    <div class="dash-stat__value dash-stat__value--neutral"><?= number_format($stats['hours_week'], 1) ?> <?= __('hours_unit') ?></div>
    <div class="dash-stat__label"><?= __('avg_per_week') ?></div>
</div>
<div class="dash-stat dash-stat--wide">
    <?php if ($stats['has_rate']): ?>
        <div class="dash-stat__value dash-stat__value--success"><?= format_currency((float) $stats['estimated_pay'], $cur, $curStyle) ?></div>
    <?php else: ?>
        <div class="dash-stat__value--muted">—</div>
    <?php endif; ?>
    <div class="dash-stat__label"><?= __('estimated_pay') ?></div>
</div>
<?php if (!empty($stats['shift_details'])): ?>
<div class="dash-stat-actions">
    <?= Button::make(__('details') . ' ▼')->ghost()->sm()->attrs(['id' => 'dash-detail-toggle', 'data-on-click' => 'dashDetailToggle'])->render() ?>
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
        ->text(__('type'), fn($r) => $r['type_name'] ?? '')
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
    $dt->footer(function($data) use ($totalNet, $stats, $cur, $curStyle) {
        $netTotal = 0;
        foreach ($data as $r) { $netTotal += (int) ($r['net_min'] ?? 0); }
        $th = intdiv($netTotal, 60); $tm = $netTotal % 60;
        $html = '<tr class="tr-total"><td colspan="3" class="td-muted">' . __('total') . '</td>';
        $html .= '<td class="td-nowrap">' . $th . 'h' . str_pad((string)$tm, 2, '0', STR_PAD_LEFT) . '</td>';
        if ($stats['has_rate']) {
            $html .= '<td></td><td class="pay-ok">' . format_currency((float)$stats['estimated_pay'], $cur, $curStyle) . '</td>';
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
        ->text(__('store'), fn($s) => $stores_map[(int)($s['store_id'] ?? 0)] ?? ('Store #' . (int)($s['store_id'] ?? 0)))
        ->text(__('start'), fn($s) => (string) ($s['start_time'] ?? '—'))
        ->text(__('end'), fn($s) => (string) ($s['end_time'] ?? '—'))
        ->column(__('pause'), fn($s) => (int) ($s['pause_minutes'] ?? 0) . ' min')
        ->text(__('notes'), fn($s) => (string) ($s['notes'] ?? ''))
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
        ->text(__('date'), fn($s) => (string) ($s['shift_date'] ?? '—'))
        ->text(__('store'), fn($s) => $stores_map[(int)($s['store_id'] ?? 0)] ?? ('Store #' . (int)($s['store_id'] ?? 0)))
        ->text(__('start'), fn($s) => (string) ($s['start_time'] ?? '—'))
        ->text(__('end'), fn($s) => (string) ($s['end_time'] ?? '—'))
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
        ->text(__('type'), fn($r) => (string) ($r['type'] ?? '—'))
        ->text(__('from'), fn($r) => (string) ($r['start_date'] ?? '—'))
        ->text(__('to'), fn($r) => (string) ($r['end_date'] ?? '—'))
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
        ->text(__('date'), fn($s) => (string) ($s['shift_date'] ?? '—'))
        ->text(__('from'), fn($s) => (string) ($s['requester_name'] ?? '#' . ($s['requester_id'] ?? '?')))
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

<?php foreach ($bundle_widgets as $_bundleWidget): ?>
<?= $_bundleWidget['html'] ?>
<?php endforeach; ?>

<script src="<?= $BASE_URL ?>/assets/js/modules/dashboard.js"></script>
