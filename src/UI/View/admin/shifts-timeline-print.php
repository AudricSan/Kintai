<?php
/**
 * Planning Timeline — Document d'impression (HTML autonome, sans layout)
 *
 * @var \DateTimeImmutable[] $days
 * @var string               $period_mode        'week' | '3days'
 * @var array                $shifts_by_date_user date → uid → shift[]
 * @var int[]                $all_user_ids
 * @var array                $users_map           id → nom
 * @var array                $user_color_map      id → couleur hex|null
 * @var array                $types_map           id → shift_type
 * @var string               $today
 * @var int                  $filter_store_id
 * @var array                $stores_map          id → nom
 * @var string               $BASE_URL
 */

// ── Helpers ──────────────────────────────────────────────────────────────────
function ptMin(string $t): int
{
    $p = explode(':', substr($t, 0, 5));
    return (int) ($p[0] ?? 0) * 60 + (int) ($p[1] ?? 0);
}
function ptFmt(string $t): string
{
    return substr($t, 0, 5);
}

$T_START = 6 * 60;   // 06:00

$_palette = ['#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#f97316','#06b6d4','#ec4899','#84cc16'];
$userColorMap = [];
foreach ($all_user_ids as $i => $uid) {
    $userColorMap[$uid] = $user_color_map[$uid] ?? $_palette[$i % count($_palette)];
}

$storeName  = $stores_map[$filter_store_id] ?? '';
$firstDay   = $days[0];
$lastDay    = $days[count($days) - 1];
$periodLabel = $period_mode === 'week'
    ? __('period_week_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')])
    : __('period_3days_label', ['start' => $firstDay->format('d M'), 'end' => $lastDay->format('d M Y')]);

// Heures du Gantt : 06 → 06 (lendemain) = 24 tranches d'1h
$ganttHours = [];
for ($h = 6; $h < 30; $h++) $ganttHours[] = $h % 24;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('print_timeline_title') ?? (__('planning') . ' — ' . __('timeline')) ?> — <?= htmlspecialchars($storeName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #222;
            background: #f0f2f5;
        }

        /* ── Barre d'outils (écran uniquement) ──────────────────────── */
        .pt-toolbar {
            background: #2c3e50;
            color: #fff;
            padding: .6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .pt-toolbar button {
            background: #3498db;
            color: #fff;
            border: none;
            padding: .4rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10pt;
        }
        .pt-toolbar button:hover { background: #2980b9; }
        .pt-toolbar a { color: #aed6f1; font-size: 9pt; text-decoration: none; }
        .pt-toolbar a:hover { text-decoration: underline; }
        .pt-toolbar__hint { margin-left: auto; font-size: 8.5pt; opacity: .6; }

        /* ── Page ────────────────────────────────────────────────────── */
        .pt-page {
            max-width: 190mm;
            margin: 1.5rem auto;
            background: #fff;
            padding: 8mm 10mm;
            box-shadow: 0 2px 12px rgba(0,0,0,.15);
        }

        /* ── En-tête ─────────────────────────────────────────────────── */
        .pt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: .7rem;
            margin-bottom: 1.2rem;
        }
        .pt-brand { font-size: 18pt; font-weight: 700; color: #2c3e50; letter-spacing: -1px; }
        .pt-brand small { display: block; font-size: 8pt; font-weight: 400; color: #666; margin-top: 2px; }
        .pt-doc-info { text-align: right; }
        .pt-doc-info strong { font-size: 11pt; color: #2c3e50; display: block; }
        .pt-doc-info span { font-size: 8.5pt; color: #666; display: block; margin-top: 2px; }

        /* ── Bloc jour ───────────────────────────────────────────────── */
        .pt-day { margin-bottom: 1.5rem; page-break-inside: avoid; }
        .pt-day-title {
            font-size: 9.5pt;
            font-weight: 700;
            color: #fff;
            background: #2c3e50;
            padding: .3rem .7rem;
            border-left: 4px solid #3498db;
            margin-bottom: .4rem;
        }
        .pt-day-title--today { background: #1a5276; border-left-color: #f39c12; }
        .pt-day-empty {
            text-align: center;
            color: #aaa;
            font-style: italic;
            padding: .5rem;
            border: 1px dashed #ddd;
            border-radius: 4px;
            font-size: 8.5pt;
        }

        /* ── Gantt visuel ────────────────────────────────────────────── */
        .pt-gantt {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: .6rem;
            table-layout: fixed;
        }
        .pt-gantt-name {
            width: 18%;
            padding: .15rem .4rem;
            font-size: 7.5pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border: 1px solid #ddd;
            background: #f8f9fa;
            vertical-align: middle;
        }
        .pt-gantt-hh {
            text-align: right;
            padding: .1rem 2px .1rem 0;
            border: 1px solid #ccc;
            background: #2c3e50;
            color: #fff;
            font-size: 6.5pt;
            font-weight: 700;
        }
        .pt-gantt-cell {
            border: 1px solid #e8e8e8;
            height: 16px;
        }
        .pt-gantt-cell--shift {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* ── Tableau textuel ─────────────────────────────────────────── */
        .pt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .pt-table thead tr { background: #34495e; color: #fff; }
        .pt-table th { padding: .25rem .45rem; text-align: left; font-weight: 600; font-size: 8pt; }
        .pt-table th.tr { text-align: right; }
        .pt-table td { padding: .2rem .45rem; border-bottom: 1px solid #eee; vertical-align: middle; }
        .pt-table td.tr { text-align: right; font-variant-numeric: tabular-nums; }
        .pt-table td.muted { color: #888; font-size: 8pt; }
        .pt-table tbody tr:nth-child(even) { background: #fafafa; }
        .pt-table tfoot tr { background: #ecf0f1; font-weight: 700; }
        .pt-table tfoot td { border-top: 2px solid #bdc3c7; padding: .25rem .45rem; }

        .pt-color-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 4px;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }


        /* ── Pied de page ────────────────────────────────────────────── */
        .pt-footer {
            margin-top: 1rem;
            padding-top: .5rem;
            border-top: 1px solid #ddd;
            font-size: 7.5pt;
            color: #aaa;
            text-align: center;
        }

        /* ── Impression ──────────────────────────────────────────────── */
        @media print {
            body { background: #fff; }
            .pt-toolbar { display: none !important; }
            .pt-page {
                max-width: 100%;
                margin: 0;
                padding: 5mm 8mm;
                box-shadow: none;
            }
            .pt-day { page-break-inside: avoid; }
            .pt-gantt-cell--shift { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        @page { size: A4 portrait; margin: 10mm; }
    </style>
</head>
<body>

<!-- Barre d'outils (écran uniquement) -->
<div class="pt-toolbar">
    <button onclick="window.print()">🖨 <?= __('print_timeline') ?></button>
    <a href="javascript:window.close()">✕ <?= __('close') ?></a>
    <span class="pt-toolbar__hint"><?= __('print_hint') ?></span>
</div>

<!-- Document A3 paysage -->
<div class="pt-page">

    <!-- En-tête -->
    <div class="pt-header">
        <div>
            <div class="pt-brand">Kintai
                <small><?= htmlspecialchars($storeName) ?></small>
            </div>
        </div>
        <div class="pt-doc-info">
            <strong><?= __('planning') ?> — <?= __('timeline') ?></strong>
            <span><?= htmlspecialchars($periodLabel) ?></span>
            <span><?= __('payslip_issued_on') ?> <?= date('d/m/Y') ?></span>
        </div>
    </div>

    <?php if (empty($all_user_ids)): ?>
        <p style="text-align:center;color:#888;padding:2rem;font-style:italic"><?= __('no_store_found') ?></p>
    <?php else: ?>

    <!-- Jours -->
    <?php foreach ($days as $day): ?>
    <?php
    $dateStr  = $day->format('Y-m-d');
    $isToday  = $dateStr === $today;
    $dayName  = __(strtolower($day->format('l')));

    // Collecte des shifts du jour
    $dayShifts = [];
    foreach ($all_user_ids as $uid) {
        foreach ($shifts_by_date_user[$dateStr][$uid] ?? [] as $s) {
            $tid = (int) ($s['shift_type_id'] ?? 0);
            $dayShifts[] = array_merge($s, [
                '_uid'       => $uid,
                '_name'      => $users_map[$uid] ?? ('#' . $uid),
                '_color'     => $userColorMap[$uid] ?? '#6366f1',
                '_type_name' => $types_map[$tid]['name'] ?? '—',
            ]);
        }
    }
    usort($dayShifts, fn($a, $b) => strcmp($users_map[$a['_uid']] ?? '', $users_map[$b['_uid']] ?? ''));

    $shiftCount = count($dayShifts);
    $staffCount = count(array_unique(array_column($dayShifts, '_uid')));
    ?>
    <div class="pt-day">

        <!-- Titre du jour -->
        <div class="pt-day-title <?= $isToday ? 'pt-day-title--today' : '' ?>">
            <?= $dayName ?> <?= $day->format('d M Y') ?>
            <?php if ($shiftCount > 0): ?>
                &nbsp;·&nbsp; <?= $shiftCount ?> shift<?= $shiftCount !== 1 ? 's' : '' ?>
                &nbsp;·&nbsp; <?= $staffCount ?> <?= __('staff_abbr') ?>
            <?php endif; ?>
        </div>

        <?php if (empty($dayShifts)): ?>
            <div class="pt-day-empty">— <?= __('no_shift_this_day') ?> —</div>
        <?php else: ?>

            <!-- Gantt visuel (rangées = employés, colonnes = heures) -->
            <table class="pt-gantt" aria-hidden="true">
                <thead>
                    <tr>
                        <th class="pt-gantt-name"></th>
                        <?php foreach ($ganttHours as $gh): ?>
                            <th class="pt-gantt-hh"><?= str_pad((string) $gh, 2, '0', STR_PAD_LEFT) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_user_ids as $uid): ?>
                    <?php
                    $userShiftsGantt = [];
                    foreach ($shifts_by_date_user[$dateStr][$uid] ?? [] as $s) {
                        $sm = ptMin($s['start_time'] ?? '00:00');
                        $em = ptMin($s['end_time']   ?? '00:00');
                        if (!empty($s['cross_midnight']) || $em <= $sm) $em += 1440;
                        if ($sm < $T_START) { $sm += 1440; $em += 1440; }
                        $userShiftsGantt[] = ['sm' => $sm, 'em' => $em];
                    }
                    if (empty($userShiftsGantt)) continue;
                    $color = $userColorMap[$uid] ?? '#6366f1';
                    ?>
                    <tr>
                        <td class="pt-gantt-name"><?= htmlspecialchars($users_map[$uid] ?? ('#' . $uid)) ?></td>
                        <?php foreach ($ganttHours as $i => $gh): ?>
                        <?php
                        $cellStart = $T_START + $i * 60;
                        $cellEnd   = $cellStart + 60;
                        $covered   = false;
                        foreach ($userShiftsGantt as $us) {
                            if ($us['sm'] < $cellEnd && $us['em'] > $cellStart) { $covered = true; break; }
                        }
                        ?>
                        <td class="pt-gantt-cell <?= $covered ? 'pt-gantt-cell--shift' : '' ?>"
                            <?= $covered ? 'style="background:' . htmlspecialchars($color) . ';"' : '' ?>></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>


    <?php endif; ?>

    <!-- Pied de page -->
    <div class="pt-footer">
        <?= __('payslip_footer', ['date' => date('d/m/Y H:i')]) ?>
    </div>

</div>

<script>
    <?php if ($autoprint ?? false): ?>
    window.addEventListener('load', () => window.print());
    <?php endif; ?>
</script>

</body>
</html>
