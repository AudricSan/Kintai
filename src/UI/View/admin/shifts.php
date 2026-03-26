<?php
/** @var array  $shifts */
/** @var array  $users_map */
/** @var array  $stores_map */
/** @var array  $types_map */
/** @var array  $users_for_filter */
/** @var int    $filter_store_id */
/** @var int    $filter_user_id */
/** @var string $filter_month */
/** @var string $sort */
$sort            ??= 'date_asc';
$filter_month    ??= date('Y-m');
$filter_store_id ??= 0;
$filter_user_id  ??= 0;
$types_map       ??= [];
$users_for_filter ??= [];

// Paramètres actifs pour les URLs de tri
$activeFilters = array_filter([
    'month'    => $filter_month,
    'store_id' => $filter_store_id ?: null,
    'user_id'  => $filter_user_id  ?: null,
], fn($v) => $v !== null);

function shiftSortUrl(string $key, string $current, array $filters): string {
    $next   = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    $params = array_filter([...$filters, 'sort' => $next], fn($v) => $v !== null && $v !== 0 && $v !== '');
    return '?' . http_build_query($params);
}
function shiftSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return ' ⇅';
}

// Stats de la période
$totalShifts = count($shifts);
$totalMinutes = 0;
$totalNetMinutes = 0;
foreach ($shifts as $s) {
    $dur   = (int) ($s['duration_minutes'] ?? 0);
    $pause = (int) ($s['pause_minutes']    ?? 0);
    $totalMinutes    += $dur;
    $totalNetMinutes += max(0, $dur - $pause);
}
$fmtH = fn(int $m) => sprintf('%dh%02d', intdiv($m, 60), $m % 60);

// Label du mois affiché
try {
    $monthDt    = new \DateTime($filter_month . '-01');
    $monthLabel = $monthDt->format('F Y');
} catch (\Exception) {
    $monthLabel = $filter_month;
}

// Regroupement des shifts par jour pour le rendu
$shiftsByDate = [];
foreach ($shifts as $s) {
    $shiftsByDate[$s['shift_date'] ?? ''][] = $s;
}
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?php
        $count = (int) ($_GET['count'] ?? 0);
        echo match($flash) {
            'created'      => __('shift_created'),
            'updated'      => __('shift_updated'),
            'deleted'      => __('shift_deleted'),
            'bulk_deleted' => __('shifts_bulk_deleted', ['n' => $count]),
            'imported'     => __('shifts_imported', ['n' => $count]),
            default        => __('operation_success'),
        };
        ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('shifts') ?>
        <span class="page-count">(<?= $totalShifts ?>)</span>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shifts/timeline<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?>" class="btn btn--ghost btn--sm"><svg class="gantt-icon icon-inline" width="16" height="16" viewBox="0 0 24 24"><rect x="4" y="2" width="2" height="20" fill="#555"/><rect x="10" y="6" width="2" height="16" fill="#555"/><rect x="16" y="10" width="2" height="12" fill="#555"/></svg> <?= __('timeline_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/calendar<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?>" class="btn btn--ghost btn--sm">📅 <?= __('calendar_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/conflicts<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?><?= $filter_month ? ($filter_store_id ? '&' : '?') . 'month=' . $filter_month : '' ?>" class="btn btn--ghost btn--sm">⚡ <?= __('conflict_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/import" class="btn btn--secondary">↑ <?= __('import_excel') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/create" class="btn btn--primary">+ <?= __('new_shift') ?></a>
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="" class="shifts-filters">
        <div class="shifts-filters__row">

            <!-- Mois -->
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-month"><?= __('month') ?></label>
                <input type="month" id="sf-month" name="month"
                       value="<?= htmlspecialchars($filter_month) ?>"
                       class="form-control form-control--sm">
            </div>

            <!-- Store -->
            <?php if (count($stores_map) > 1): ?>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-store"><?= __('store') ?></label>
                <select id="sf-store" name="store_id" class="form-control form-control--sm">
                    <option value="0"><?= __('all_stores') ?></option>
                    <?php foreach ($stores_map as $sid => $sname): ?>
                        <option value="<?= $sid ?>" <?= $filter_store_id === $sid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sname) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Personnel -->
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-user"><?= __('user') ?></label>
                <select id="sf-user" name="user_id" class="form-control form-control--sm">
                    <option value="0"><?= __('all_staff') ?></option>
                    <?php foreach ($users_for_filter as $uid => $uname): ?>
                        <option value="<?= $uid ?>" <?= $filter_user_id === $uid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($uname) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tri (conservé entre soumissions) -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

            <div class="shifts-filters__actions">
                <button type="submit" class="btn btn--primary btn--sm"><?= __('apply') ?></button>
                <a href="<?= $BASE_URL ?>/admin/shifts" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </div>
    </form>

    <!-- Résumé de la période -->
    <?php if ($totalShifts > 0): ?>
    <div class="shifts-filters__summary">
        <span class="badge badge--info"><?= $totalShifts ?> <?= __('shifts') ?></span>
        <span class="badge badge--neutral"><?= __('total') ?> : <?= $fmtH($totalMinutes) ?></span>
        <span class="badge badge--success"><?= __('net_after_breaks') ?> : <?= $fmtH($totalNetMinutes) ?></span>
        <?php if (count($users_for_filter) > 0):
            $staffInPeriod = count(array_unique(array_column($shifts, 'user_id')));
        ?>
        <span class="badge badge--neutral"><?= $staffInPeriod ?> <?= __('staff_members') ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Formulaire de suppression en masse (HTML5 : les checkboxes référencent ce form via form="bulk-form") -->
<form id="bulk-form" method="POST" action="<?= $BASE_URL ?>/admin/shifts/bulk-delete">
    <input type="hidden" name="redirect_month" value="<?= htmlspecialchars($filter_month) ?>">
    <input type="hidden" name="redirect_store" value="<?= (int) $filter_store_id ?>">
</form>

<!-- Tableau -->
<div class="card">
    <?php if (empty($shifts)): ?>
        <div class="empty-state"><?= __('no_shift_found') ?></div>
    <?php else: ?>

        <!-- Barre d'actions en masse (masquée jusqu'à sélection) -->
        <div id="bulk-bar" class="bulk-bar is-hidden">
            <span id="bulk-count" class="bulk-bar__count">0 sélectionné(s)</span>
            <button type="submit" form="bulk-form"
                    class="btn btn--danger btn--sm"
                    onclick="return confirm('<?= __('confirm_bulk_delete') ?>')">
                🗑 <?= __('bulk_delete_selection') ?>
            </button>
            <button type="button" class="btn btn--ghost btn--sm" onclick="bulkSelectAll(false)">
                <?= __('deselect_all') ?>
            </button>
        </div>

        <div class="table-wrap">
            <table class="data-table data-table--shifts">
                <thead>
                    <tr>
                        <th class="col-check">
                            <input type="checkbox" id="bulk-select-all" title="Tout sélectionner/désélectionner"
                                   onchange="bulkSelectAll(this.checked)">
                        </th>
                        <th class="col-id">#</th>
                        <th class="col-date">
                            <a href="<?= shiftSortUrl('date', $sort, $activeFilters) ?>" class="link-sort">
                                <?= __('date') ?><span class="sort-icon"><?= shiftSortIcon('date', $sort) ?></span>
                            </a>
                        </th>
                        <?php if (count($stores_map) > 1): ?>
                        <th class="col-store"><?= __('store') ?></th>
                        <?php endif; ?>
                        <th class="col-staff">
                            <a href="<?= shiftSortUrl('staff', $sort, $activeFilters) ?>" class="link-sort">
                                <?= __('user') ?><span class="sort-icon"><?= shiftSortIcon('staff', $sort) ?></span>
                            </a>
                        </th>
                        <th class="col-type">
                            <a href="<?= shiftSortUrl('type', $sort, $activeFilters) ?>" class="link-sort">
                                <?= __('type') ?><span class="sort-icon"><?= shiftSortIcon('type', $sort) ?></span>
                            </a>
                        </th>
                        <th class="col-time"><?= __('start') ?></th>
                        <th class="col-time"><?= __('end') ?></th>
                        <th class="col-duration">
                            <a href="<?= shiftSortUrl('duration', $sort, $activeFilters) ?>" class="link-sort">
                                <?= __('duration') ?><span class="sort-icon"><?= shiftSortIcon('duration', $sort) ?></span>
                            </a>
                        </th>
                        <th class="col-pause"><?= __('pause') ?></th>
                        <th class="col-night" title="<?= __('cross_midnight') ?>">🌙</th>
                        <th class="col-actions"><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prevDate    = null;
                    $groupByDate = str_starts_with($sort, 'date') && count($shiftsByDate) > 1;
                    foreach ($shifts as $shift):
                        $shiftDate  = $shift['shift_date'] ?? '';
                        $typeId     = (int) ($shift['shift_type_id'] ?? 0);
                        $typeData   = $types_map[$typeId] ?? null;
                        $typeName   = $typeData['name']  ?? '—';
                        $typeColor  = $typeData['color'] ?? null;
                        $dur        = (int) ($shift['duration_minutes'] ?? 0);
                        $pause      = (int) ($shift['pause_minutes']    ?? 0);
                        $netMin     = max(0, $dur - $pause);
                        $newDay     = ($shiftDate !== $prevDate);
                        $prevDate   = $shiftDate;
                    ?>
                        <?php if ($groupByDate && $newDay): ?>
                        <tr class="tr-date-separator">
                            <td colspan="<?= count($stores_map) > 1 ? 12 : 11 ?>" class="td-date-group">
                                <?php
                                try {
                                    $d = new \DateTime($shiftDate);
                                    echo htmlspecialchars($d->format('l d F Y'));
                                } catch (\Exception) {
                                    echo htmlspecialchars($shiftDate);
                                }
                                $dayCount = count($shiftsByDate[$shiftDate] ?? []);
                                echo ' <span class="badge badge--neutral badge--xs">' . $dayCount . ' shift' . ($dayCount > 1 ? 's' : '') . '</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="col-check">
                                <input type="checkbox" name="ids[]"
                                       value="<?= (int) $shift['id'] ?>"
                                       form="bulk-form"
                                       class="bulk-cb"
                                       onchange="bulkUpdateBar()">
                            </td>
                            <td class="col-id td-muted"><?= (int) $shift['id'] ?></td>
                            <td class="col-date td-nowrap"><?= htmlspecialchars($shiftDate) ?></td>
                            <?php if (count($stores_map) > 1): ?>
                            <td class="col-store"><?= htmlspecialchars($stores_map[(int) ($shift['store_id'] ?? 0)] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="col-staff"><?= htmlspecialchars($users_map[(int) ($shift['user_id'] ?? 0)] ?? '—') ?></td>
                            <td class="col-type">
                                <?php if ($typeColor): ?>
                                    <span class="type-badge" data-color="<?= htmlspecialchars($typeColor) ?>">
                                        <?= htmlspecialchars($typeName) ?>
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars($typeName) ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-time td-nowrap"><?= htmlspecialchars($shift['start_time'] ?? '') ?></td>
                            <td class="col-time td-nowrap"><?= htmlspecialchars($shift['end_time'] ?? '') ?></td>
                            <td class="col-duration">
                                <?= $fmtH($dur) ?>
                                <?php if ($pause > 0): ?>
                                    <span class="td-muted td-sm"> (net <?= $fmtH($netMin) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-pause td-muted"><?= $pause > 0 ? $pause . ' min' : '—' ?></td>
                            <td class="col-night"><?= !empty($shift['cross_midnight']) ? '✓' : '' ?></td>
                            <td class="col-actions">
                                <div class="btn-group">
                                    <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $shift['id'] ?>/edit" class="btn btn--ghost btn--sm"><?= __('edit') ?></a>
                                    <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int) $shift['id'] ?>/delete" class="form-inline">
                                        <button type="submit" class="btn btn--danger btn--sm"
                                                onclick="return confirm('<?= __('confirm_delete_shift') ?>')"><?= __('delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Couleurs dynamiques des badges de type via CSS custom properties
document.querySelectorAll('.type-badge[data-color]').forEach(function (el) {
    var c = el.dataset.color;
    if (!c) return;
    el.style.setProperty('--type-bg',     c + '20');
    el.style.setProperty('--type-fg',     c);
    el.style.setProperty('--type-border', c + '40');
});

var BULK_MAX = 100;

function bulkSelectAll(checked) {
    var all = document.querySelectorAll('.bulk-cb');
    var selected = 0;
    all.forEach(function(cb) {
        if (checked && selected >= BULK_MAX) {
            cb.checked = false;
        } else {
            cb.checked = checked;
            if (checked) selected++;
        }
    });
    if (checked && selected >= BULK_MAX) {
        alert('<?= __('bulk_max_warning', ['n' => 100]) ?>');
    }
    bulkUpdateBar();
}

function bulkUpdateBar() {
    var checked = document.querySelectorAll('.bulk-cb:checked');
    var bar     = document.getElementById('bulk-bar');
    var count   = document.getElementById('bulk-count');
    var all     = document.getElementById('bulk-select-all');
    var total   = document.querySelectorAll('.bulk-cb').length;
    if (checked.length > 0) {
        bar.classList.remove('is-hidden');
        count.textContent = checked.length + ' <?= __('selected') ?>' + (checked.length >= BULK_MAX ? ' (max ' + BULK_MAX + ')' : '');
    } else {
        bar.classList.add('is-hidden');
    }
    all.indeterminate = checked.length > 0 && checked.length < total;
    all.checked       = checked.length > 0 && checked.length === total;
}

// Empêche de cocher manuellement plus de BULK_MAX cases
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('bulk-cb')) return;
    if (e.target.checked && document.querySelectorAll('.bulk-cb:checked').length > BULK_MAX) {
        e.target.checked = false;
        alert('<?= __('bulk_max_warning', ['n' => 100]) ?>');
    }
    bulkUpdateBar();
});
</script>
