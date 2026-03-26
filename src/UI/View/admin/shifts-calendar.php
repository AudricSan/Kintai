<?php
/**
 * @var int    $year
 * @var int    $month
 * @var string $month_label
 * @var string $month_start   Y-m-d
 * @var string $month_end     Y-m-d
 * @var string $prev_month    Y-m
 * @var string $next_month    Y-m
 * @var string $today         Y-m-d
 * @var array  $shifts_by_date  'Y-m-d' → shift[]
 * @var array  $all_members   user[]
 * @var array  $members_map   uid → user
 * @var array  $users_colour  uid → '#hex'
 * @var int[]  $filter_uids   uids sélectionnés (vide = tous)
 * @var array  $types_map     id → shift_type
 * @var array  $stores_map    id → name
 * @var int    $filter_store_id
 */

// Construire la grille calendrier (semaine commence lundi)
$firstDay  = new \DateTimeImmutable($month_start);
$lastDay   = new \DateTimeImmutable($month_end);
$startDow  = (int) $firstDay->format('N'); // 1=Lun … 7=Dim
$gridStart = $firstDay->modify('-' . ($startDow - 1) . ' days');

$weeks = [];
$cursor = $gridStart;
while ($cursor <= $lastDay || count($weeks) === 0 || $cursor->format('N') != 1) {
    $week = [];
    for ($d = 0; $d < 7; $d++) {
        $week[] = $cursor;
        $cursor = $cursor->modify('+1 day');
    }
    $weeks[] = $week;
    if ($cursor > $lastDay && (int) $cursor->format('N') === 1) break;
}

// Paramètres URL pour la navigation (conserver les filtres)
function calUrl(string $base, string $month, array $filterUids, int $filterStore): string {
    $params = ['month' => $month];
    foreach ($filterUids as $uid) $params['u'][] = $uid;
    if ($filterStore > 0) $params['store_id'] = $filterStore;
    return $base . '/admin/shifts/calendar?' . http_build_query($params);
}
?>


<div class="page-header">
    <h2 class="page-header__title">📅 <?= __('planning') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="btn btn--ghost btn--sm"><svg class="gantt-icon icon-inline" width="16" height="16" viewBox="0 0 24 24"><rect x="4" y="2" width="2" height="20" fill="#555"/><rect x="10" y="6" width="2" height="16" fill="#555"/><rect x="16" y="10" width="2" height="12" fill="#555"/></svg> <?= __('timeline_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts" class="btn btn--ghost btn--sm">☰ <?= __('list_view') ?></a>
                <a href="<?= $BASE_URL ?>/admin/shifts/import" class="btn btn--ghost btn--sm">↑ <?= __('import_excel') ?></a>
<a href="<?= $BASE_URL ?>/admin/shifts/create" class="btn btn--primary btn--sm">+ <?= __('new_shift') ?></a>
    </div>
</div>

<div class="cal-layout">

    <!-- ── Sidebar filtre employés ─────────────────────────── -->
    <form method="GET" action="<?= $BASE_URL ?>/admin/shifts/calendar" class="cal-sidebar" id="calFilterForm">
        <input type="hidden" name="month" value="<?= htmlspecialchars($month_start ? substr($month_start, 0, 7) : date('Y-m')) ?>">
        <?php if ($filter_store_id > 0): ?>
            <input type="hidden" name="store_id" value="<?= $filter_store_id ?>">
        <?php endif; ?>

        <h4><?= __('staff') ?></h4>

        <?php if (empty($all_members)): ?>
            <p class="text-hint"><?= __('none') ?></p>
        <?php else: ?>
            <div class="cal-sidebar-actions">
                <button type="button" onclick="calSelectAll(true)" class="btn btn--ghost btn--sm"><?= __('all') ?></button>
                <button type="button" onclick="calSelectAll(false)" class="btn btn--ghost btn--sm"><?= __('none') ?></button>
            </div>
            <?php foreach ($all_members as $m): ?>
                <?php
                $uid   = (int) $m['id'];
                $col   = $users_colour[$uid] ?? '#6366f1';
                $label = $m['display_name'] ?? trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: ($m['email']??'#'.$uid);
                $checked = empty($filter_uids) || in_array($uid, $filter_uids, true);
                ?>
                <label class="cal-member-item" style="--c:<?= htmlspecialchars($col) ?>" title="<?= htmlspecialchars($label) ?>">
                    <input type="checkbox" name="u[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?> onchange="document.getElementById('calFilterForm').submit()">
                    <span class="cal-member-dot" style="background:<?= htmlspecialchars($col) ?>"></span>
                    <span class="cal-member-name"><?= htmlspecialchars($label) ?></span>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($stores_map) && count($stores_map) > 1): ?>
            <hr>
            <h4><?= __('store') ?></h4>
            <select name="store_id" class="form-control" onchange="this.form.submit()">
                <option value="0"><?= __('all_stores') ?></option>
                <?php foreach ($stores_map as $sid => $sname): ?>
                    <option value="<?= $sid ?>" <?= $filter_store_id === $sid ? 'selected' : '' ?>><?= htmlspecialchars($sname) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>

    <!-- ── Calendrier ──────────────────────────────────────── -->
    <div class="cal-main">

        <!-- Navigation mois -->
        <div class="cal-nav">
            <div class="cal-nav-btns">
                <a href="<?= calUrl($BASE_URL, $prev_month, $filter_uids, $filter_store_id) ?>" class="btn btn--ghost btn--sm">← <?= __('prev_week') ?></a>
            </div>
            <span class="cal-nav-title"><?= htmlspecialchars($month_label) ?></span>
            <div class="cal-nav-btns">
                <a href="<?= calUrl($BASE_URL, date('Y-m'), $filter_uids, $filter_store_id) ?>" class="btn btn--ghost btn--sm"><?= __('today') ?></a>
                <a href="<?= calUrl($BASE_URL, $next_month, $filter_uids, $filter_store_id) ?>" class="btn btn--ghost btn--sm"><?= __('next_week') ?> →</a>
            </div>
        </div>

        <!-- Grille -->
        <table class="cal-grid">
            <thead>
                <tr>
                    <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $dow): ?>
                        <th><?= __($dow) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weeks as $week): ?>
                    <tr>
                        <?php foreach ($week as $day): ?>
                            <?php
                            $dateStr     = $day->format('Y-m-d');
                            $isThisMonth = $day->format('Y-m') === substr($month_start, 0, 7);
                            $isToday     = $dateStr === $today;
                            $dayShifts   = $shifts_by_date[$dateStr] ?? [];
                            usort($dayShifts, fn($a, $b) => strcmp($a['start_time']??'', $b['start_time']??''));
                            $maxVisible  = 3;
                            $extra       = max(0, count($dayShifts) - $maxVisible);
                            $classes     = [];
                            if (!$isThisMonth) $classes[] = 'cal-other-month';
                            if ($isToday)      $classes[] = 'cal-today';
                            ?>
                            <td class="<?= implode(' ', $classes) ?>">
                                <div class="cal-day-num"><?= (int) $day->format('j') ?></div>
                                <?php foreach (array_slice($dayShifts, 0, $maxVisible) as $s): ?>
                                    <?php
                                    $uid   = (int) ($s['user_id'] ?? 0);
                                    $tid   = (int) ($s['shift_type_id'] ?? 0);
                                    $type  = $types_map[$tid] ?? null;
                                    $col   = $users_colour[$uid] ?? ($type['color'] ?? '#6366f1');
                                    $bg    = $col . '22';
                                    $uName = '';
                                    if (isset($members_map[$uid])) {
                                        $mu = $members_map[$uid];
                                        $uName = $mu['display_name'] ?? trim(($mu['first_name']??'').' '.($mu['last_name']??'')) ?: ($mu['email']??'');
                                    }
                                    $label = htmlspecialchars(substr($s['start_time']??'',0,5) . ' ' . ($uName ?: ($type['name'] ?? 'Shift')));
                                    $tipParts = [
                                        substr($s['start_time']??'',0,5) . '–' . substr($s['end_time']??'',0,5),
                                        $type['name'] ?? '',
                                        $uName,
                                        $stores_map[(int)($s['store_id']??0)] ?? '',
                                    ];
                                    $tip = htmlspecialchars(implode(' · ', array_filter($tipParts)));
                                    ?>
                                    <span class="cal-pill" style="--pill-color:<?= $col ?>;--pill-bg:<?= $bg ?>" title="<?= $tip ?>">
                                        <?= $label ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if ($extra > 0): ?>
                                    <a href="<?= $BASE_URL ?>/admin/shifts?store_id=<?= $filter_store_id ?>" class="cal-pill-more">+<?= $extra ?> <?= __('more') ?></a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calSelectAll(checked) {
    var form = document.getElementById('calFilterForm');
    document.querySelectorAll('#calFilterForm input[type=checkbox]').forEach(function(cb) {
        cb.checked = checked;
    });
    if (!checked) {
        // Sentinelle : u[]=0 indique "aucun sélectionné" (UID 0 inexistant)
        // Sans ça, un tableau vide serait interprété côté serveur comme "tous"
        var inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'u[]';
        inp.value = '0';
        form.appendChild(inp);
    }
    form.submit();
}
</script>
