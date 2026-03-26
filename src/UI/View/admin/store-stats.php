<?php
/**
 * @var array  $store
 * @var int    $period
 * @var string $since
 * @var string $today
 * @var string $currency
 * @var int    $n
 * @var array  $usersMap
 * @var int[]  $memberIds
 */

function statCard(string $label, string $value, string $sub = '', string $color = 'var(--color-primary)'): string {
    return '<div class="sstat-card">'
        . '<div class="sstat-card__value" style="color:' . $color . '">' . htmlspecialchars($value) . '</div>'
        . '<div class="sstat-card__label">' . htmlspecialchars($label) . '</div>'
        . ($sub !== '' ? '<div class="sstat-card__sub">' . htmlspecialchars($sub) . '</div>' : '')
        . '</div>';
}

function barChart(array $data, string $color = 'var(--color-primary)', int $maxBarHeight = 80): string {
    if (!$data) return '<em class="sstat-empty">' . __('no_data') . '</em>';
    $max = max(array_values($data)) ?: 1;
    $out = '<div class="sstat-bar-chart">';
    foreach ($data as $label => $val) {
        $h = round($val / $max * $maxBarHeight);
        $out .= '<div class="sstat-bar-col">'
            . '<div class="sstat-bar" style="height:' . $h . 'px;background:' . $color . '" title="' . htmlspecialchars($label) . ': ' . round($val, 1) . '"></div>'
            . '<div class="sstat-bar-label">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }
    return $out . '</div>';
}

function scoreRing(int $score, string $label, string $color): string {
    $r = 36; $c = 2 * M_PI * $r;
    $pct = max(0, min(100, $score));
    $dash = $c * $pct / 100;
    return '<div class="sstat-ring-wrap">'
        . '<svg width="90" height="90" viewBox="0 0 90 90">'
        . '<circle cx="45" cy="45" r="' . $r . '" fill="none" stroke="var(--color-border)" stroke-width="8"/>'
        . '<circle cx="45" cy="45" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="8"'
        . ' stroke-dasharray="' . round($dash, 2) . ' ' . round($c, 2) . '"'
        . ' stroke-dashoffset="' . round($c / 4, 2) . '" stroke-linecap="round"/>'
        . '<text x="45" y="50" text-anchor="middle" font-size="16" font-weight="700" fill="' . $color . '">' . $pct . '</text>'
        . '</svg>'
        . '<div class="sstat-ring-label">' . htmlspecialchars($label) . '</div>'
        . '</div>';
}

function userName(array $usersMap, int $uid): string {
    if (!isset($usersMap[$uid])) return '#' . $uid;
    $u = $usersMap[$uid];
    return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['email'] ?? '#' . $uid);
}

function pct(int $part, int $total): string {
    return $total > 0 ? round($part / $total * 100, 1) . '%' : '—';
}

$dowLabels ??= [__('mon_abbr'), __('tue_abbr'), __('wed_abbr'), __('thu_abbr'), __('fri_abbr'), __('sat_abbr'), __('sun_abbr')];
?>


<div class="page-header">
    <h2 class="page-header__title">
        <?= __('statistics') ?> — <?= htmlspecialchars($store['name'] ?? '') ?>
        <span class="page-count">
            <?= __('period_from_to', ['from' => htmlspecialchars($since), 'to' => htmlspecialchars($today)]) ?>
        </span>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/employee-report" class="btn btn--ghost btn--sm">👥 <?= __('employee_report') ?></a>
        <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/edit" class="btn btn--ghost btn--sm"><?= __('edit') ?></a>
        <a href="<?= $BASE_URL ?>/admin/stores" class="btn btn--ghost btn--sm"><?= __('back') ?></a>
    </div>
</div>

<div class="sstat-period-nav">
    <?php foreach ([7 => __('period_7d'), 30 => __('period_30d'), 90 => __('period_3m'), 180 => __('period_6m'), 365 => __('period_1y')] as $d => $label): ?>
        <a href="?period=<?= $d ?>" class="<?= $period == $d ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($n === 0): ?>
    <div class="card"><div class="empty-state"><?= __('no_shift_period') ?></div></div>
<?php else: ?>

<!-- ================================================================== -->
<!-- 10. SCORES AVANCÉS (dashboard en tête) -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('performance_scores') ?></div>
    <div class="sstat-scores">
        <?= scoreRing($equityScore,      __('score_equity'),     '#10b981') ?>
        <?= scoreRing($efficiencyScore,  __('score_efficiency'), '#4f46e5') ?>
        <?= scoreRing($stabilityScore,   __('score_stability'),  '#f59e0b') ?>
        <?= scoreRing(max(0, 100 - $burnoutRisk), __('score_wellbeing'), $burnoutRisk > 50 ? '#ef4444' : ($burnoutRisk > 25 ? '#f59e0b' : '#10b981')) ?>
        <div class="sstat-legend-block">
            <div class="sstat-legend-title"><?= __('score_legend_title') ?></div>
            <div class="sstat-legend-text">
                <strong class="text-default"><?= __('score_equity') ?></strong> — <?= __('score_equity_desc') ?><br>
                <strong class="text-default"><?= __('score_efficiency') ?></strong> — <?= __('score_efficiency_desc') ?><br>
                <strong class="text-default"><?= __('score_stability') ?></strong> — <?= __('score_stability_desc') ?><br>
                <strong class="text-default"><?= __('score_wellbeing') ?></strong> — <?= __('score_wellbeing_desc') ?>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- 1. STATISTIQUES DE PLANIFICATION -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_planning_stats') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('shifts_analyzed'), (string) $n) ?>
        <?= statCard(__('avg_shift_duration'), number_format($avgDuration, 1) . 'h') ?>
        <?= statCard(__('avg_shifts_per_emp'), (string) $avgShiftsPerEmployee) ?>
        <?= statCard(__('avg_work_days'), (string) $avgDaysPerEmployee, __('per_employee')) ?>
        <?= statCard(__('opening_shifts'), (string) $openingShifts, pct($openingShifts, $n) . ' ' . __('of_shifts'), '#4f46e5') ?>
        <?= statCard(__('closing_shifts'), (string) $closingShifts, pct($closingShifts, $n) . ' ' . __('of_shifts'), '#6b7280') ?>
        <?= statCard(__('avg_gap_between_shifts'), $avgTimeBetweenShifts !== null ? $avgTimeBetweenShifts . 'h' : '—', __('same_employee_gap')) ?>
        <?php if ($shortRateShifts !== null): ?>
            <?= statCard(__('short_shifts_stat'), (string) $shortRateShifts, '< ' . $minShiftMin . ' min (' . pct($shortRateShifts, $n) . ')', $shortRateShifts > 0 ? '#ef4444' : '#10b981') ?>
        <?php endif; ?>
        <?php if ($longRateShifts !== null): ?>
            <?= statCard(__('long_shifts_stat'), (string) $longRateShifts, '> ' . $maxShiftMin . ' min (' . pct($longRateShifts, $n) . ')', $longRateShifts > 0 ? '#f59e0b' : '#10b981') ?>
        <?php endif; ?>
    </div>
    <div class="mt-md">
        <div class="sstat-sublabel"><?= __('duration_distribution') ?></div>
        <?= barChart(['< 4h' => $distShort, '4–8h' => $distMedium, '> 8h' => $distLong]) ?>
    </div>
</div>

<!-- ================================================================== -->
<!-- 2. PERFORMANCE OPÉRATIONNELLE -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_operational') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('net_hours'), number_format($totalNetHours, 1) . 'h') ?>
        <?= statCard(__('gross_hours'), number_format($totalGrossHours, 1) . 'h') ?>
        <?= statCard(__('active_days'), (string) $activeDays) ?>
        <?= statCard(__('avg_emp_per_hour'), (string) $avgEmpPerHour) ?>
    </div>
    <div class="mt-md">
        <div class="sstat-sublabel"><?= __('hourly_coverage') ?></div>
        <?php
        $slotData = [];
        foreach ($hoursBySlot as $h => $v) {
            $slotData[sprintf('%02dh', $h)] = $v;
        }
        echo barChart($slotData, '#4f46e5');
        ?>
    </div>
</div>

<!-- ================================================================== -->
<!-- 3. CHARGE DE TRAVAIL -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_workload') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('avg_hours_per_emp'), number_format($meanHours, 1) . 'h') ?>
        <?= statCard(__('std_deviation'), number_format($stdDev, 1) . 'h') ?>
        <?= statCard(__('gini_coeff'), number_format($gini, 3), __('gini_hint'), $gini > 0.4 ? '#ef4444' : ($gini > 0.2 ? '#f59e0b' : '#10b981')) ?>
        <?= statCard(__('top20_hours'), $top20ratio . '%', __('of_total_hours')) ?>
    </div>
    <div class="sstat-two mt-md">
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('hours_by_employee') ?></div>
            <table class="sstat-table">
                <thead><tr><th><?= __('employee') ?></th><th><?= __('hours') ?></th><th></th></tr></thead>
                <tbody>
                <?php
                $maxH = $hoursByUser ? max($hoursByUser) : 1;
                foreach ($hoursByUser as $uid => $h): ?>
                    <tr>
                        <td><?= htmlspecialchars(userName($usersMap, (int) $uid)) ?></td>
                        <td><?= number_format($h, 1) ?>h</td>
                        <td class="col-40">
                            <div class="sstat-progress">
                                <div class="sstat-progress-bar" style="width:<?= round($h / $maxH * 100) ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('hours_by_type') ?></div>
            <?= barChart($hoursByType, '#10b981') ?>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- 4. STABILITÉ DU PLANNING -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_stability') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('modification_rate'), $modRate . '%', __('shifts_were_modified'), $modRate > 30 ? '#ef4444' : ($modRate > 10 ? '#f59e0b' : '#10b981')) ?>
        <?= statCard(__('avg_mod_delay'), $avgModDelay !== null ? number_format($avgModDelay, 1) . 'h' : '—', __('between_create_mod')) ?>
    </div>
    <div class="sstat-two mt-md">
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('mod_by_dow') ?></div>
            <?= barChart(array_combine($dowLabels, array_values($modByDow)), '#f59e0b') ?>
        </div>
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('shifts_by_manager') ?></div>
            <table class="sstat-table">
                <thead><tr><th><?= __('role_manager') ?></th><th><?= __('created_col') ?></th><th><?= __('modified_col') ?></th></tr></thead>
                <tbody>
                <?php foreach ($shiftsCreatedByManager as $mid => $cnt): ?>
                    <tr>
                        <td><?= htmlspecialchars($mid > 0 ? userName($usersMap, $mid) : __('import_system')) ?></td>
                        <td><?= $cnt ?></td>
                        <td><?= $modByManager[$mid] ?? 0 ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- 5. STATISTIQUES RH -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_hr_stats') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('absence_rate'), $absRate . '%', __('absence_rate_hint')) ?>
        <?= statCard(__('approved_days'), (string) $approvedDays, __('cumulated_days')) ?>
        <?= statCard(__('total_requests'), (string) array_sum($timeoffsByStatus)) ?>
        <?= statCard(__('pending'), (string) ($timeoffsByStatus['pending'] ?? 0), '', '#f59e0b') ?>
    </div>
    <div class="sstat-two mt-md">
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('timeoff_by_type') ?></div>
            <?php if ($timeoffsByType): ?>
                <?= barChart($timeoffsByType, '#6366f1') ?>
            <?php else: ?>
                <div class="sstat-empty"><?= __('no_timeoff_period') ?></div>
            <?php endif; ?>
        </div>
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('timeoff_by_employee') ?></div>
            <table class="sstat-table">
                <thead><tr><th><?= __('employee') ?></th><th><?= __('requests') ?></th></tr></thead>
                <tbody>
                <?php arsort($timeoffsByUser); foreach ($timeoffsByUser as $uid => $cnt): ?>
                    <tr>
                        <td><?= htmlspecialchars(userName($usersMap, (int) $uid)) ?></td>
                        <td><?= $cnt ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$timeoffsByUser): ?>
                    <tr><td colspan="2" class="text-muted"><?= __('no_requests') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- 6. ANALYSE FINANCIÈRE -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_financial') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('total_cost'), number_format($totalCost, 2) . ' ' . $currency, __('on_period'), '#10b981') ?>
        <?= statCard(__('avg_cost_per_shift'), number_format($avgCostPerShift, 2) . ' ' . $currency) ?>
        <?= statCard(__('avg_cost_per_hour'), number_format($avgCostPerHour, 2) . ' ' . $currency . '/h') ?>
    </div>
    <?php if ($totalCost == 0): ?>
        <div class="alert-box--warn">
            <?= __('no_rate_cost_hint') ?>
        </div>
    <?php else: ?>
        <div class="alert-box--ok">
            <?= __('rate_info') ?>
        </div>
    <?php endif; ?>
    <div class="sstat-two mt-md">
        <div class="card card--p">
            <div class="sstat-sublabel--strong">
                <?= __('monthly_trend') ?>
                <?php if ($totalCost > 0): ?><span class="text-sm-muted">(<?= __('payroll_cost') ?>)</span>
                <?php else: ?><span class="text-sm-muted">(<?= __('hours') ?>)</span><?php endif; ?>
            </div>
            <?php if ($totalCost > 0): ?>
                <?= barChart(array_map(fn($v) => round($v, 2), $costByMonth), '#10b981') ?>
            <?php else: ?>
                <?= barChart(array_map(fn($v) => round($v, 1), $hoursByMonth), '#10b981') ?>
            <?php endif; ?>
        </div>
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('cost_hours_by_type') ?></div>
            <table class="sstat-table">
                <thead><tr><th><?= __('type') ?></th><th><?= __('hours') ?></th><?php if ($totalCost > 0): ?><th><?= __('cost_col') ?></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($hoursByType as $label => $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= number_format($h, 1) ?>h</td>
                        <?php if ($totalCost > 0): ?>
                            <td><?= number_format($costByType[$label] ?? 0, 2) ?> <?= $currency ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$hoursByType): ?>
                    <tr><td colspan="3" class="text-muted"><?= __('no_data') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalCost > 0): ?>
    <div class="card card--p mt-sm">
        <div class="sstat-sublabel--strong"><?= __('cost_by_employee') ?></div>
        <table class="sstat-table">
            <thead><tr><th><?= __('employee') ?></th><th><?= __('cost_col') ?></th><th><?= __('hours') ?></th><th><?= __('avg_cost_h') ?></th></tr></thead>
            <tbody>
            <?php arsort($costByUser); foreach ($costByUser as $uid => $cost): ?>
                <tr>
                    <td><?= htmlspecialchars(userName($usersMap, (int) $uid)) ?></td>
                    <td><?= number_format($cost, 2) ?> <?= $currency ?></td>
                    <td><?= number_format($hoursByUser[$uid] ?? 0, 1) ?>h</td>
                    <td><?= ($hoursByUser[$uid] ?? 0) > 0 ? number_format($cost / $hoursByUser[$uid], 2) . ' ' . $currency : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ================================================================== -->
<!-- 7. ANALYSE TEMPORELLE -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_temporal') ?></div>
    <div class="sstat-two">
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('hours_by_dow') ?></div>
            <?= barChart(array_combine($dowLabels, array_values($hoursByDow)), '#4f46e5') ?>
        </div>
        <div class="card card--p">
            <div class="sstat-sublabel--strong"><?= __('shifts_by_dow') ?></div>
            <?= barChart(array_combine($dowLabels, array_values($shiftsByDow)), '#6366f1') ?>
        </div>
    </div>
    <?php if (count($hoursByWeek) > 1): ?>
    <div class="card card--p mt-sm">
        <div class="sstat-sublabel--strong"><?= __('weekly_hours_trend') ?></div>
        <?= barChart(array_map(fn($v) => round($v, 1), $hoursByWeek), '#8b5cf6') ?>
    </div>
    <?php endif; ?>
</div>

<!-- ================================================================== -->
<!-- 9. QUALITÉ DU PLANNING -->
<!-- ================================================================== -->
<div class="sstat-section">
    <div class="sstat-section-title"><?= __('section_quality') ?></div>
    <div class="sstat-grid">
        <?= statCard(__('conflicts_detected'), (string) $conflictsCount, __('shift_overlaps'), $conflictsCount > 0 ? '#ef4444' : '#10b981') ?>
        <?= statCard(__('short_rest'), (string) $shortRestCount, __('short_transitions'), $shortRestCount > 0 ? '#f59e0b' : '#10b981') ?>
        <?= statCard(__('max_consec_days'), (string) $maxConsecDays, __('all_employees_combined'), $maxConsecDays > 6 ? '#ef4444' : ($maxConsecDays > 4 ? '#f59e0b' : '#10b981')) ?>
        <?= statCard(__('avg_consec_days'), (string) $avgConsecDays) ?>
    </div>
</div>

<?php endif; ?>
