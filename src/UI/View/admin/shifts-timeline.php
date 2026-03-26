<?php
/** @var \DateTimeImmutable[] $days */
/** @var string               $period_mode        'week' | '3days' */
/** @var array                $shifts_by_date_user date → uid → shift[] */
/** @var int[]                $all_user_ids */
/** @var array                $users_map           id → nom */
/** @var array                $user_color_map      id → couleur hex|null */
/** @var array                $types_map           id → shift_type */
/** @var int                  $my_user_id          admin courant */
/** @var string               $today */
/** @var string               $prev_start */
/** @var string               $next_start */
/** @var array                $rates_map           uid → type_id → rate */
/** @var array                $currency_map        uid → currency */
/** @var int                  $filter_store_id */
/** @var array                $stores_map          id → nom */
/** @var array                $available_stores    [{id, name, ...}] */
/** @var array                $store_settings      {min_staff_per_day, min_shift_minutes, max_shift_minutes} */

$_ratesMap      = $rates_map     ?? [];
$_currencyMap   = $currency_map  ?? [];
$_minStaffDay   = (int) ($store_settings['min_staff_per_day']    ?? 0);
$_minShiftMin   = (int) ($store_settings['min_shift_minutes']    ?? 0);
$_maxShiftMin   = (int) ($store_settings['max_shift_minutes']    ?? 0);
$_lowStaffStart = (int) ($store_settings['low_staff_hour_start'] ?? -1);
$_lowStaffEnd   = (int) ($store_settings['low_staff_hour_end']   ?? -1);

// ── Helpers timeline ─────────────────────────────────────────────────────────
$T_START = 6 * 60;   // 06:00
$T_TOTAL = 24 * 60;  // 1440 min

function atMin(string $t): int
{
    $p = explode(':', substr($t, 0, 5));
    return (int) ($p[0] ?? 0) * 60 + (int) ($p[1] ?? 0);
}
function atFmt(int $min): string
{
    $m = $min % 1440;
    return str_pad((string) intdiv($m, 60), 2, '0', STR_PAD_LEFT)
        . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
}

function atLanes(array $rawShifts, int $tStart): array
{
    $lanes = []; $laneEnds = [];
    foreach ($rawShifts as $sh) {
        $sm = atMin($sh['start_time'] ?? '00:00');
        $em = atMin($sh['end_time']   ?? '00:00');
        if (!empty($sh['cross_midnight']) || $em <= $sm) $em += 1440;
        if ($sm < $tStart) { $sm += 1440; $em += 1440; }
        $sh['_sm'] = $sm; $sh['_em'] = $em;
        $placed = false;
        foreach ($laneEnds as $li => $lEnd) {
            if ($lEnd <= $sm) { $lanes[$li][] = $sh; $laneEnds[$li] = $em; $placed = true; break; }
        }
        if (!$placed) { $lanes[] = [$sh]; $laneEnds[] = $em; }
    }
    return $lanes;
}

function atPayBreakdown(
    string $startTime, string $endTime, int $pauseMin, bool $crossMidnight,
    int $uid, int $storeId, array $typesMap, array $ratesMap, string $currency
): array {
    $sm = atMin($startTime); $em = atMin($endTime);
    if ($crossMidnight || $em <= $sm) $em += 1440;
    $grossMin = $em - $sm;
    if ($pauseMin > 0 && $grossMin > $pauseMin) {
        $mid = $sm + intdiv($grossMin, 2);
        $ps  = $mid - intdiv($pauseMin, 2);
        $segments = [[$sm, $ps], [$ps + $pauseMin, $em]];
    } else { $segments = [[$sm, $em]]; }
    $netMin = array_sum(array_map(fn($s) => $s[1] - $s[0], $segments));
    $storeTypes = array_filter($typesMap, fn($t) => (int) ($t['store_id'] ?? 0) === $storeId);
    $minByType  = [];
    foreach ($storeTypes as $tid => $type) {
        $ts = atMin($type['start_time']); $te = atMin($type['end_time']);
        if ($te <= $ts) $te += 1440;
        $overlap = 0;
        foreach ($segments as [$ss, $se]) {
            foreach ([-1440, 0, 1440] as $offset) {
                $ov = min($se, $te + $offset) - max($ss, $ts + $offset);
                if ($ov > 0) $overlap += $ov;
            }
        }
        if ($overlap > 0) $minByType[$tid] = $overlap;
    }
    if (empty($minByType)) $netMin = max(0, $grossMin - $pauseMin);
    $totalPay = 0.0; $hasRate = false; $items = [];
    foreach ($minByType as $tid => $minutes) {
        $type = $typesMap[$tid] ?? [];
        $rate = $ratesMap[$uid][$tid] ?? (float) ($type['hourly_rate'] ?? 0);
        $pay  = ($minutes / 60) * $rate;
        $totalPay += $pay;
        if ($rate > 0) $hasRate = true;
        $items[] = [
            'type_name' => $type['name'] ?? '?', 'minutes' => $minutes,
            'rate' => $rate, 'rate_fmt' => $rate > 0 ? format_currency($rate, $currency) . '/h' : '',
            'pay_fmt' => $rate > 0 ? format_currency($pay, $currency) : '', 'has_rate' => $rate > 0,
        ];
    }
    return ['total' => $totalPay, 'has_rate' => $hasRate, 'net_minutes' => $netMin, 'items' => $items];
}

// En-têtes heures
$headerHours = [];
for ($h = 6; $h < 30; $h++) $headerHours[] = $h % 24;

// Couleurs employés
$_palette = ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#f97316','#06b6d4','#ec4899','#84cc16'];
$userColorMap = [];
foreach ($all_user_ids as $i => $uid) {
    $userColorMap[$uid] = $user_color_map[$uid] ?? $_palette[$i % count($_palette)];
}

// Mapping user → store (tous dans le même store dans cette vue)
$_userStoreMap = [];
foreach ($all_user_ids as $uid) {
    $_userStoreMap[$uid] = $filter_store_id;
}

$frDaysFull = [
    'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'
];

$firstDay   = $days[0];
$lastDay    = $days[count($days) - 1];
$periodLabel = $period_mode === 'week'
    ? __('period_week_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')])
    : __('period_3days_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')]);
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'created' => __('shift_created'), 'updated' => __('shift_updated'), 'deleted' => __('shift_deleted'),
            default   => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('planning') ?> <span class="tl-subtitle">— <?= __('timeline') ?></span></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shifts<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?>" class="btn btn--ghost btn--sm">☰ <?= __('list_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/calendar<?= $filter_store_id ? '?store_id=' . $filter_store_id : '' ?>" class="btn btn--ghost btn--sm">📅 <?= __('calendar_view') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/import" class="btn btn--ghost btn--sm">↑ <?= __('import_excel') ?></a>
        <?php
        $printHref = ($BASE_URL ?? '') . '/admin/shifts/timeline/print'
            . '?store_id=' . $filter_store_id
            . '&start=' . $days[0]->format('Y-m-d')
            . '&view=' . $period_mode;
        ?>
        <a href="<?= htmlspecialchars($printHref) ?>" target="_blank" class="btn btn--ghost btn--sm">🖨 <?= __('print_timeline') ?></a>
        <a href="<?= $BASE_URL ?>/admin/shifts/create" class="btn btn--primary btn--sm">+ <?= __('new_shift') ?></a>
    </div>
</div>

<!-- Barre de contrôle ───────────────────────────────────────────────────────── -->
<div class="card card--mb">
    <div class="card-body filter-bar">

        <!-- Sélecteur de store -->
        <?php if (count($available_stores) > 1): ?>
        <form method="GET" action="" class="form-contents">
            <input type="hidden" name="view"  value="<?= htmlspecialchars($period_mode) ?>">
            <input type="hidden" name="start" value="<?= htmlspecialchars($days[0]->format('Y-m-d')) ?>">
            <select name="store_id" class="form-control filter-bar__select" onchange="this.form.submit()">
                <?php foreach ($available_stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $filter_store_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
            <span class="filter-bar__store-name"><?= htmlspecialchars($stores_map[$filter_store_id] ?? '') ?></span>
        <?php endif; ?>

        <div class="filter-bar__nav">
            <a href="?store_id=<?= $filter_store_id ?>&start=<?= $prev_start ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm">←</a>
            <strong class="filter-bar__period-label"><?= htmlspecialchars($periodLabel) ?></strong>
            <a href="?store_id=<?= $filter_store_id ?>&start=<?= $next_start ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm">→</a>
        </div>

        <div class="filter-bar__modes">
            <a href="?store_id=<?= $filter_store_id ?>&start=<?= $days[0]->format('Y-m-d') ?>&view=3days"
               class="btn btn--sm <?= $period_mode === '3days' ? 'btn--primary' : 'btn--ghost' ?>"><?= __('3days') ?></a>
            <a href="?store_id=<?= $filter_store_id ?>&start=<?= $days[0]->format('Y-m-d') ?>&view=week"
               class="btn btn--sm <?= $period_mode === 'week'  ? 'btn--primary' : 'btn--ghost' ?>"><?= __('week') ?></a>
        </div>

        <a href="?store_id=<?= $filter_store_id ?>&start=<?= $today ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm" title="<?= __('today') ?>"><?= __('today') ?></a>
    </div>
</div>

<?php if (empty($all_user_ids)): ?>
    <div class="card"><div class="empty-state"><?= __('no_store_found') ?></div></div>
<?php else: ?>

<!-- Jours ────────────────────────────────────────────────────────────────────── -->
<?php foreach ($days as $day): ?>
    <?php
    $dateStr  = $day->format('Y-m-d');
    $isToday  = $dateStr === $today;
    $dayName  = __(strtolower($day->format('l')));

    $dayShifts = [];
    foreach ($all_user_ids as $uid) {
        foreach ($shifts_by_date_user[$dateStr][$uid] ?? [] as $s) {
            $tid = (int) ($s['shift_type_id'] ?? 0);
            $dayShifts[] = array_merge($s, [
                '_uid'       => $uid,
                '_name'      => $users_map[$uid] ?? ('#' . $uid),
                '_is_me'     => $uid === $my_user_id,
                '_color'     => $userColorMap[$uid] ?? '#6366f1',
                '_type_name' => $types_map[$tid]['name'] ?? 'Shift',
            ]);
        }
    }

    usort($dayShifts, function ($a, $b) use ($T_START) {
        $sm = fn($s) => (($v = atMin($s['start_time'] ?? '00:00')) < $T_START) ? $v + 1440 : $v;
        return $sm($a) - $sm($b);
    });

    $lanes      = atLanes($dayShifts, $T_START);
    $shiftCount = count($dayShifts);
    // ── Diagnostics du jour ────────────────────────────────────────────────
    $_diagShifts = [];
    foreach ($dayShifts as $_sh) {
        $_sm = atMin($_sh['start_time'] ?? '00:00');
        $_em = atMin($_sh['end_time']   ?? '00:00');
        if (!empty($_sh['cross_midnight']) || $_em <= $_sm) $_em += 1440;
        if ($_sm < $T_START) { $_sm += 1440; $_em += 1440; }
        $_diagShifts[] = array_merge($_sh, ['_sm' => $_sm, '_em' => $_em]);
    }

    $_staffCount  = count(array_unique(array_column($_diagShifts, '_uid')));

    // Staffing par heure (tranches 0h–23h)
    $_hourlyStaff = array_fill(0, 24, 0);
    foreach ($_diagShifts as $_sh) {
        for ($_h = 0; $_h < 24; $_h++) {
            if ($_sh['_sm'] < ($_h + 1) * 60 && $_sh['_em'] > $_h * 60) {
                $_hourlyStaff[$_h]++;
            }
        }
    }
    $_peakConcurrent = max(array_merge([0], $_hourlyStaff));

    $_conflicts   = [];
    $_shortShifts = [];
    $_longShifts  = [];

    $_byUser = [];
    foreach ($_diagShifts as $_sh) $_byUser[$_sh['_uid']][] = $_sh;
    foreach ($_byUser as $_uid => $_ushifts) {
        usort($_ushifts, fn($a, $b) => $a['_sm'] - $b['_sm']);
        for ($_i = 1; $_i < count($_ushifts); $_i++) {
            if ($_ushifts[$_i]['_sm'] < $_ushifts[$_i - 1]['_em']) {
                $_conflicts[] = ($users_map[$_uid] ?? ('#' . $_uid))
                    . ' : ' . atFmt($_ushifts[$_i - 1]['_sm']) . '–' . atFmt($_ushifts[$_i - 1]['_em'])
                    . ' / ' . atFmt($_ushifts[$_i]['_sm']) . '–' . atFmt($_ushifts[$_i]['_em']);
            }
        }
    }

    foreach ($_diagShifts as $_sh) {
        $_dur = $_sh['_em'] - $_sh['_sm'];
        if ($_minShiftMin > 0 && $_dur < $_minShiftMin) {
            $_shortShifts[] = $_sh['_name'] . ' ' . atFmt($_sh['_sm']) . '–' . atFmt($_sh['_em'])
                . ' (' . intdiv($_dur, 60) . 'h' . str_pad($_dur % 60, 2, '0', STR_PAD_LEFT) . ')';
        }
        if ($_maxShiftMin > 0 && $_dur > $_maxShiftMin) {
            $_longShifts[] = $_sh['_name'] . ' ' . atFmt($_sh['_sm']) . '–' . atFmt($_sh['_em'])
                . ' (' . intdiv($_dur, 60) . 'h' . str_pad($_dur % 60, 2, '0', STR_PAD_LEFT) . ')';
        }
    }

    // Plages horaires sous-effectif (heure couverte par ≥1 employé mais < minimum requis,
    // hors plage à effectif réduit configurée)
    $_lowExempt = function (int $h) use ($_lowStaffStart, $_lowStaffEnd): bool {
        if ($_lowStaffStart < 0 || $_lowStaffEnd < 0) return false;
        if ($_lowStaffStart < $_lowStaffEnd) return $h >= $_lowStaffStart && $h < $_lowStaffEnd;
        return $h >= $_lowStaffStart || $h < $_lowStaffEnd; // plage traversant minuit
    };
    $_understaffedRanges = [];
    if ($_minStaffDay > 0) {
        $_inRange = false; $_rStart = 0; $_rMin = 0;
        for ($_h = 0; $_h < 24; $_h++) {
            $_cnt = $_hourlyStaff[$_h];
            if ($_cnt > 0 && $_cnt < $_minStaffDay && !$_lowExempt($_h)) {
                if (!$_inRange) { $_inRange = true; $_rStart = $_h; $_rMin = $_cnt; }
                else { $_rMin = min($_rMin, $_cnt); }
            } else {
                if ($_inRange) {
                    $_understaffedRanges[] = sprintf('%02d:00–%02d:00 (%d/%d)', $_rStart, $_h, $_rMin, $_minStaffDay);
                    $_inRange = false;
                }
            }
        }
        if ($_inRange) {
            $_understaffedRanges[] = sprintf('%02d:00–24:00 (%d/%d)', $_rStart, $_rMin, $_minStaffDay);
        }
    }
    $_understaffed = !empty($_understaffedRanges);
    $_hasIssues    = !empty($_conflicts) || !empty($_shortShifts) || !empty($_longShifts) || $_understaffed;
    $_issueCount   = count($_conflicts) + count($_shortShifts) + count($_longShifts) + ($_understaffed ? 1 : 0);
    $_diagJson     = htmlspecialchars(json_encode([
        'staff'               => $_staffCount,
        'peak'                => $_peakConcurrent,
        'min_staff'           => $_minStaffDay,
        'understaffed'        => $_understaffed,
        'understaffed_ranges' => $_understaffedRanges,
        'conflicts'           => $_conflicts,
        'short'               => $_shortShifts,
        'long'                => $_longShifts,
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ?>
    <div class="card sd-day-card tl-day-card <?= $isToday ? 'tl-day-card--today' : '' ?>" data-date="<?= htmlspecialchars($dateStr) ?>">
        <div class="tl-scroll">
            <div class="tl-scroll-inner">

                <!-- En-tête du jour -->
                <div class="tl-day-header">
                    <div class="tl-day-col">
                        <span class="tl-day-name--<?= $isToday ? 'today' : 'normal' ?>"><?= $dayName ?></span>
                        <span class="tl-day-sub"><?= $day->format('d M') ?></span>
                        <?php if ($isToday): ?><span class="badge badge--active badge--mt"><?= __('today') ?></span><?php endif; ?>
                    </div>
                    <span class="tl-day-stats">
                        <?= $shiftCount ?> shift<?= $shiftCount !== 1 ? 's' : '' ?>
                        <?php if (!empty($dayShifts)): ?>
                            · <?= $_staffCount ?> <?= __('staff_abbr') ?>
                            <?php if ($_minStaffDay > 0): ?>
                                · <?= __('peak_abbr') ?> <?= $_peakConcurrent ?>/<?= $_minStaffDay ?> <?= __('simult_abbr') ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($_hasIssues): ?>
                        <span class="tl-diag-badge sd-diag-badge"
                              data-diag="<?= $_diagJson ?>">
                            ⚠ <?= $_issueCount ?>
                        </span>
                    <?php elseif (!empty($dayShifts)): ?>
                        <span class="tl-diag-ok" title="<?= __('no_issue_detected') ?>">✓</span>
                    <?php endif; ?>
                    <!-- <a href="<?= $BASE_URL ?>/admin/shifts?store_id=<?= $filter_store_id ?>" class="tl-day-link" title="<?= __('list_view') ?>"><?= __('list_link') ?></a> -->
                </div>

                <!-- Gantt -->
                <div class="tl-gantt-body">

                    <!-- Axe des heures -->
                    <div class="tl-hours-axis">
                        <?php foreach ($headerHours as $i => $h): ?>
                            <div class="tl-hours-cell">
                                <?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($lanes)): ?>
                        <div class="tl-empty-lane">
                            <span class="tl-empty-text">—</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lanes as $li => $laneShifts): ?>
                            <div class="sd-lane tl-lane <?= $li > 0 ? 'tl-lane--mt' : '' ?>">
                                <!-- Grille verticale -->
                                <?php for ($i = 0; $i <= 24; $i++): ?>
                                    <div class="tl-grid-line" style="left:<?= number_format($i/24*100, 5) ?>%;opacity:<?= $i % 6 === 0 ? '.5' : '.2' ?>"></div>
                                <?php endfor; ?>

                                <!-- Barres shifts -->
                                <?php foreach ($laneShifts as $sh): ?>
                                    <?php
                                    $left  = ($sh['_sm'] - $T_START) / $T_TOTAL * 100;
                                    $width = ($sh['_em'] - $sh['_sm']) / $T_TOTAL * 100;
                                    $left  = max(0, min(100, $left));
                                    $width = max(0.4, min(100 - $left, $width));

                                    $color  = $sh['_color'];
                                    $isMe   = $sh['_is_me'];
                                    $bg     = $isMe ? $color : $color . '70';
                                    $border = $isMe ? "border:2px solid {$color}" : "border:1px solid {$color}aa";

                                    $shUid      = $sh['_uid'];
                                    $shPause    = (int) ($sh['pause_minutes'] ?? 0);
                                    $shStoreId  = (int) ($sh['store_id'] ?? 0);
                                    $shCurrency = $_currencyMap[$shUid] ?? 'JPY';
                                    $shPay = atPayBreakdown(
                                        $sh['start_time'] ?? '00:00', $sh['end_time'] ?? '00:00',
                                        $shPause, !empty($sh['cross_midnight']),
                                        $shUid, $shStoreId, $types_map, $_ratesMap, $shCurrency
                                    );
                                    $shNetMin     = $shPay['net_minutes'];
                                    $shHoursLabel = intdiv($shNetMin, 60) . 'h' . str_pad($shNetMin % 60, 2, '0', STR_PAD_LEFT)
                                        . ($shPause > 0 ? ' (pause ' . $shPause . ' min)' : '');
                                    $shPayLabel   = $shPay['has_rate'] ? format_currency($shPay['total'], $shCurrency) : '';
                                    $shRateDetail = htmlspecialchars(json_encode($shPay['items'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);

                                    $tooltip = htmlspecialchars(
                                        $sh['_name'] . ' · ' . atFmt($sh['_sm']) . '–' . atFmt($sh['_em'])
                                        . ' (' . $sh['_type_name'] . ')'
                                        . "\n" . $shHoursLabel
                                        . ($shPayLabel ? "\n" . $shPayLabel : '')
                                    );
                                    ?>
                                    <div title="<?= $tooltip ?>"
                                         onclick="sdModalOpen(this)"
                                         data-id="<?= (int) ($sh['id'] ?? 0) ?>"
                                         data-date="<?= htmlspecialchars($sh['shift_date'] ?? '') ?>"
                                         data-start="<?= atFmt($sh['_sm']) ?>"
                                         data-end="<?= atFmt($sh['_em']) ?>"
                                         data-name="<?= htmlspecialchars($sh['_name']) ?>"
                                         data-type="<?= htmlspecialchars($sh['_type_name']) ?>"
                                         data-notes="<?= htmlspecialchars($sh['notes'] ?? '') ?>"
                                         data-color="<?= htmlspecialchars($color) ?>"
                                         data-hours="<?= htmlspecialchars($shHoursLabel) ?>"
                                         data-pay="<?= htmlspecialchars($shPayLabel) ?>"
                                         data-rate-detail="<?= $shRateDetail ?>"
                                         data-width-pct="<?= number_format($width, 5) ?>"
                                         data-draggable="1"
                                         class="tl-bar" style="left:<?= number_format($left, 5) ?>%;width:<?= number_format($width, 5) ?>%;background:<?= htmlspecialchars($bg) ?>;<?= $border ?>">
                                        <?php if ($width > 4): ?>
                                            <span class="tl-bar-label tl-bar-label--main">
                                                <?= atFmt($sh['_sm']) ?>–<?= atFmt($sh['_em']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($width > 9): ?>
                                            <span class="tl-bar-label tl-bar-label--sub">
                                                <?= htmlspecialchars($sh['_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Zone de création par drag -->
                    <div class="sd-create-zone">
                        <span class="sd-create-zone__hint">
                            <span class="sd-create-zone__plus">+</span> <?= __('zone_to_create') ?>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Légende employés -->
<div class="week-legend tl-legend">
    <?php foreach ($all_user_ids as $uid): ?>
        <?php $c = $userColorMap[$uid] ?? '#6366f1'; $n = $users_map[$uid] ?? ('#' . $uid); $isMe = $uid === $my_user_id; ?>
        <span class="week-legend-item">
            <span class="tl-legend-bar" style="background:<?= $isMe ? $c : $c . '70' ?>;border:<?= $isMe ? "2px solid {$c}" : "1px solid {$c}aa" ?>"></span>
            <span class="<?= $isMe ? 'tl-legend-name--me' : 'text-muted tl-legend-name' ?>"><?= htmlspecialchars($n) ?></span>
        </span>
    <?php endforeach; ?>
    <!-- <span class="text-muted ml-auto"><?= __('timeline_legend_hint') ?></span> -->
</div>

<?php endif; ?>

<!-- ── Modal détail shift ──────────────────────────────────────────────────── -->
<div id="sd-overlay" class="sd-overlay" onclick="sdModalClose()">
    <div id="sd-modal" class="sd-modal" onclick="event.stopPropagation()">

        <div id="sd-header" class="sd-modal-header">
            <div>
                <div class="sd-modal-title">
                    <span id="sd-dot" class="sd-dot"></span>
                    <strong id="sd-name" class="sd-modal-name"></strong>
                </div>
                <div id="sd-date" class="sd-modal-sub"></div>
            </div>
            <button onclick="sdModalClose()" class="sd-modal-close">×</button>
        </div>

        <div class="sd-modal-body">
            <div class="sd-modal-field">
                <span class="sd-modal-label"><?= __('schedule') ?></span>
                <strong id="sd-time"></strong>
            </div>
            <div class="sd-modal-field">
                <span class="sd-modal-label"><?= __('duration') ?></span>
                <span id="sd-hours" class="sd-modal-hours"></span>
            </div>
            <div class="sd-modal-field">
                <span class="sd-modal-label"><?= __('type') ?></span>
                <span id="sd-type"></span>
            </div>
            <div id="sd-rate-block" class="sd-rate-block">
                <div id="sd-rate-rows"></div>
                <div id="sd-pay-row" class="sd-pay-row">
                    <span class="sd-modal-label"><?= __('total_estimated') ?></span>
                    <strong id="sd-pay" class="sd-pay"></strong>
                </div>
                <div id="sd-no-rate" class="sd-no-rate"><?= __('no_rate_configured') ?></div>
            </div>
            <div id="sd-notes-row" class="sd-notes-row">
                <span class="sd-modal-label"><?= __('notes') ?></span>
                <span id="sd-notes" class="sd-notes"></span>
            </div>
        </div>

        <div class="sd-modal-footer">
            <a id="sd-edit-link" href="#" class="btn btn--primary btn--sm"><?= __('edit') ?></a>
            <form id="sd-delete-form" method="POST" action="#" class="form-inline"
                  onsubmit="return confirm('<?= __('confirm_delete_shift_permanently') ?>')">
                <button type="submit" class="btn btn--danger btn--sm"><?= __('delete') ?></button>
            </form>
            <button onclick="sdModalClose()" class="btn btn--ghost btn--sm ml-auto"><?= __('close') ?></button>
        </div>
    </div>
</div>

<!-- ── Modal création rapide ──────────────────────────────────────────────────── -->
<div id="qc-overlay" class="qc-overlay" onclick="if(event.target===this)sdQcClose()">
    <div class="qc-modal" onclick="event.stopPropagation()">
        <div class="qc-header">
            <strong class="qc-title"><?= __('quick_new_shift') ?></strong>
            <div class="qc-header-right">
                <span id="qc-date-label" class="qc-date-label"></span>
                <button onclick="sdQcClose()" class="qc-close">×</button>
            </div>
        </div>
        <form id="qc-form" class="qc-form">
            <input type="hidden" id="qc-date">
            <div class="qc-grid">
                <label class="qc-label">
                    <span class="text-hint"><?= __('start') ?></span>
                    <input type="time" id="qc-start" required class="qc-input">
                </label>
                <label class="qc-label">
                    <span class="text-hint"><?= __('end') ?></span>
                    <input type="time" id="qc-end" required class="qc-input">
                </label>
            </div>
            <label class="qc-label">
                <span class="text-hint"><?= __('employee') ?></span>
                <select id="qc-user" required class="qc-select"></select>
            </label>
            <label class="qc-label">
                <span class="text-hint"><?= __('type') ?> <em class="text-dim">(<?= __('optional') ?>)</em></span>
                <select id="qc-type" class="qc-select">
                    <option value=""><?= __('no_type') ?></option>
                </select>
            </label>
            <div class="qc-footer">
                <button type="button" onclick="sdQcClose()" class="btn btn--ghost btn--sm"><?= __('cancel') ?></button>
                <button type="submit" id="qc-submit" class="btn btn--primary btn--sm"><?= __('create') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
var I18N = <?= json_encode([
    'error_prefix'        => __('error_prefix'),
    'network_error'       => __('network_error'),
    'creating'            => __('creating'),
    'create'              => __('create'),
    'no_type'             => __('no_type'),
    'staff_planned_label' => __('staff_planned_label'),
    'peak_abbr'           => __('peak_abbr'),
    'simult_abbr'         => __('simult_abbr'),
    'understaffed_warn'   => __('understaffed_warn'),
    'alert_conflicts'     => __('alert_conflicts'),
    'alert_short_shifts'  => __('alert_short_shifts'),
    'alert_long_shifts'   => __('alert_long_shifts'),
], JSON_UNESCAPED_UNICODE) ?>;
// ── Tooltip diagnostics journée ──────────────────────────────────────────────
(function () {
    var tip = document.createElement('div');
    tip.id = 'sd-diag-tip';
    tip.className = 'tl-diag-tip';
    document.body.appendChild(tip);

    function renderDiag(d) {
        var html = '';
        var staffColor = d.understaffed ? '#fca5a5' : '#86efac';
        html += '<div class="tl-diag-hdr">'
            + '👥 ' + d.staff + I18N.staff_planned_label
            + (d.min_staff > 0
                ? ' · ' + I18N.peak_abbr + ' <span style="color:' + staffColor + '">' + d.peak + '</span>'
                  + '<span class="tl-diag-dim"> / min ' + d.min_staff + ' ' + I18N.simult_abbr + '</span>'
                : '')
            + (d.understaffed ? ' <span class="tl-diag-warn">— ' + I18N.understaffed_warn + '</span>' : '')
            + '</div>';
        if (d.understaffed_ranges && d.understaffed_ranges.length) {
            html += '<div class="tl-diag-section tl-diag-section--under">⚠ ' + I18N.understaffed_warn + ' (' + d.understaffed_ranges.length + ')</div>';
            d.understaffed_ranges.forEach(function (r) {
                html += '<div class="tl-diag-item">· ' + r + '</div>';
            });
        }
        if (d.conflicts && d.conflicts.length) {
            html += '<div class="tl-diag-section tl-diag-section--conflict">⚡ ' + I18N.alert_conflicts + ' (' + d.conflicts.length + ')</div>';
            d.conflicts.forEach(function (c) {
                html += '<div class="tl-diag-item">· ' + c + '</div>';
            });
        }
        if (d.short && d.short.length) {
            html += '<div class="tl-diag-section tl-diag-section--short">⏱ ' + I18N.alert_short_shifts + ' (' + d.short.length + ')</div>';
            d.short.forEach(function (s) {
                html += '<div class="tl-diag-item">· ' + s + '</div>';
            });
        }
        if (d.long && d.long.length) {
            html += '<div class="tl-diag-section tl-diag-section--long">⏰ ' + I18N.alert_long_shifts + ' (' + d.long.length + ')</div>';
            d.long.forEach(function (l) {
                html += '<div class="tl-diag-item">· ' + l + '</div>';
            });
        }
        return html;
    }

    document.addEventListener('mouseenter', function (e) {
        var badge = e.target.closest('.sd-diag-badge');
        if (!badge) return;
        var d;
        try { d = JSON.parse(badge.dataset.diag || '{}'); } catch (_) { return; }
        tip.innerHTML = renderDiag(d);
        var rect = badge.getBoundingClientRect();
        tip.classList.add('open');
        var tipW = 340, margin = 8;
        var left = Math.min(rect.left, window.innerWidth - tipW - margin);
        tip.style.left = Math.max(margin, left) + 'px';
        tip.style.top  = (rect.bottom + 6) + 'px';
    }, true);

    document.addEventListener('mouseleave', function (e) {
        if (e.target.closest('.sd-diag-badge')) tip.classList.remove('open');
    }, true);

    document.addEventListener('scroll', function () { tip.classList.remove('open'); }, true);
})();

// ── Modal détail ─────────────────────────────────────────────────────────────
function sdModalOpen(el) {
    var id    = el.dataset.id, name = el.dataset.name, date = el.dataset.date;
    var start = el.dataset.start, end = el.dataset.end, type = el.dataset.type;
    var notes = el.dataset.notes, color = el.dataset.color;
    var hours = el.dataset.hours, pay = el.dataset.pay;
    var rateDetail = [];
    try { rateDetail = JSON.parse(el.dataset.rateDetail || '[]'); } catch(e) {}

    document.getElementById('sd-dot').style.background  = color;
    document.getElementById('sd-name').textContent       = name;
    document.getElementById('sd-date').textContent       = date;
    document.getElementById('sd-time').textContent       = start + ' – ' + end;
    document.getElementById('sd-hours').textContent      = hours || '—';
    document.getElementById('sd-type').textContent       = type  || '—';

    var block = document.getElementById('sd-rate-block');
    var rows  = document.getElementById('sd-rate-rows');
    rows.innerHTML = '';
    var hasAnyRate = rateDetail.some(function(i){ return i.has_rate; });
    if (rateDetail.length > 0) {
        rateDetail.forEach(function(item) {
            var row = document.createElement('div');
            row.className = 'pay-row';
            var lbl = item.rate_fmt ? item.type_name + ' · ' + item.rate_fmt : item.type_name;
            var mins = Math.round(item.minutes), h = Math.floor(mins/60), m = mins%60;
            row.innerHTML = '<span class="pay-label">' + lbl + ' × ' + h + 'h' + String(m).padStart(2,'0') + '</span>'
                + '<span class="pay-amount">' + (item.pay_fmt || '—') + '</span>';
            rows.appendChild(row);
        });
    }
    var payRow = document.getElementById('sd-pay-row');
    var noRate = document.getElementById('sd-no-rate');
    if (hasAnyRate) {
        payRow.style.display = 'flex'; noRate.style.display = 'none';
        document.getElementById('sd-pay').textContent = pay || '—';
    } else { payRow.style.display = 'none'; noRate.style.display = 'block'; }
    block.style.display = 'flex'; block.style.flexDirection = 'column';

    document.getElementById('sd-edit-link').href =
        '<?= rtrim($BASE_URL, '/') ?>/admin/shifts/' + id + '/edit';
    document.getElementById('sd-delete-form').action =
        '<?= rtrim($BASE_URL, '/') ?>/admin/shifts/' + id + '/delete';

    var notesRow = document.getElementById('sd-notes-row');
    if (notes) { document.getElementById('sd-notes').textContent = notes; notesRow.style.display = 'flex'; }
    else { notesRow.style.display = 'none'; }

    document.getElementById('sd-overlay').classList.add('open');
}
function sdModalClose() { document.getElementById('sd-overlay').classList.remove('open'); }
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { sdModalClose(); sdQcClose(); } });

// ── Drag & Drop ──────────────────────────────────────────────────────────────
(function () {
    'use strict';
    var T_START = 360, T_TOTAL = 1440;
    var BASE    = '<?= rtrim($BASE_URL, '/') ?>';

    var SD_USERS = <?= json_encode(
        array_map(fn($uid) => [
            'id'       => $uid,
            'name'     => $users_map[$uid] ?? ('#' . $uid),
            'store_id' => $_userStoreMap[$uid] ?? 0,
        ], $all_user_ids),
        JSON_UNESCAPED_UNICODE
    ) ?>;
    var SD_TYPES = <?= json_encode(array_values($types_map), JSON_UNESCAPED_UNICODE) ?>;

    function minToStr(min) {
        var m = ((Math.round(min) % 1440) + 1440) % 1440;
        return String(Math.floor(m/60)).padStart(2,'0') + ':' + String(m%60).padStart(2,'0');
    }
    function snap15(min) { return Math.round(min / 15) * 15; }
    function xToMin(x, w) { return Math.max(0, Math.min(1, x/w)) * T_TOTAL + T_START; }

    var _drag = null, _blockNextClick = false;

    document.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        var bar = e.target.closest('[data-draggable="1"]');
        if (bar) {
            var lane = bar.closest('.sd-lane');
            if (!lane) return;
            var rect = lane.getBoundingClientRect();
            var sParts = (bar.dataset.start||'00:00').split(':');
            var eParts = (bar.dataset.end  ||'00:00').split(':');
            var sMin = parseInt(sParts[0])*60 + parseInt(sParts[1]);
            var eMin = parseInt(eParts[0])*60 + parseInt(eParts[1]);
            var dur  = eMin >= sMin ? eMin - sMin : eMin + 1440 - sMin;
            var normS = sMin < T_START ? sMin + 1440 : sMin;
            var clickMin = xToMin(e.clientX - rect.left, rect.width);
            _drag = { type:'move', bar:bar, lane:lane, rect:rect, startX:e.clientX, moved:false,
                id:bar.dataset.id, date:bar.dataset.date, color:bar.dataset.color||'#6366f1',
                widthPct:parseFloat(bar.dataset.widthPct||'10'), dur:dur, normS:normS,
                offsetMin: clickMin - normS, ghost:null, newStart:null, newEnd:null };
            e.preventDefault(); return;
        }
        var zone = e.target.closest('.sd-create-zone');
        if (zone) {
            var dayCard = zone.closest('.sd-day-card');
            var rect2 = zone.getBoundingClientRect();
            var sMin2 = snap15(xToMin(e.clientX - rect2.left, rect2.width));
            _drag = { type:'create', zone:zone, dayCard:dayCard, rect:rect2,
                startX:e.clientX, moved:false, startMin:sMin2, endMin:sMin2+60, ghost:null };
            e.preventDefault();
        }
    }, true);

    document.addEventListener('mousemove', function(e) {
        if (!_drag) return;
        if (!_drag.moved && Math.abs(e.clientX - _drag.startX) > 5) {
            _drag.moved = true;
            if (_drag.type === 'move') {
                _drag.bar.style.opacity = '0.4';
                var g = document.createElement('div');
                g.style.cssText = 'position:absolute;top:3px;height:26px;border-radius:5px;box-sizing:border-box;pointer-events:none;z-index:20;border:2px dashed rgba(255,255,255,.7);opacity:.82';
                g.style.background = _drag.color; g.style.width = _drag.widthPct + '%';
                _drag.lane.appendChild(g); _drag.ghost = g;
            } else {
                var g2 = document.createElement('div');
                g2.style.cssText = 'position:absolute;top:2px;bottom:2px;border-radius:5px;box-sizing:border-box;pointer-events:none;z-index:10;background:rgba(99,102,241,.22);border:2px dashed #6366f1';
                _drag.zone.appendChild(g2); _drag.ghost = g2;
            }
        }
        if (!_drag.moved) return;
        if (_drag.type === 'move') {
            var x = e.clientX - _drag.rect.left;
            var cMin = xToMin(x, _drag.rect.width);
            var newS = ((snap15(cMin - _drag.offsetMin) % 1440) + 1440) % 1440;
            _drag.newStart = newS; _drag.newEnd = (newS + _drag.dur) % 1440;
            var normNew = newS < T_START ? newS + 1440 : newS;
            var lPct = Math.max(0, Math.min(99, (normNew - T_START) / T_TOTAL * 100));
            if (_drag.ghost) _drag.ghost.style.left = lPct + '%';
        } else {
            var x3 = Math.max(0, e.clientX - _drag.rect.left);
            var cur = snap15(xToMin(x3, _drag.rect.width));
            _drag.endMin = cur > _drag.startMin + 14 ? cur : _drag.startMin + 30;
            var lP = Math.max(0, Math.min(99, (_drag.startMin - T_START) / T_TOTAL * 100));
            var wP = Math.max(0.5, Math.min(100-lP, (_drag.endMin - _drag.startMin) / T_TOTAL * 100));
            if (_drag.ghost) { _drag.ghost.style.left = lP + '%'; _drag.ghost.style.width = wP + '%'; }
        }
    });

    document.addEventListener('mouseup', function(e) {
        if (!_drag) return;
        var drag = _drag; _drag = null;
        if (drag.ghost) drag.ghost.remove();
        if (drag.type === 'move') {
            drag.bar.style.opacity = '1';
            if (!drag.moved) return;
            _blockNextClick = true;
            if (drag.newStart === null) return;
            fetch(BASE + '/admin/shifts/' + drag.id + '/move', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ shift_date:drag.date, start_time:minToStr(drag.newStart), end_time:minToStr(drag.newEnd) })
            }).then(function(r) {
                if (r.ok) { window.location.reload(); return; }
                r.json().then(function(d){ alert(I18N.error_prefix+(d.error||r.status)); });
            }).catch(function(){ alert(I18N.network_error); });
        } else {
            var date = drag.dayCard ? drag.dayCard.dataset.date : '';
            sdQcOpen(date, minToStr(drag.startMin % 1440), minToStr(drag.endMin % 1440));
        }
    });

    document.addEventListener('click', function(e) {
        if (_blockNextClick) { _blockNextClick = false; e.stopImmediatePropagation(); e.preventDefault(); }
    }, true);

    // ── Quick-create modal ───────────────────────────────────────────────────
    function sdQcOpen(date, startTime, endTime) {
        var userSel = document.getElementById('qc-user');
        userSel.innerHTML = '';
        SD_USERS.forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.id; opt.textContent = u.name; opt.dataset.storeId = u.store_id;
            userSel.appendChild(opt);
        });
        refreshTypes(userSel);
        userSel.onchange = function(){ refreshTypes(userSel); };
        document.getElementById('qc-date').value = date;
        document.getElementById('qc-date-label').textContent = date;
        document.getElementById('qc-start').value = startTime;
        document.getElementById('qc-end').value   = endTime;
        document.getElementById('qc-overlay').classList.add('open');
    }
    function refreshTypes(userSel) {
        var selOpt  = userSel.options[userSel.selectedIndex];
        var storeId = selOpt ? parseInt(selOpt.dataset.storeId||'0') : 0;
        var typeSel = document.getElementById('qc-type');
        typeSel.innerHTML = '<option value="">' + I18N.no_type + '</option>';
        SD_TYPES.forEach(function(t) {
            if (storeId > 0 && parseInt(t.store_id) !== storeId) return;
            var opt = document.createElement('option'); opt.value = t.id; opt.textContent = t.name;
            typeSel.appendChild(opt);
        });
    }
    window.sdQcClose = function() {
        document.getElementById('qc-overlay').classList.remove('open');
        document.getElementById('qc-user').onchange = null;
    };
    document.getElementById('qc-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var userSel = document.getElementById('qc-user');
        var selOpt  = userSel.options[userSel.selectedIndex];
        var storeId = selOpt ? parseInt(selOpt.dataset.storeId||'0') : 0;
        var typeId  = document.getElementById('qc-type').value;
        var body = { store_id:storeId, user_id:parseInt(userSel.value),
            shift_date:document.getElementById('qc-date').value,
            start_time:document.getElementById('qc-start').value,
            end_time:document.getElementById('qc-end').value };
        if (typeId) body.shift_type_id = parseInt(typeId);
        var btn = document.getElementById('qc-submit');
        btn.disabled = true; btn.textContent = I18N.creating;
        fetch(BASE + '/admin/shifts/quick', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
        }).then(function(r) {
            if (r.ok) { sdQcClose(); window.location.reload(); return; }
            r.json().then(function(d){ alert(I18N.error_prefix+(d.error||r.status)); });
            btn.disabled=false; btn.textContent=I18N.create;
        }).catch(function(){ alert(I18N.network_error); btn.disabled=false; btn.textContent=I18N.create; });
    });
})();
</script>
