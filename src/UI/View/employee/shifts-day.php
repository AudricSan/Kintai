<?php

/** @var \DateTimeImmutable[] $days               jours à afficher */
/** @var string               $period_mode        '3days' | 'week' */
/** @var array                $shifts_by_date_user date → uid → shift[] */
/** @var int[]                $all_user_ids        moi en premier */
/** @var array                $users_map           id → nom */
/** @var array                $user_color_map      id → couleur hex */
/** @var array                $types_map           id → shift_type */
/** @var int                  $my_user_id */
/** @var string               $today              Y-m-d */
/** @var string               $prev_start         Y-m-d */
/** @var string               $next_start         Y-m-d */
/** @var bool                 $can_manage         vrai si admin/manager (peut éditer/supprimer) */
/** @var array                $rates_map          uid → shift_type_id → hourly_rate */
/** @var array                $currency_map       uid → currency code */

$_canManage  = !empty($can_manage);
$_ratesMap   = $rates_map   ?? [];
$_currencyMap= $currency_map ?? [];

// Mapping user → store_id (depuis les shifts existants)
$_userStoreMap = [];
foreach ($shifts_by_date_user as $_d => $_dateShifts) {
    foreach ($_dateShifts as $_uid => $_shArr) {
        if (!isset($_userStoreMap[$_uid]) && !empty($_shArr)) {
            $_userStoreMap[$_uid] = (int) ($_shArr[0]['store_id'] ?? 0);
        }
    }
}

// ── Helpers timeline ────────────────────────────────────────────────────
$T_START = 6 * 60;   // 06:00 = 360 min depuis minuit
$T_TOTAL = 24 * 60;  // 1440 min

function sdMin(string $t): int
{
    $p = explode(':', substr($t, 0, 5));
    return (int)($p[0] ?? 0) * 60 + (int)($p[1] ?? 0);
}
function sdFmt(int $min): string
{
    $m = $min % 1440;
    return str_pad((string)intdiv($m, 60), 2, '0', STR_PAD_LEFT)
        . ':' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);
}

/**
 * Construit les lanes (greedy packing) pour un ensemble de shifts.
 * Chaque shift enrichi avec _sm, _em, _name, _is_me, _color, _type_name.
 */
function sdLanes(array $rawShifts, int $tStart): array
{
    $lanes    = [];
    $laneEnds = [];
    foreach ($rawShifts as $sh) {
        $sm = sdMin($sh['start_time'] ?? '00:00');
        $em = sdMin($sh['end_time']   ?? '00:00');
        if (!empty($sh['cross_midnight']) || $em <= $sm) $em += 1440;
        if ($sm < $tStart) {
            $sm += 1440;
            $em += 1440;
        }
        $sh['_sm'] = $sm;
        $sh['_em'] = $em;

        $placed = false;
        foreach ($laneEnds as $li => $lEnd) {
            if ($lEnd <= $sm) {
                $lanes[$li][]  = $sh;
                $laneEnds[$li] = $em;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $lanes[]    = [$sh];
            $laneEnds[] = $em;
        }
    }
    return $lanes;
}

/**
 * Calcule la décomposition du salaire en tenant compte des différents types de shift
 * présents dans le store et de leurs plages horaires.
 * La pause est toujours prise en deux moitiés égales autour du milieu du shift.
 *
 * @return array{total:float, has_rate:bool, net_minutes:int,
 *               items:list<array{type_name:string,minutes:int,rate:float,
 *                               rate_fmt:string,pay_fmt:string,has_rate:bool}>}
 */
function sdPayBreakdown(
    string $startTime,
    string $endTime,
    int    $pauseMin,
    bool   $crossMidnight,
    int    $uid,
    int    $storeId,
    array  $typesMap,
    array  $ratesMap,
    string $currency
): array {
    $sm = sdMin($startTime);
    $em = sdMin($endTime);
    if ($crossMidnight || $em <= $sm) $em += 1440;
    $grossMin = $em - $sm;

    // Segments de travail : pause au centre, coupée en deux
    if ($pauseMin > 0 && $grossMin > $pauseMin) {
        $mid        = $sm + intdiv($grossMin, 2);
        $pauseStart = $mid - intdiv($pauseMin, 2);
        $pauseEnd   = $pauseStart + $pauseMin;
        $segments   = [[$sm, $pauseStart], [$pauseEnd, $em]];
    } else {
        $segments = [[$sm, $em]];
    }
    $netMin = array_sum(array_map(fn($seg) => $seg[1] - $seg[0], $segments));

    // Types du store uniquement
    $storeTypes = array_filter($typesMap, fn($t) => (int)($t['store_id'] ?? 0) === $storeId);

    // Chevauchement (en minutes) par type_id
    $minByType = [];
    foreach ($storeTypes as $tid => $type) {
        $ts = sdMin($type['start_time']);
        $te = sdMin($type['end_time']);
        if ($te <= $ts) $te += 1440; // type cross-midnight

        $overlap = 0;
        foreach ($segments as [$ss, $se]) {
            // Essayer 3 fenêtres (±1440) pour couvrir tous les décalages jour/nuit
            foreach ([-1440, 0, 1440] as $offset) {
                $ov = min($se, $te + $offset) - max($ss, $ts + $offset);
                if ($ov > 0) $overlap += $ov;
            }
        }
        if ($overlap > 0) {
            $minByType[$tid] = $overlap;
        }
    }

    // Si aucun type ne couvre le shift (configuration incomplète), fallback sur net brut
    if (empty($minByType)) {
        $netMin = max(0, $grossMin - $pauseMin);
    }

    // Calcul du salaire par type
    $totalPay = 0.0;
    $hasRate  = false;
    $items    = [];
    foreach ($minByType as $tid => $minutes) {
        $type = $typesMap[$tid] ?? [];
        // Priorité : taux personnel → taux par défaut du type
        $rate = $ratesMap[$uid][$tid] ?? (float)($type['hourly_rate'] ?? 0);
        $pay  = ($minutes / 60) * $rate;
        $totalPay += $pay;
        if ($rate > 0) $hasRate = true;
        $items[] = [
            'type_name' => $type['name'] ?? '?',
            'minutes'   => $minutes,
            'rate'      => $rate,
            'rate_fmt'  => $rate > 0 ? format_currency($rate, $currency) . '/h' : '',
            'pay_fmt'   => $rate > 0 ? format_currency($pay,  $currency) : '',
            'has_rate'  => $rate > 0,
        ];
    }

    return [
        'total'       => $totalPay,
        'has_rate'    => $hasRate,
        'net_minutes' => $netMin,
        'items'       => $items,
    ];
}

// Heures en-tête : 06 → 05
$headerHours = [];
for ($h = 6; $h < 30; $h++) $headerHours[] = $h % 24;

// Couleurs des employés depuis la base (fallback palette si non défini)
$_fallbackPalette = ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#f97316','#06b6d4','#ec4899','#84cc16'];
$userColorMap = [];
foreach ($all_user_ids as $i => $uid) {
    $userColorMap[$uid] = $user_color_map[$uid] ?? $_fallbackPalette[$i % count($_fallbackPalette)];
}

$frDays = [
    'Monday' => 'Lun',
    'Tuesday' => 'Mar',
    'Wednesday' => 'Mer',
    'Thursday' => 'Jeu',
    'Friday' => 'Ven',
    'Saturday' => 'Sam',
    'Sunday' => 'Dim'
];
$frDaysFull = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];

// Label de la période pour le titre
$firstDay = $days[0];
$lastDay  = $days[count($days) - 1];
if ($period_mode === 'week') {
    $periodLabel = __('period_week_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')]);
} else {
    $periodLabel = __('period_3days_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')]);
}
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('my_planning') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/employee/shifts/week" class="btn btn--ghost btn--sm">☰ <?= __('table_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/shifts/calendar" class="btn btn--ghost btn--sm">📅 <?= __('calendar_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/swaps/create" class="btn btn--primary btn--sm">⇄ <?= __('request_swap') ?></a>
    </div>
</div>

<!-- Barre de contrôle : navigation + toggle vue -->
<div class="card card--mb">
    <div class="card-body sd-control-bar">

        <!-- Navigation -->
        <a href="?start=<?= $prev_start ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm">←</a>
        <strong class="sd-period-label"><?= htmlspecialchars($periodLabel) ?></strong>
        <a href="?start=<?= $next_start ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm">→</a>

        <!-- Toggle -->
        <div class="sd-mode-toggle">
            <a href="?start=<?= $days[0]->format('Y-m-d') ?>&view=3days"
                class="btn btn--sm <?= $period_mode === '3days' ? 'btn--primary' : 'btn--ghost' ?>">3 jours</a>
            <a href="?start=<?= $days[0]->format('Y-m-d') ?>&view=week"
                class="btn btn--sm <?= $period_mode === 'week' ? 'btn--primary' : 'btn--ghost' ?>">Semaine</a>
        </div>

        <!-- Raccourci aujourd'hui -->
        <a href="?start=<?= $today ?>&view=<?= $period_mode ?>" class="btn btn--ghost btn--sm" title="<?= __('back_to_today') ?>"><?= __('today') ?></a>
    </div>
</div>

<!-- Jours ──────────────────────────────────────────────────────────── -->
<?php foreach ($days as $day): ?>
    <?php
    $dateStr  = $day->format('Y-m-d');
    $isToday  = $dateStr === $today;
    $dayName  = $frDaysFull[$day->format('l')] ?? $day->format('l');
    $dayShort = $frDays[$day->format('l')] ?? substr($day->format('l'), 0, 3);

    // Collecte des shifts du jour (tous users)
    $dayShifts = [];
    foreach ($all_user_ids as $uid) {
        foreach ($shifts_by_date_user[$dateStr][$uid] ?? [] as $s) {
            $tid   = (int)($s['shift_type_id'] ?? 0);
            $type  = $types_map[$tid] ?? null;
            $dayShifts[] = array_merge($s, [
                '_uid'       => $uid,
                '_name'      => $users_map[$uid] ?? '#' . $uid,
                '_is_me'     => $uid === $my_user_id,
                '_color'     => $userColorMap[$uid] ?? '#6366f1',
                '_type_name' => $type['name'] ?? 'Shift',
            ]);
        }
    }

    // Tri par heure de début (normalisée)
    usort($dayShifts, function ($a, $b) use ($T_START) {
        $sm = function ($s) use ($T_START) {
            $v = sdMin($s['start_time'] ?? '00:00');
            if (!empty($s['cross_midnight']) || sdMin($s['end_time'] ?? '00:00') <= $v) {
            }
            return $v < $T_START ? $v + 1440 : $v;
        };
        return $sm($a) - $sm($b);
    });

    $lanes      = sdLanes($dayShifts, $T_START);
    $shiftCount = count($dayShifts);
    ?>
    <div class="card sd-day-card <?= $isToday ? 'tl-day-card--today' : '' ?>" data-date="<?= htmlspecialchars($dateStr) ?>">
        <div class="sd-day-wrap">
            <div class="sd-day-inner">

                <!-- En-tête du jour -->
                <div class="sd-day-header">
                    <div class="sd-day-col">
                        <span class="sd-day-name <?= $isToday ? 'sd-day-name--today' : '' ?>"><?= $dayName ?></span>
                        <span class="sd-day-sub"><?= $day->format('d M') ?></span>
                        <?php if ($isToday): ?>
                            <span class="badge badge--active badge--mt"><?= __('today') ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="sd-day-stats">
                        <?= $shiftCount ?> shift<?= $shiftCount !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <!-- Gantt du jour -->
                <div class="sd-gantt">

                    <!-- Axe des heures propre à ce jour -->
                    <div class="sd-hours-axis">
                        <?php foreach ($headerHours as $i => $h): ?>
                            <div class="sd-hours-cell">
                                <?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($lanes)): ?>
                        <div class="sd-empty-lane">
                            <span class="sd-empty-text">—</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lanes as $li => $laneShifts): ?>
                            <div class="sd-lane tl-lane <?= $li > 0 ? 'tl-lane--mt' : '' ?>">
                                <!-- Grille verticale -->
                                <?php for ($i = 0; $i <= 24; $i++): ?>
                                    <div class="tl-grid-line" style="left:<?= number_format($i / 24 * 100, 5) ?>%;opacity:<?= $i % 6 === 0 ? '.5' : '.2' ?>"></div>
                                <?php endfor; ?>

                                <!-- Barres shifts -->
                                <?php foreach ($laneShifts as $sh): ?>
                                    <?php
                                    $left  = ($sh['_sm'] - $T_START) / $T_TOTAL * 100;
                                    $width = ($sh['_em'] - $sh['_sm']) / $T_TOTAL * 100;
                                    $left  = max(0, min(100, $left));
                                    $width = max(0.4, min(100 - $left, $width));

                                    $color   = $sh['_color'];
                                    $isMe    = $sh['_is_me'];
                                    $bg      = $isMe ? $color : $color . '60';
                                    $border  = $isMe
                                        ? "border:2px solid {$color}"
                                        : "border:1px solid {$color}90";
                                    $tooltip = htmlspecialchars(
                                        $sh['_name']
                                            . ' · ' . sdFmt($sh['_sm'])
                                            . ' – ' . sdFmt($sh['_em'])
                                            . ' (' . $sh['_type_name'] . ')'
                                    );

                                    // Calcul multi-taux avec pause au centre
                                    $shUid      = $sh['_uid'];
                                    $shPauseMin = (int)($sh['pause_minutes'] ?? 0);
                                    $shStoreId  = (int)($sh['store_id'] ?? 0);
                                    $shCurrency = $_currencyMap[$shUid] ?? 'JPY';
                                    $shPay      = sdPayBreakdown(
                                        $sh['start_time'] ?? '00:00',
                                        $sh['end_time']   ?? '00:00',
                                        $shPauseMin,
                                        !empty($sh['cross_midnight']),
                                        $shUid,
                                        $shStoreId,
                                        $types_map,
                                        $_ratesMap,
                                        $shCurrency
                                    );
                                    $shNetMin   = $shPay['net_minutes'];
                                    $shH        = intdiv($shNetMin, 60);
                                    $shM        = $shNetMin % 60;
                                    $shHoursLabel = $shH . 'h' . str_pad($shM, 2, '0', STR_PAD_LEFT)
                                        . ($shPauseMin > 0 ? ' (pause ' . $shPauseMin . ' min)' : '');
                                    $shPayLabel   = $shPay['has_rate']
                                        ? format_currency($shPay['total'], $shCurrency) : '';
                                    // Détail JSON pré-formaté pour le modal
                                    $shRateDetail = htmlspecialchars(
                                        json_encode($shPay['items'], JSON_UNESCAPED_UNICODE),
                                        ENT_QUOTES
                                    );
                                    ?>
                                    <div title="<?= $tooltip ?>"
                                         onclick="sdModalOpen(this)"
                                         data-id="<?= (int)($sh['id'] ?? 0) ?>"
                                         data-date="<?= htmlspecialchars($sh['shift_date'] ?? '') ?>"
                                         data-start="<?= sdFmt($sh['_sm']) ?>"
                                         data-end="<?= sdFmt($sh['_em']) ?>"
                                         data-name="<?= htmlspecialchars($sh['_name']) ?>"
                                         data-type="<?= htmlspecialchars($sh['_type_name']) ?>"
                                         data-notes="<?= htmlspecialchars($sh['notes'] ?? '') ?>"
                                         data-color="<?= htmlspecialchars($color) ?>"
                                         data-hours="<?= htmlspecialchars($shHoursLabel) ?>"
                                         data-pay="<?= htmlspecialchars($shPayLabel) ?>"
                                         data-rate-detail="<?= $shRateDetail ?>"
                                         data-width-pct="<?= number_format($width, 5) ?>"
                                         <?php if ($_canManage): ?>data-draggable="1"<?php endif; ?>
                                         class="sd-lane-bar <?= $_canManage ? 'sd-lane-bar--grab' : '' ?>"
                                         style="left:<?= number_format($left, 5) ?>%;width:<?= number_format($width, 5) ?>%;background:<?= htmlspecialchars($bg) ?>;<?= $border ?>">
                                        <?php if ($width > 4): ?>
                                            <span class="sd-bar-main">
                                                <?= sdFmt($sh['_sm']) ?>–<?= sdFmt($sh['_em']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($width > 9): ?>
                                            <span class="sd-bar-sub <?= $isMe ? 'sd-bar-sub--me' : '' ?>">
                                                <?= htmlspecialchars($sh['_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($_canManage): ?>
                    <!-- Zone de création par drag (managers uniquement) -->
                    <div class="sd-create-zone">
                        <span class="sd-create-zone__hint">
                            <span class="sd-create-zone__plus">+</span> <?= __('zone_to_create') ?>
                        </span>
                    </div>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Légende employés -->
<div class="sd-legend">
    <?php foreach ($all_user_ids as $uid): ?>
        <?php
        $empColor = $userColorMap[$uid] ?? '#6366f1';
        $empName  = $users_map[$uid] ?? ('#' . $uid);
        $isMe     = $uid === $my_user_id;
        ?>
        <span class="sd-legend-item">
            <span class="sd-legend-bar" style="background:<?= $isMe ? $empColor : $empColor . '70' ?>;border:<?= $isMe ? "2px solid {$empColor}" : "1px solid {$empColor}99" ?>"></span>
            <span class="<?= $isMe ? 'sd-legend-name--me' : 'text-muted' ?>"><?= htmlspecialchars($empName) ?><?= $isMe ? ' (' . __('me') . ')' : '' ?></span>
        </span>
    <?php endforeach; ?>
    <span class="sd-legend-hint"><?= __('timeline_legend_hint') ?></span>
</div>

<!-- ── Modal détail shift ────────────────────────────────────────────────── -->
<div id="sd-overlay" class="sd-overlay" onclick="sdModalClose()">

    <div id="sd-modal" class="sd-modal" onclick="event.stopPropagation()">

        <!-- Bandeau couleur + titre -->
        <div id="sd-header" class="sd-modal-header">
            <div>
                <div class="sd-modal-title">
                    <span id="sd-dot" class="sd-dot"></span>
                    <strong id="sd-name" class="sd-modal-name"></strong>
                </div>
                <div id="sd-date" class="sd-modal-sub"></div>
            </div>
            <button onclick="sdModalClose()" class="sd-modal-close" title="<?= __('close') ?>">×</button>
        </div>

        <!-- Corps -->
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
            <?php if ($_canManage): ?>
            <div id="sd-rate-block" class="sd-rate-block">
                <div id="sd-rate-rows"></div>
                <div id="sd-pay-row" class="sd-pay-row">
                    <span class="sd-modal-label"><?= __('total_estimated') ?></span>
                    <strong id="sd-pay" class="sd-pay"></strong>
                </div>
                <div id="sd-no-rate" class="sd-no-rate"><?= __('no_rate_configured') ?></div>
            </div>
            <?php endif; ?>
            <div id="sd-notes-row" class="sd-notes-row">
                <span class="sd-modal-label"><?= __('notes') ?></span>
                <span id="sd-notes" class="sd-notes"></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="sd-modal-footer">
            <?php if ($_canManage): ?>
            <a id="sd-edit-link" href="#" class="btn btn--primary btn--sm"><?= __('edit') ?></a>
            <form id="sd-delete-form" method="POST" action="#" class="form-inline"
                  onsubmit="return confirm('<?= __('confirm_delete_shift_permanently') ?>')">
                <button type="submit" class="btn btn--danger btn--sm"><?= __('delete') ?></button>
            </form>
            <?php endif; ?>
            <button onclick="sdModalClose()" class="btn btn--ghost btn--sm ml-auto"><?= __('close') ?></button>
        </div>

    </div>
</div>

<script>
function sdModalOpen(el) {
    var id         = el.dataset.id;
    var name       = el.dataset.name;
    var date       = el.dataset.date;
    var start      = el.dataset.start;
    var end        = el.dataset.end;
    var type       = el.dataset.type;
    var notes      = el.dataset.notes;
    var color      = el.dataset.color;
    var hours      = el.dataset.hours;
    var pay        = el.dataset.pay;
    var rateDetail = [];
    try { rateDetail = JSON.parse(el.dataset.rateDetail || '[]'); } catch(e) {}

    document.getElementById('sd-dot').style.background = color;
    document.getElementById('sd-name').textContent     = name;
    document.getElementById('sd-date').textContent     = date;
    document.getElementById('sd-time').textContent     = start + ' – ' + end;
    document.getElementById('sd-hours').textContent    = hours || '—';
    document.getElementById('sd-type').textContent     = type || '—';

    <?php if ($_canManage): ?>
    var block   = document.getElementById('sd-rate-block');
    var rows    = document.getElementById('sd-rate-rows');
    var payRow  = document.getElementById('sd-pay-row');
    var noRate  = document.getElementById('sd-no-rate');
    var hasAnyRate = rateDetail.some(function(item) { return item.has_rate; });

    rows.innerHTML = '';
    if (rateDetail.length > 0) {
        rateDetail.forEach(function(item) {
            var row = document.createElement('div');
            row.className = 'pay-row';
            var lbl = item.rate_fmt
                ? item.type_name + ' · ' + item.rate_fmt
                : item.type_name;
            var mins = Math.round(item.minutes);
            var h = Math.floor(mins / 60), m = mins % 60;
            var dur = h + 'h' + (m > 0 ? String(m).padStart(2,'0') : '00');
            row.innerHTML =
                '<span class="pay-label">' + lbl + ' × ' + dur + '</span>' +
                '<span class="pay-amount">' + (item.pay_fmt || '—') + '</span>';
            rows.appendChild(row);
        });
    }

    if (hasAnyRate) {
        noRate.style.display  = 'none';
        payRow.style.display  = 'flex';
        document.getElementById('sd-pay').textContent = pay || '—';
    } else {
        payRow.style.display  = 'none';
        noRate.style.display  = 'block';
    }
    block.style.display = 'flex';
    block.style.flexDirection = 'column';

    document.getElementById('sd-edit-link').href =
        '<?= rtrim($BASE_URL, '/') ?>/admin/shifts/' + id + '/edit';
    document.getElementById('sd-delete-form').action =
        '<?= rtrim($BASE_URL, '/') ?>/admin/shifts/' + id + '/delete';
    <?php endif; ?>

    var notesRow = document.getElementById('sd-notes-row');
    if (notes) {
        document.getElementById('sd-notes').textContent = notes;
        notesRow.style.display = 'flex';
    } else {
        notesRow.style.display = 'none';
    }

    document.getElementById('sd-overlay').classList.add('open');
}

function sdModalClose() {
    document.getElementById('sd-overlay').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') sdModalClose();
});
</script>

<?php if ($_canManage): ?>
<!-- ── Modal création rapide (drag-to-create) ──────────────────────────────── -->
<div id="qc-overlay" class="qc-overlay" onclick="if(event.target===this)sdQcClose()">

    <div id="qc-modal" class="qc-modal" onclick="event.stopPropagation()">

        <div class="qc-header">
            <strong class="qc-title"><?= __('quick_new_shift') ?></strong>
            <div class="qc-header-right">
                <span id="qc-date-label" class="qc-date-label"></span>
                <button onclick="sdQcClose()" class="qc-close" title="<?= __('close') ?>">×</button>
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
                <span class="text-hint"><?= __('shift_types') ?> <em class="text-dim">(<?= __('optional') ?>)</em></span>
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
    'error_prefix'  => __('error_prefix'),
    'network_error' => __('network_error'),
    'creating'      => __('creating'),
    'create'        => __('create'),
    'no_type'       => __('no_type'),
], JSON_UNESCAPED_UNICODE) ?>;

(function () {
    'use strict';

    // ── Constantes timeline ──────────────────────────────────────────────────
    var T_START = 360;   // 06:00 en minutes depuis minuit
    var T_TOTAL = 1440;  // 24h
    var BASE    = '<?= rtrim($BASE_URL, '/') ?>';

    // Données pré-calculées en PHP → JS
    var SD_USERS = <?= json_encode(
        array_map(fn($uid) => [
            'id'       => $uid,
            'name'     => $users_map[$uid] ?? ('#' . $uid),
            'store_id' => $_userStoreMap[$uid] ?? 0,
        ], $all_user_ids),
        JSON_UNESCAPED_UNICODE
    ) ?>;

    var SD_TYPES = <?= json_encode(
        array_values($types_map),
        JSON_UNESCAPED_UNICODE
    ) ?>;

    // ── Helpers ──────────────────────────────────────────────────────────────
    function minToStr(min) {
        var m = ((Math.round(min) % 1440) + 1440) % 1440;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }
    function snapTo15(min) { return Math.round(min / 15) * 15; }
    function xToMin(x, w)  { return Math.max(0, Math.min(1, x / w)) * T_TOTAL + T_START; }

    // ── État drag ────────────────────────────────────────────────────────────
    var _drag           = null;   // objet décrivant le drag en cours
    var _blockNextClick = false;  // bloque le prochain onclick après un drag

    // ── Mousedown ────────────────────────────────────────────────────────────
    document.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return;

        // — Drag-to-move : clic sur une barre de shift ——————————————————————
        var bar = e.target.closest('[data-draggable="1"]');
        if (bar) {
            var lane = bar.closest('.sd-lane');
            if (!lane) return;
            var rect = lane.getBoundingClientRect();

            var sParts = (bar.dataset.start || '00:00').split(':');
            var eParts = (bar.dataset.end   || '00:00').split(':');
            var sMin   = parseInt(sParts[0]) * 60 + parseInt(sParts[1]);
            var eMin   = parseInt(eParts[0]) * 60 + parseInt(eParts[1]);
            var dur    = eMin >= sMin ? eMin - sMin : eMin + 1440 - sMin;
            var normS  = sMin < T_START ? sMin + 1440 : sMin;

            // Décalage du clic dans la barre (pour ancrer le ghost)
            var clickMin   = xToMin(e.clientX - rect.left, rect.width);
            var offsetMin  = clickMin - normS;

            _drag = {
                type: 'move', bar: bar, lane: lane, rect: rect,
                startX: e.clientX, moved: false,
                id: bar.dataset.id, date: bar.dataset.date,
                color: bar.dataset.color || '#6366f1',
                widthPct: parseFloat(bar.dataset.widthPct || '10'),
                dur: dur, normS: normS, offsetMin: offsetMin,
                ghost: null, newStart: null, newEnd: null,
            };
            e.preventDefault();
            return;
        }

        // — Drag-to-create : clic sur la zone de création ———————————————————
        var zone = e.target.closest('.sd-create-zone');
        if (zone) {
            var dayCard = zone.closest('.sd-day-card');
            var rect2   = zone.getBoundingClientRect();
            var sMin2   = snapTo15(xToMin(e.clientX - rect2.left, rect2.width));

            _drag = {
                type: 'create', zone: zone, dayCard: dayCard,
                rect: rect2, startX: e.clientX, moved: false,
                startMin: sMin2, endMin: sMin2 + 60, ghost: null,
            };
            e.preventDefault();
        }
    }, true);

    // ── Mousemove ────────────────────────────────────────────────────────────
    document.addEventListener('mousemove', function (e) {
        if (!_drag) return;
        var dx = e.clientX - _drag.startX;

        // Initier le mode drag (seuil 5px)
        if (!_drag.moved && Math.abs(dx) > 5) {
            _drag.moved = true;

            if (_drag.type === 'move') {
                _drag.bar.style.opacity = '0.4';
                var g = document.createElement('div');
                g.id = 'sd-drag-ghost';
                g.style.cssText = 'position:absolute;top:3px;height:28px;border-radius:5px;box-sizing:border-box;pointer-events:none;z-index:20;border:2px dashed rgba(255,255,255,.7);opacity:.82';
                g.style.background = _drag.color;
                g.style.width = _drag.widthPct + '%';
                _drag.lane.appendChild(g);
                _drag.ghost = g;

            } else if (_drag.type === 'create') {
                var g2 = document.createElement('div');
                g2.id = 'sd-create-ghost';
                g2.style.cssText = 'position:absolute;top:2px;bottom:2px;border-radius:5px;box-sizing:border-box;pointer-events:none;z-index:10;background:rgba(99,102,241,.22);border:2px dashed #6366f1';
                _drag.zone.appendChild(g2);
                _drag.ghost = g2;
            }
        }

        if (!_drag.moved) return;

        if (_drag.type === 'move') {
            var x   = e.clientX - _drag.rect.left;
            var cMin = xToMin(x, _drag.rect.width);
            var newSRaw  = cMin - _drag.offsetMin;
            var newS = ((snapTo15(newSRaw) % 1440) + 1440) % 1440;
            var newE = (newS + _drag.dur) % 1440;
            _drag.newStart = newS;
            _drag.newEnd   = newE;

            // Positionner le ghost
            var normNew = newS < T_START ? newS + 1440 : newS;
            var lPct = Math.max(0, Math.min(99, (normNew - T_START) / T_TOTAL * 100));
            if (_drag.ghost) _drag.ghost.style.left = lPct + '%';

        } else if (_drag.type === 'create') {
            var x3   = Math.max(0, e.clientX - _drag.rect.left);
            var cur3 = snapTo15(xToMin(x3, _drag.rect.width));
            _drag.endMin = cur3 > _drag.startMin + 14 ? cur3 : _drag.startMin + 30;

            var lPct3 = Math.max(0, Math.min(99, (_drag.startMin - T_START) / T_TOTAL * 100));
            var wPct3 = Math.max(0.5, Math.min(100 - lPct3, (_drag.endMin - _drag.startMin) / T_TOTAL * 100));
            if (_drag.ghost) {
                _drag.ghost.style.left  = lPct3 + '%';
                _drag.ghost.style.width = wPct3 + '%';
            }
        }
    });

    // ── Mouseup ──────────────────────────────────────────────────────────────
    document.addEventListener('mouseup', function (e) {
        if (!_drag) return;
        var drag = _drag;
        _drag = null;

        if (drag.ghost) drag.ghost.remove();

        if (drag.type === 'move') {
            drag.bar.style.opacity = '1';
            if (!drag.moved) return; // simple clic → onclick prend le relais
            _blockNextClick = true;
            if (drag.newStart === null) return;
            sdDoMove(drag.id, drag.date, minToStr(drag.newStart), minToStr(drag.newEnd));

        } else if (drag.type === 'create') {
            var dayCard = drag.dayCard;
            var date    = dayCard ? dayCard.dataset.date : '';
            sdQcOpen(date, minToStr(drag.startMin % 1440), minToStr(drag.endMin % 1440));
        }
    });

    // Bloquer le onclick suivant un drag-to-move (évite d'ouvrir le modal)
    document.addEventListener('click', function (e) {
        if (_blockNextClick) {
            _blockNextClick = false;
            e.stopImmediatePropagation();
            e.preventDefault();
        }
    }, true);

    // ── Appel API : déplacer un shift ────────────────────────────────────────
    function sdDoMove(id, date, startTime, endTime) {
        fetch(BASE + '/admin/shifts/' + id + '/move', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shift_date: date, start_time: startTime, end_time: endTime }),
        })
        .then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            r.json().then(function (d) { alert(I18N.error_prefix + (d.error || r.status)); });
        })
        .catch(function () { alert(I18N.network_error); });
    }

    // ── Modal création rapide ────────────────────────────────────────────────
    function sdQcOpen(date, startTime, endTime) {
        // Remplir le sélecteur d'employés
        var userSel = document.getElementById('qc-user');
        userSel.innerHTML = '';
        SD_USERS.forEach(function (u) {
            var opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            opt.dataset.storeId = u.store_id;
            userSel.appendChild(opt);
        });

        // Remplir le sélecteur de types
        refreshTypeOptions(userSel);
        userSel.onchange = function () { refreshTypeOptions(userSel); };

        document.getElementById('qc-date').value       = date;
        document.getElementById('qc-date-label').textContent = date;
        document.getElementById('qc-start').value      = startTime;
        document.getElementById('qc-end').value        = endTime;
        document.getElementById('qc-overlay').classList.add('open');
    }

    function refreshTypeOptions(userSel) {
        var selOpt  = userSel.options[userSel.selectedIndex];
        var storeId = selOpt ? parseInt(selOpt.dataset.storeId || '0') : 0;
        var typeSel = document.getElementById('qc-type');
        typeSel.innerHTML = '<option value="">' + I18N.no_type + '</option>';
        SD_TYPES.forEach(function (t) {
            if (storeId > 0 && parseInt(t.store_id) !== storeId) return;
            var opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            typeSel.appendChild(opt);
        });
    }

    window.sdQcClose = function () {
        document.getElementById('qc-overlay').classList.remove('open');
        document.getElementById('qc-user').onchange = null;
    };

    document.getElementById('qc-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var userSel  = document.getElementById('qc-user');
        var selOpt   = userSel.options[userSel.selectedIndex];
        var storeId  = selOpt ? parseInt(selOpt.dataset.storeId || '0') : 0;
        var typeId   = document.getElementById('qc-type').value;

        var body = {
            store_id:   storeId,
            user_id:    parseInt(userSel.value),
            shift_date: document.getElementById('qc-date').value,
            start_time: document.getElementById('qc-start').value,
            end_time:   document.getElementById('qc-end').value,
        };
        if (typeId) body.shift_type_id = parseInt(typeId);

        var btn = document.getElementById('qc-submit');
        btn.disabled = true;
        btn.textContent = I18N.creating;

        fetch(BASE + '/admin/shifts/quick', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        })
        .then(function (r) {
            if (r.ok) { sdQcClose(); window.location.reload(); return; }
            r.json().then(function (d) { alert(I18N.error_prefix + (d.error || r.status)); });
            btn.disabled = false; btn.textContent = I18N.create;
        })
        .catch(function () {
            alert(I18N.network_error);
            btn.disabled = false; btn.textContent = I18N.create;
        });
    });

})();
</script>
<?php endif; ?>