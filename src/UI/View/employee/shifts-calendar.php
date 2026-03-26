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
 * @var array  $shifts_by_date 'Y-m-d' → shift[]
 * @var int    $my_user_id
 * @var array  $users_colour  uid → '#hex'
 * @var array  $types_map     id → shift_type
 * @var array  $stores_map    id → name
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

// Compter mes heures du mois
$myMonthMinutes = 0;
foreach ($shifts_by_date as $dayShifts) {
    foreach ($dayShifts as $s) {
        if ((int)($s['user_id'] ?? 0) !== $my_user_id) continue;
        [$sh, $sm] = explode(':', substr($s['start_time'] ?? '00:00', 0, 5));
        [$eh, $em] = explode(':', substr($s['end_time'] ?? '00:00', 0, 5));
        $start = (int)$sh * 60 + (int)$sm;
        $end   = (int)$eh * 60 + (int)$em;
        if (!empty($s['cross_midnight']) || $end <= $start) $end += 1440;
        $myMonthMinutes += max(0, $end - $start - (int)($s['pause_minutes'] ?? 0));
    }
}
$myMonthH = intdiv($myMonthMinutes, 60);
$myMonthM = $myMonthMinutes % 60;
?>

<div class="page-header">
    <h2 class="page-header__title">📅 <?= __('my_planning') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/employee/shifts/week" class="btn btn--ghost btn--sm">☰ <?= __('table_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/shifts/day?start=<?= $today ?>&view=3days" class="btn btn--ghost btn--sm"><svg class="gantt-icon icon-inline" width="16" height="16" viewBox="0 0 24 24"><rect x="4" y="2" width="2" height="20" fill="#555"/><rect x="10" y="6" width="2" height="16" fill="#555"/><rect x="16" y="10" width="2" height="12" fill="#555"/></svg> <?= __('gantt_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/swaps/create" class="btn btn--primary btn--sm">⇄ <?= __('request_swap') ?></a>
    </div>
</div>

<!-- Navigation mois + stats -->
<div class="ecal-nav">
    <div class="ecal-nav-side">
        <a href="<?= $BASE_URL ?>/employee/shifts/calendar?month=<?= $prev_month ?>" class="btn btn--ghost btn--sm">← <?= __('prev_week') ?></a>
    </div>
    <div class="ecal-nav-center">
        <span class="ecal-nav-title"><?= htmlspecialchars($month_label) ?></span>
        <div class="ecal-stats">
            <span><?= __('hours') ?> : <strong><?= $myMonthH ?>h<?= str_pad((string)$myMonthM, 2, '0', STR_PAD_LEFT) ?></strong></span>
        </div>
    </div>
    <div class="ecal-nav-side">
        <a href="<?= $BASE_URL ?>/employee/shifts/calendar?month=<?= date('Y-m') ?>" class="btn btn--ghost btn--sm"><?= __('today') ?></a>
        <a href="<?= $BASE_URL ?>/employee/shifts/calendar?month=<?= $next_month ?>" class="btn btn--ghost btn--sm"><?= __('next_week') ?> →</a>
    </div>
</div>

<!-- Grille calendrier -->
<div class="card card--flush">
    <table class="ecal-grid">
        <thead>
            <tr>
                <?php foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $dow): ?>
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

                        // Uniquement mes shifts
                        $dayShifts = array_values(array_filter(
                            $shifts_by_date[$dateStr] ?? [],
                            fn($s) => (int)($s['user_id'] ?? 0) === $my_user_id
                        ));
                        usort($dayShifts, fn($a, $b) => strcmp($a['start_time'] ?? '', $b['start_time'] ?? ''));

                        $classes = [];
                        if (!$isThisMonth)      $classes[] = 'ecal-other';
                        if ($isToday)           $classes[] = 'ecal-today';
                        if (!empty($dayShifts)) $classes[] = 'ecal-has-my-shift';

                        $maxVisible = 3;
                        $extra = max(0, count($dayShifts) - $maxVisible);
                        ?>
                        <td class="<?= implode(' ', $classes) ?>">
                            <div class="ecal-day-num"><?= (int) $day->format('j') ?></div>
                            <?php foreach (array_slice($dayShifts, 0, $maxVisible) as $s): ?>
                                <?php
                                $tid   = (int) ($s['shift_type_id'] ?? 0);
                                $type  = $types_map[$tid] ?? null;
                                $col   = $users_colour[$my_user_id] ?? ($type['color'] ?? '#6366f1');
                                $bg    = $col . '25';
                                $time  = substr($s['start_time'] ?? '', 0, 5) . '–' . substr($s['end_time'] ?? '', 0, 5);
                                $tName = $type['name'] ?? 'Shift';
                                $store = $stores_map[(int)($s['store_id'] ?? 0)] ?? '';
                                $tip   = htmlspecialchars($time . ' · ' . $tName . ($store ? ' · ' . $store : ''));
                                ?>
                                <span class="ecal-pill"
                                    style="--pill-color:<?= $col ?>;--pill-bg:<?= $bg ?>"
                                    title="<?= $tip ?>">
                                    <?= htmlspecialchars("$time $tName") ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if ($extra > 0): ?>
                                <span class="ecal-more">+<?= $extra ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>