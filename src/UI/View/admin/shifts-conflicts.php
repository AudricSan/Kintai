<?php
/** @var array  $conflicts        [{user_id, shift_a, shift_b, overlap_minutes}] */
/** @var array  $too_short        [shift] — shifts dont duration_minutes < min_shift_minutes du store */
/** @var array  $stores_min_map   store_id → min_shift_minutes */
/** @var array  $users_map        id → nom */
/** @var array  $stores_map       id → nom */
/** @var array  $types_map        id → shift_type */
/** @var int    $filter_store_id */
/** @var string $filter_month */

$successMsg = '';
$successType = $_GET['success'] ?? '';
if ($successType === 'conflict_resolved') {
    $successMsg = __('conflict_resolved_ok');
} elseif ($successType === 'conflicts_resolved') {
    $n = (int) ($_GET['count'] ?? 0);
    $successMsg = __('conflicts_resolved_ok', ['n' => $n]);
}

$fmtShift = function (array $s, array $typesMap): string {
    $type  = $typesMap[(int) ($s['shift_type_id'] ?? 0)] ?? null;
    $color = $type['color'] ?? null;
    $name  = htmlspecialchars($type['name'] ?? '—');
    $badge = $color
        ? "<span class='type-badge' style='background:{$color}20;color:{$color};border:1px solid {$color}40;'>{$name}</span>"
        : $name;
    $cross = !empty($s['cross_midnight']) ? ' 🌙' : '';
    return htmlspecialchars($s['shift_date'] ?? '') . ' · '
         . htmlspecialchars($s['start_time'] ?? '') . '–'
         . htmlspecialchars($s['end_time'] ?? '') . $cross
         . ' ' . $badge;
};

// Grouper par user_id pour l'affichage
$byUser = [];
foreach ($conflicts as $c) {
    $byUser[$c['user_id']][] = $c;
}
?>

<div class="page-header">
    <h2 class="page-header__title">
        ⚡ <?= __('shift_conflicts_title') ?>
        <?php if (count($conflicts) > 0): ?>
            <span class="page-count badge badge--danger"><?= count($conflicts) ?></span>
        <?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shifts<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?><?= $filter_month ? ($filter_store_id ? '&' : '?') . 'month=' . $filter_month : '' ?>" class="btn btn--ghost btn--sm">← <?= __('shifts') ?></a>
    </div>
</div>

<!-- Filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="" class="shifts-filters">
        <div class="shifts-filters__row">

            <!-- Mois -->
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="cf-month"><?= __('month') ?></label>
                <input type="month" id="cf-month" name="month"
                       value="<?= htmlspecialchars($filter_month) ?>"
                       class="form-control form-control--sm">
            </div>

            <!-- Store -->
            <?php if (count($stores_map) > 1): ?>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="cf-store"><?= __('store') ?></label>
                <select id="cf-store" name="store_id" class="form-control form-control--sm">
                    <option value="0"><?= __('all_stores') ?></option>
                    <?php foreach ($stores_map as $sid => $sname): ?>
                        <option value="<?= $sid ?>" <?= $filter_store_id === $sid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sname) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="shifts-filters__actions">
                <button type="submit" class="btn btn--primary btn--sm"><?= __('apply') ?></button>
                <a href="<?= $BASE_URL ?>/admin/shifts/conflicts" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </div>
    </form>
</div>

<?php if ($successMsg !== ''): ?>
    <div class="alert alert--success mb-sm">✅ <?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<?php if (empty($conflicts)): ?>
    <div class="alert alert--success">
        ✅ <?= __('no_conflicts_found') ?>
    </div>
<?php else: ?>

    <?php
    // Compter les conflits du même mois (résolvables automatiquement)
    $sameMonthConflicts = array_filter($conflicts, function ($c) {
        $monthA = substr($c['shift_a']['shift_date'] ?? '', 0, 7);
        $monthB = substr($c['shift_b']['shift_date'] ?? '', 0, 7);
        return $monthA !== '' && $monthA === $monthB;
    });
    ?>

    <div class="alert alert--danger mb-sm">
        ⚡ <?= __('n_conflicts_detected', ['n' => count($conflicts)]) ?>
        <?php if ($filter_month !== '' && count($sameMonthConflicts) > 0): ?>
            <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/conflicts/resolve-newer"
                  style="display:inline;margin-left:1rem;"
                  onsubmit="return confirm('<?= htmlspecialchars(__('resolve_all_month') . ' (' . count($sameMonthConflicts) . ') ?') ?>")">
                <input type="hidden" name="bulk"     value="1">
                <input type="hidden" name="month"    value="<?= htmlspecialchars($filter_month) ?>">
                <input type="hidden" name="store_id" value="<?= $filter_store_id ?>">
                <button type="submit" class="btn btn--warning btn--sm">
                    ⚡ <?= __('resolve_all_month') ?> (<?= count($sameMonthConflicts) ?>)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php foreach ($byUser as $userId => $userConflicts): ?>
    <?php $userName = htmlspecialchars($users_map[$userId] ?? ('#' . $userId)); ?>

    <div class="conflict-group mb-sm">
        <div class="conflict-group__header">
            <span class="conflict-user-name">👤 <?= $userName ?></span>
            <span class="badge badge--danger"><?= count($userConflicts) ?></span>
        </div>

        <?php foreach ($userConflicts as $c): ?>
        <?php
            $sa = $c['shift_a'];
            $sb = $c['shift_b'];
            $overlapMin   = (int) $c['overlap_minutes'];
            $overlapH     = intdiv($overlapMin, 60);
            $overlapM     = $overlapMin % 60;
            $overlapLabel = $overlapH > 0 ? "{$overlapH}h{$overlapM}min" : "{$overlapM}min";
            $storeA = htmlspecialchars($stores_map[(int) ($sa['store_id'] ?? 0)] ?? '—');
            $storeB = htmlspecialchars($stores_map[(int) ($sb['store_id'] ?? 0)] ?? '—');

            // Résolution auto : même mois → on garde le plus récent (ID le plus élevé)
            $monthA   = substr($sa['shift_date'] ?? '', 0, 7);
            $monthB   = substr($sb['shift_date'] ?? '', 0, 7);
            $sameMth  = $monthA !== '' && $monthA === $monthB;
            $idNewer  = max((int) $sa['id'], (int) $sb['id']);
            $idOlder  = min((int) $sa['id'], (int) $sb['id']);
        ?>
        <div class="conflict-card">
            <div class="conflict-card__overlap">
                ⚡ <?= __('overlap_duration', ['duration' => $overlapLabel]) ?>
            </div>

            <div class="conflict-card__shifts">
                <!-- Shift A -->
                <div class="conflict-shift conflict-shift--a">
                    <div class="conflict-shift__label"><?= __('conflict_shift_a') ?> — #<?= (int) $sa['id'] ?></div>
                    <div class="conflict-shift__info">
                        <strong><?= htmlspecialchars($sa['shift_date'] ?? '') ?></strong>
                        <?= htmlspecialchars($sa['start_time'] ?? '') ?> – <?= htmlspecialchars($sa['end_time'] ?? '') ?>
                        <?= !empty($sa['cross_midnight']) ? '🌙' : '' ?>
                    </div>
                    <?php if (count($stores_map) > 1): ?>
                    <div class="conflict-shift__store td-muted"><?= $storeA ?></div>
                    <?php endif; ?>
                    <?php $typeA = $types_map[(int) ($sa['shift_type_id'] ?? 0)] ?? null; ?>
                    <?php if ($typeA): ?>
                    <div class="conflict-shift__type">
                        <span class="type-badge" data-color="<?= htmlspecialchars($typeA['color'] ?? '') ?>">
                            <?= htmlspecialchars($typeA['name'] ?? '') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="conflict-shift__actions">
                        <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sa['id'] ?>/edit"
                           class="btn btn--ghost btn--xs">✏️ <?= __('edit') ?></a>
                        <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sa['id'] ?>/delete"
                              class="form-inline">
                            <button type="submit" class="btn btn--danger btn--xs"
                                    onclick="return confirm('<?= __('confirm_delete_shift') ?>')">🗑</button>
                        </form>
                    </div>
                </div>

                <div class="conflict-card__vs">↔</div>

                <!-- Shift B -->
                <div class="conflict-shift conflict-shift--b">
                    <div class="conflict-shift__label"><?= __('conflict_shift_b') ?> — #<?= (int) $sb['id'] ?></div>
                    <div class="conflict-shift__info">
                        <strong><?= htmlspecialchars($sb['shift_date'] ?? '') ?></strong>
                        <?= htmlspecialchars($sb['start_time'] ?? '') ?> – <?= htmlspecialchars($sb['end_time'] ?? '') ?>
                        <?= !empty($sb['cross_midnight']) ? '🌙' : '' ?>
                    </div>
                    <?php if (count($stores_map) > 1): ?>
                    <div class="conflict-shift__store td-muted"><?= $storeB ?></div>
                    <?php endif; ?>
                    <?php $typeB = $types_map[(int) ($sb['shift_type_id'] ?? 0)] ?? null; ?>
                    <?php if ($typeB): ?>
                    <div class="conflict-shift__type">
                        <span class="type-badge" data-color="<?= htmlspecialchars($typeB['color'] ?? '') ?>">
                            <?= htmlspecialchars($typeB['name'] ?? '') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="conflict-shift__actions">
                        <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sb['id'] ?>/edit"
                           class="btn btn--ghost btn--xs">✏️ <?= __('edit') ?></a>
                        <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sb['id'] ?>/delete"
                              class="form-inline">
                            <button type="submit" class="btn btn--danger btn--xs"
                                    onclick="return confirm('<?= __('confirm_delete_shift') ?>')">🗑</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Suggestions de résolution -->
            <div class="conflict-card__suggest">
                <span class="text-sm-muted">💡 <?= __('conflict_suggestions') ?></span>
                <?php if ($sameMth): ?>
                <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/conflicts/resolve-newer"
                      style="display:inline;"
                      onsubmit="return confirm('<?= htmlspecialchars(__('keep_newer_shift') . ' #' . $idNewer . ' ?') ?>')">
                    <input type="hidden" name="id_keep"   value="<?= $idNewer ?>">
                    <input type="hidden" name="id_delete" value="<?= $idOlder ?>">
                    <input type="hidden" name="month"     value="<?= htmlspecialchars($monthA) ?>">
                    <input type="hidden" name="store_id"  value="<?= $filter_store_id ?>">
                    <button type="submit" class="btn btn--warning btn--xs">
                        ⚡ <?= __('keep_newer_shift') ?> (#<?= $idNewer ?>)
                    </button>
                </form>
                <?php endif; ?>
                <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sa['id'] ?>/edit" class="btn btn--ghost btn--xs">
                    <?= __('adjust_hours_shift_a') ?>
                </a>
                <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sb['id'] ?>/edit" class="btn btn--ghost btn--xs">
                    <?= __('adjust_hours_shift_b') ?>
                </a>
                <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $sb['id'] ?>/edit?reassign=1" class="btn btn--secondary btn--xs">
                    <?= __('reassign_shift_b') ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php if (!empty($too_short)): ?>
<div class="section-title mt-md mb-sm" style="display:flex;align-items:center;gap:.5rem;">
    <h3 style="margin:0">⏱ <?= __('too_short_shifts_title') ?></h3>
    <span class="badge badge--warning"><?= count($too_short) ?></span>
</div>

<div class="alert alert--warning mb-sm">
    ⚠️ <?= __('too_short_shifts_info', ['n' => count($too_short)]) ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('date') ?></th>
                    <th><?= __('employee') ?></th>
                    <th><?= __('start') ?> – <?= __('end') ?></th>
                    <th><?= __('duration') ?></th>
                    <th><?= __('minimum') ?></th>
                    <?php if (count($stores_map) > 1): ?><th><?= __('store') ?></th><?php endif; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($too_short as $s): ?>
            <?php
                $sid      = (int) ($s['store_id'] ?? 0);
                $dur      = (int) ($s['duration_minutes'] ?? 0);
                $min      = (int) ($stores_min_map[$sid] ?? 0);
                $durH     = intdiv($dur, 60);
                $durM     = $dur % 60;
                $minH     = intdiv($min, 60);
                $minM     = $min % 60;
                $durLabel = $durH > 0 ? "{$durH}h{$durM}min" : "{$durM}min";
                $minLabel = $minH > 0 ? "{$minH}h{$minM}min" : "{$minM}min";
                $userName = htmlspecialchars($users_map[(int) ($s['user_id'] ?? 0)] ?? ('#' . ($s['user_id'] ?? '?')));
                $typeObj  = $types_map[(int) ($s['shift_type_id'] ?? 0)] ?? null;
            ?>
            <tr>
                <td class="td-nowrap"><?= htmlspecialchars($s['shift_date'] ?? '') ?></td>
                <td><?= $userName ?></td>
                <td class="td-nowrap">
                    <?= htmlspecialchars($s['start_time'] ?? '') ?> – <?= htmlspecialchars($s['end_time'] ?? '') ?>
                    <?= !empty($s['cross_midnight']) ? '🌙' : '' ?>
                    <?php if ($typeObj): ?>
                        <span class="type-badge" data-color="<?= htmlspecialchars($typeObj['color'] ?? '') ?>">
                            <?= htmlspecialchars($typeObj['name'] ?? '') ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td class="td-nowrap"><span class="badge badge--danger"><?= $durLabel ?></span></td>
                <td class="td-nowrap td-muted"><?= $minLabel ?></td>
                <?php if (count($stores_map) > 1): ?>
                <td class="td-muted"><?= htmlspecialchars($stores_map[$sid] ?? '#' . $sid) ?></td>
                <?php endif; ?>
                <td class="td-nowrap">
                    <a href="<?= $BASE_URL ?>/admin/shifts/<?= (int) $s['id'] ?>/edit"
                       class="btn btn--ghost btn--xs">✏️ <?= __('edit') ?></a>
                    <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/<?= (int) $s['id'] ?>/delete"
                          class="form-inline">
                        <button type="submit" class="btn btn--danger btn--xs"
                                onclick="return confirm('<?= __('confirm_delete_shift') ?>')">🗑</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.type-badge[data-color]').forEach(function (el) {
    var c = el.dataset.color;
    if (!c) return;
    el.style.setProperty('--type-bg',     c + '20');
    el.style.setProperty('--type-fg',     c);
    el.style.setProperty('--type-border', c + '40');
});
</script>
