<?php
/** @var array  $stats */
/** @var array  $shifts_today */
/** @var array  $pending_timeoff */
/** @var array  $pending_swaps */
/** @var array  $active_clocks_now  pointages actifs en ce moment */
/** @var array  $users_map          id → display_name */
/** @var array  $enabled_widgets    key → index (array_flip) */
/** @var array  $all_widgets        ordered list of widget keys */
$users_map        ??= [];
$active_clocks_now ??= [];
$enabled_widgets        ??= array_flip(\kintai\UI\Controller\Web\HomeController::ADMIN_WIDGETS);
$all_widgets            ??= \kintai\UI\Controller\Web\HomeController::ADMIN_WIDGETS;

function admin_widget_on(string $key, array $enabled): bool
{
    return array_key_exists($key, $enabled);
}

// Feature-gating : null = toutes features actives (owner/is_admin), array = liste des features autorisées
$feat = static fn(?string $f): bool =>
    feat_bundle($f) && (
        $f === null || ($store_features ?? null) === null || in_array($f, (array) ($store_features ?? []), true)
    );

// Permission-gating des liens de navigation rapide (même pattern que _topbar.php)
$can = $user_can ?? fn(string $k): bool => true;

// Widgets désactivés si la feature associée est indisponible
$_widgetFeatMap = [
    'pending_timeoff'  => 'timeoff',
    'pending_swaps'    => 'swaps',
    'timeclocks_today' => 'timeclock',
];
// Widgets masqués (panneau de personnalisation + affichage) si la permission requise manque
$_widgetPermMap = [
    'store_stats_summary' => 'payroll.view',
];
// Filtrer all_widgets par features/permissions pour le panneau de personnalisation
$all_widgets = array_values(array_filter(
    $all_widgets,
    fn(string $wk) => $feat($_widgetFeatMap[$wk] ?? null) && (!isset($_widgetPermMap[$wk]) || $can($_widgetPermMap[$wk]))
));
// Retirer des enabled_widgets les widgets dont la feature/permission est indisponible
foreach ($_widgetFeatMap as $_wk => $_wf) {
    if (!$feat($_wf)) {
        unset($enabled_widgets[$_wk]);
    }
}
foreach ($_widgetPermMap as $_wk => $_wp) {
    if (!$can($_wp)) {
        unset($enabled_widgets[$_wk]);
    }
}

$widgetLabels = [
    'kpi_counters'         => __('widget_kpi_counters'),
    'quick_nav'            => __('widget_quick_nav'),
    'store_stats_summary'  => __('widget_store_stats_summary'),
    'shifts_today'         => __('widget_shifts_today'),
    'pending_timeoff'      => __('widget_pending_timeoff'),
    'pending_swaps'        => __('widget_pending_swaps'),
    'timeclocks_today' => __('widget_timeclocks_today'),
];
?>

<!-- En-tête dashboard + bouton Personnaliser -->
<div class="page-header">
    <h2 class="page-header__title"><?= __('dashboard') ?></h2>
    <button type="button" class="btn btn--ghost btn--sm" onclick="adminDashCustomize()">
        <?= __('customize') ?>
    </button>
</div>

<!-- Panneau de personnalisation -->
<div id="admin-dash-customize" class="dash-customize-panel card card--mb hidden">
    <div class="card-header">
        <span><?= __('dashboard_customize') ?></span>
        <button type="button" class="btn btn--ghost btn--sm" onclick="adminDashCustomize()"><?= __('close') ?></button>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= route_url('admin.dashboard.widgets') ?>">
            <?= csrf_field() ?>
            <div class="dash-widget-list">
                <?php foreach ($all_widgets as $wk): ?>
                <label class="dash-widget-item">
                    <input type="checkbox" name="widgets[<?= htmlspecialchars($wk) ?>]" value="1"
                           <?= admin_widget_on($wk, $enabled_widgets) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($widgetLabels[$wk] ?? $wk) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="form-actions mt-sm">
                <button type="submit" class="btn btn--primary btn--sm"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<script src="<?= $BASE_URL ?>/assets/js/modules/admin-dashboard.js"></script>

<?php if (admin_widget_on('kpi_counters', $enabled_widgets)): ?>
<!-- KPI Stats -->
<?php $_statCount = 3 + (($feat('timeoff') || $feat('swaps')) ? 1 : 0); ?>
<div class="stat-grid" style="--stat-cols:<?= $_statCount ?>"><?php unset($_statCount); ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--primary">👥</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['users'] ?></div>
            <div class="stat-card__label"><?= __('users_plural') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--success">🏬</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['stores'] ?></div>
            <div class="stat-card__label"><?= __('stores_plural') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--warning">📅</div>
        <div class="stat-card__body">
            <div class="stat-card__value"><?= $stats['shifts_today'] ?></div>
            <div class="stat-card__label"><?= __('shifts_today') ?></div>
        </div>
    </div>
    <?php if ($feat('timeoff') || $feat('swaps')): ?>
    <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--danger">⏳</div>
        <div class="stat-card__body">
            <?php
            $_pendingCount = ($feat('timeoff') ? count($pending_timeoff) : 0)
                           + ($feat('swaps')   ? count($pending_swaps)   : 0);
            ?>
            <div class="stat-card__value"><?= $_pendingCount ?></div>
            <div class="stat-card__label"><?= __('pending_requests') ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$_qnLinks = [
    ['route' => 'admin.users',        'perm' => 'employees.view', 'icon' => '👤',  'label' => 'manage_users',      'feat' => null],
    ['route' => 'admin.stores',       'perm' => 'stores.view',    'icon' => '🏬',  'label' => 'manage_stores',     'feat' => null],
    ['route' => 'admin.shifts',       'perm' => 'shifts.view',    'icon' => '📋',  'label' => 'manage_shifts',     'feat' => null],
    ['route' => 'admin.shift_types',  'perm' => 'shifts.view',    'icon' => '🏷️', 'label' => 'shift_types',       'feat' => null],
    ['route' => 'admin.timeoff',      'perm' => 'timeoff.view',   'icon' => '🌴',  'label' => 'timeoff_requests',  'feat' => 'timeoff'],
    ['route' => 'admin.swap_requests','perm' => 'swaps.view',     'icon' => '🔄',  'label' => 'swap_requests',     'feat' => 'swaps'],
];
$_qnLinks = array_values(array_filter(
    $_qnLinks,
    fn(array $l) => ($l['feat'] === null || $feat($l['feat'])) && $can($l['perm'])
));
?>
<?php if (admin_widget_on('quick_nav', $enabled_widgets) && $_qnLinks !== []): ?>
<!-- Navigation rapide -->
<div class="quick-nav" style="--qn-cols:<?= count($_qnLinks) ?>">
    <?php foreach ($_qnLinks as $_qn): ?>
    <a href="<?= route_url($_qn['route']) ?>" class="quick-nav-card">
        <span class="quick-nav-card__icon"><?= $_qn['icon'] ?></span>
        <span class="quick-nav-card__label"><?= __($_qn['label']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php unset($_qnLinks, $_qn); ?>

<?php
$store_stats_rows          ??= [];
$store_stats_period        ??= 30;
$store_stats_hours_by_week ??= [];
?>
<?php if (admin_widget_on('store_stats_summary', $enabled_widgets) && $can('payroll.view') && $store_stats_rows !== []): ?>
<!-- Aperçu statistiques -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('widget_store_stats_summary') ?> — <?= (int) $store_stats_period ?>j</span>
    </div>
    <?php if (count($store_stats_rows) === 1): ?>
        <?php $_row = $store_stats_rows[0]; ?>
        <div class="stat-grid" style="--stat-cols:3">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--primary">⏱️</div>
                <div class="stat-card__body">
                    <div class="stat-card__value"><?= number_format((float) $_row['total_hours'], 1) ?>h</div>
                    <div class="stat-card__label"><?= __('net_hours') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--success">💰</div>
                <div class="stat-card__body">
                    <div class="stat-card__value"><?= format_currency((float) $_row['total_cost'], (string) $_row['currency'], (string) $_row['currency_symbol_style']) ?></div>
                    <div class="stat-card__label"><?= __('total_cost') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--warning">📈</div>
                <div class="stat-card__body">
                    <div class="stat-card__value"><?= $_row['stability'] !== null ? (int) $_row['stability'] : '—' ?></div>
                    <div class="stat-card__label"><?= __('score_stability') ?></div>
                </div>
            </div>
        </div>
        <?php if (count($store_stats_hours_by_week) > 1): ?>
        <div class="card-body">
            <div class="sstat-sublabel--strong"><?= __('weekly_hours_trend') ?></div>
            <div class="sstat-canvas-wrap">
                <canvas id="chart-weekly-hours"></canvas>
            </div>
        </div>
        <script type="application/json" id="kintai-stats-data"><?= json_encode([
            'hoursByWeek' => array_map('floatval', $store_stats_hours_by_week),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
        <script src="<?= $BASE_URL ?>/assets/js/modules/stats-charts.js"></script>
        <?php endif; ?>
        <div class="card-body">
            <a href="<?= route_url('admin.stores.stats', ['id' => $_row['store_id']]) ?>" class="card-header-link"><?= __('statistics') ?> →</a>
        </div>
        <?php unset($_row); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <th><?= __('store') ?></th>
                        <th><?= __('net_hours') ?></th>
                        <th><?= __('total_cost') ?></th>
                        <th><?= __('score_stability') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($store_stats_rows as $_row): ?>
                        <tr class="tr--clickable" onclick="location.href='<?= route_url('admin.stores.stats', ['id' => $_row['store_id']]) ?>'">
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars((string) $_row['store_name']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('net_hours')) ?>"><?= number_format((float) $_row['total_hours'], 1) ?>h</td>
                            <td data-label="<?= htmlspecialchars(__('total_cost')) ?>"><?= format_currency((float) $_row['total_cost'], (string) $_row['currency'], (string) $_row['currency_symbol_style']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('score_stability')) ?>"><?= $_row['stability'] !== null ? (int) $_row['stability'] : '—' ?></td>
                            <td><a href="<?= route_url('admin.stores.stats', ['id' => $_row['store_id']]) ?>" class="card-header-link"><?= __('statistics') ?> →</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php unset($_row); ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$sort       ??= 'start_asc';
$_qs = fn(string $s): string => '?' . http_build_query(['sort' => $s]);
$_icon = function (string $field) use ($sort): string {
    [$f, $d] = explode('_', $sort, 2);
    if ($f !== $field) return '<span class="sort-icon-dim">↕</span>';
    return $d === 'asc' ? '↑' : '↓';
};
$_href = function (string $field) use ($sort, $_qs): string {
    [$f, $d] = explode('_', $sort, 2);
    $dir = ($f === $field && $d === 'asc') ? 'desc' : 'asc';
    return $_qs("{$field}_{$dir}");
};
?>

<?php if (admin_widget_on('shifts_today', $enabled_widgets) && $can('shifts.view')): ?>
<!-- Shifts du jour -->
<?php
// Regrouper par magasin
$shiftsByStore = [];
foreach ($shifts_today as $shift) {
    $sid   = (int) ($shift['store_id'] ?? 0);
    $sname = (string) ($shift['store_name'] ?? ('Store #' . $sid));
    if (!isset($shiftsByStore[$sid])) {
        $shiftsByStore[$sid] = ['name' => $sname, 'shifts' => []];
    }
    $shiftsByStore[$sid]['shifts'][] = $shift;
}
$hasMultipleStores = count($shiftsByStore) > 1;
?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('shifts_of_day') ?> — <?= date('d/m/Y') ?></span>
        <a href="<?= route_url('admin.shifts') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($shifts_today)): ?>
        <div class="empty-state"><?= __('no_shift_today') ?></div>
    <?php else: ?>
        <?php if ($hasMultipleStores): ?>
        <!-- Onglets par magasin -->
        <div class="dash-tabs" id="dash-shifts-tabs">
            <div class="dash-tabs-nav">
                <button type="button" class="dash-tab-btn" data-tab="all" onclick="dashTab('all')">
                    <?= __('all') ?> <span class="badge badge--secondary badge--sm"><?= count($shifts_today) ?></span>
                </button>
                <?php foreach ($shiftsByStore as $sid => $sGroup): ?>
                    <button type="button" class="dash-tab-btn" data-tab="s<?= $sid ?>" onclick="dashTab('s<?= $sid ?>')">
                        <?= htmlspecialchars($sGroup['name']) ?>
                        <span class="badge badge--secondary badge--sm"><?= count($sGroup['shifts']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Onglet Tous -->
            <div class="dash-tab-panel" id="dash-tab-all">
                <div class="table-wrap">
                    <table class="data-table" data-mob-stack>
                        <thead><tr>
                            <th><a href="<?= $_href('store') ?>" class="sort-link"><?= __('store') ?> <?= $_icon('store') ?></a></th>
                            <th><a href="<?= $_href('user') ?>" class="sort-link"><?= __('user') ?> <?= $_icon('user') ?></a></th>
                            <th><a href="<?= $_href('start') ?>" class="sort-link"><?= __('start') ?> <?= $_icon('start') ?></a></th>
                            <th><a href="<?= $_href('end') ?>" class="sort-link"><?= __('end') ?> <?= $_icon('end') ?></a></th>
                            <th><a href="<?= $_href('pause') ?>" class="sort-link"><?= __('pause') ?> <?= $_icon('pause') ?></a></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($shifts_today as $shift): ?>
                                <tr class="tr--clickable" onclick="location.href='<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/edit'">
                                    <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars((string) ($shift['store_name'] ?? '—')) ?></td>
                                    <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars((string) ($shift['user_name'] ?? '—')) ?></td>
                                    <td data-label="<?= htmlspecialchars(__('start')) ?>"><?= htmlspecialchars((string) ($shift['start_time'] ?? '—')) ?></td>
                                    <td data-label="<?= htmlspecialchars(__('end')) ?>"><?= htmlspecialchars((string) ($shift['end_time'] ?? '—')) ?></td>
                                    <td data-label="<?= htmlspecialchars(__('pause')) ?>"><?= (int) ($shift['pause_minutes'] ?? 0) ?> min</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Un onglet par magasin -->
            <?php foreach ($shiftsByStore as $sid => $sGroup): ?>
                <div class="dash-tab-panel" id="dash-tab-s<?= $sid ?>">
                    <div class="table-wrap">
                        <table class="data-table" data-mob-stack>
                            <thead><tr>
                                <th><a href="<?= $_href('user') ?>" class="sort-link"><?= __('user') ?> <?= $_icon('user') ?></a></th>
                                <th><a href="<?= $_href('start') ?>" class="sort-link"><?= __('start') ?> <?= $_icon('start') ?></a></th>
                                <th><a href="<?= $_href('end') ?>" class="sort-link"><?= __('end') ?> <?= $_icon('end') ?></a></th>
                                <th><a href="<?= $_href('pause') ?>" class="sort-link"><?= __('pause') ?> <?= $_icon('pause') ?></a></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($sGroup['shifts'] as $shift): ?>
                                    <tr class="tr--clickable" onclick="location.href='<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/edit'">
                                        <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars((string) ($shift['user_name'] ?? '—')) ?></td>
                                        <td data-label="<?= htmlspecialchars(__('start')) ?>"><?= htmlspecialchars((string) ($shift['start_time'] ?? '—')) ?></td>
                                        <td data-label="<?= htmlspecialchars(__('end')) ?>"><?= htmlspecialchars((string) ($shift['end_time'] ?? '—')) ?></td>
                                        <td data-label="<?= htmlspecialchars(__('pause')) ?>"><?= (int) ($shift['pause_minutes'] ?? 0) ?> min</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
        (function () {
            var LS_KEY = 'kintai_dash_shifts_tab';
            function dashTab(id) {
                document.querySelectorAll('#dash-shifts-tabs .dash-tab-panel').forEach(function (p) { p.style.display = 'none'; });
                document.querySelectorAll('#dash-shifts-tabs .dash-tab-btn').forEach(function (b) { b.classList.remove('dash-tab-btn--active'); });
                var panel = document.getElementById('dash-tab-' + id);
                var btn   = document.querySelector('#dash-shifts-tabs [data-tab="' + id + '"]');
                if (panel) panel.style.display = 'block';
                if (btn)   btn.classList.add('dash-tab-btn--active');
                try { localStorage.setItem(LS_KEY, id); } catch(e) {}
            }
            window.dashTab = dashTab;
            var saved = localStorage.getItem(LS_KEY) || 'all';
            // Vérifier que l'onglet sauvegardé existe (le store peut ne plus être là)
            if (!document.getElementById('dash-tab-' + saved)) saved = 'all';
            dashTab(saved);
        }());
        </script>

        <?php else: ?>
        <!-- Un seul magasin : pas d'onglets -->
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><a href="<?= $_href('user') ?>" class="sort-link"><?= __('user') ?> <?= $_icon('user') ?></a></th>
                        <th><a href="<?= $_href('start') ?>" class="sort-link"><?= __('start') ?> <?= $_icon('start') ?></a></th>
                        <th><a href="<?= $_href('end') ?>" class="sort-link"><?= __('end') ?> <?= $_icon('end') ?></a></th>
                        <th><a href="<?= $_href('pause') ?>" class="sort-link"><?= __('pause') ?> <?= $_icon('pause') ?></a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts_today as $shift): ?>
                        <tr class="tr--clickable" onclick="location.href='<?= $BASE_URL ?>/admin/shifts/<?= (int)$shift['id'] ?>/edit'">
                            <td><?= (int) $shift['id'] ?></td>
                            <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars((string) ($shift['user_name'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('start')) ?>"><?= htmlspecialchars((string) ($shift['start_time'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('end')) ?>"><?= htmlspecialchars((string) ($shift['end_time'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('pause')) ?>"><?= (int) ($shift['pause_minutes'] ?? 0) ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (
    (admin_widget_on('pending_timeoff', $enabled_widgets) && $can('timeoff.view'))
    || (admin_widget_on('pending_swaps', $enabled_widgets) && $can('swaps.view'))
): ?>
<!-- Demandes en attente -->
<div class="dash-two-col card--mt">

    <?php if (admin_widget_on('pending_timeoff', $enabled_widgets) && $feat('timeoff') && $can('timeoff.view')): ?>
    <div class="card">
        <div class="card-header">
            <span><?= __('pending_timeoff') ?></span>
            <a href="<?= route_url('admin.timeoff') ?>" class="card-header-link"><?= __('view_all') ?></a>
        </div>
        <?php if (empty($pending_timeoff)): ?>
            <div class="empty-state"><?= __('no_pending_request') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" data-mob-stack>
                    <thead>
                        <tr><th>#</th><th><?= __('user') ?></th><th><?= __('type') ?></th><th><?= __('from') ?></th><th><?= __('to') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_timeoff as $req): ?>
                            <tr>
                                <td data-label="#"><?= (int) $req['id'] ?></td>
                                <td data-label="<?= htmlspecialchars(__('user')) ?>"><a href="<?= $BASE_URL ?>/admin/users/<?= (int)($req['user_id'] ?? 0) ?>/edit"><?= htmlspecialchars($users_map[(int)($req['user_id'] ?? 0)] ?? ('User #' . (int)($req['user_id'] ?? 0))) ?></a></td>
                                <td data-label="<?= htmlspecialchars(__('type')) ?>"><span class="badge badge--pending"><?= htmlspecialchars((string) ($req['type'] ?? '—')) ?></span></td>
                                <td data-label="<?= htmlspecialchars(__('from')) ?>"><?= htmlspecialchars((string) ($req['start_date'] ?? '—')) ?></td>
                                <td data-label="<?= htmlspecialchars(__('to')) ?>"><?= htmlspecialchars((string) ($req['end_date'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (admin_widget_on('pending_swaps', $enabled_widgets) && $feat('swaps') && $can('swaps.view')): ?>
    <div class="card">
        <div class="card-header">
            <span><?= __('pending_swaps') ?></span>
            <a href="<?= route_url('admin.swap_requests') ?>" class="card-header-link"><?= __('view_all') ?></a>
        </div>
        <?php if (empty($pending_swaps)): ?>
            <div class="empty-state"><?= __('no_pending_swap') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" data-mob-stack>
                    <thead>
                        <tr><th>#</th><th><?= __('requester') ?></th><th><?= __('target') ?></th><th><?= __('shifts') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_swaps as $swap): ?>
                            <tr>
                                <td data-label="#"><?= (int) $swap['id'] ?></td>
                                <td data-label="<?= htmlspecialchars(__('requester')) ?>"><a href="<?= $BASE_URL ?>/admin/users/<?= (int)($swap['requester_id'] ?? 0) ?>/edit"><?= htmlspecialchars($users_map[(int)($swap['requester_id'] ?? 0)] ?? ('User #' . (int)($swap['requester_id'] ?? 0))) ?></a></td>
                                <td data-label="<?= htmlspecialchars(__('target')) ?>"><a href="<?= $BASE_URL ?>/admin/users/<?= (int)($swap['target_user_id'] ?? 0) ?>/edit"><?= htmlspecialchars($users_map[(int)($swap['target_user_id'] ?? 0)] ?? ('User #' . (int)($swap['target_user_id'] ?? 0))) ?></a></td>
                                <td data-label="<?= htmlspecialchars(__('shifts')) ?>">#<?= (int) ($swap['requester_shift_id'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<?php if (admin_widget_on('timeclocks_today', $enabled_widgets) && $feat('timeclock') && $can('timeclock.view')): ?>
<!-- Pointages actifs en ce moment -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('widget_timeclocks_today') ?></span>
        <a href="<?= route_url('admin.timeclocks') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($active_clocks_now)): ?>
        <div class="empty-state"><?= __('no_active_timeclock') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <th><?= __('user') ?></th>
                        <th><?= __('store') ?></th>
                        <th><?= __('clock_in') ?></th>
                        <th><?= __('duration') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_clocks_now as $tc): ?>
                        <?php
                        $clockInRaw = $tc['clock_in_time'] ?? '';
                        $clockInFmt = strlen($clockInRaw) >= 16 ? substr($clockInRaw, 11, 5) : $clockInRaw;
                        $durationMin = 0;
                        if ($clockInRaw !== '') {
                            try {
                                $dtIn = new DateTimeImmutable($clockInRaw);
                                $durationMin = (int) floor((time() - $dtIn->getTimestamp()) / 60);
                            } catch (\Exception) {}
                        }
                        $dh = intdiv($durationMin, 60);
                        $dm = $durationMin % 60;
                        ?>
                        <tr>
                            <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars($tc['user_name']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars($tc['store_name']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('clock_in')) ?>" class="td-mono"><?= htmlspecialchars($clockInFmt) ?></td>
                            <td data-label="<?= htmlspecialchars(__('duration')) ?>" class="td-nowrap"><?= $dh ?>h<?= str_pad((string)$dm, 2, '0', STR_PAD_LEFT) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
