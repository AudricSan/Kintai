<?php
/** @var \DateTimeImmutable[] $days */
/** @var array[][]            $shifts_by_date  date → shift[] */
/** @var array[]              $types_map        id → shift_type */
/** @var array                $stores_map       id → name */
/** @var array                $users_map        id → name */
/** @var int                  $my_user_id */
/** @var string               $prev_week        YYYY-W */
/** @var string               $next_week        YYYY-W */
/** @var string               $week_label */
/** @var string               $today            Y-m-d */
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('my_planning') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/employee/shifts/day" class="btn btn--ghost btn--sm"><svg class="gantt-icon icon-inline" width="16" height="16" viewBox="0 0 24 24"><rect x="4" y="2" width="2" height="20" fill="#555"/><rect x="10" y="6" width="2" height="16" fill="#555"/><rect x="16" y="10" width="2" height="12" fill="#555"/></svg><?= __('gantt_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/shifts/calendar" class="btn btn--ghost btn--sm">📅 <?= __('calendar_view') ?></a>
        <a href="<?= $BASE_URL ?>/employee/swaps/create" class="btn btn--primary btn--sm">⇄ <?= __('request_swap') ?></a>
    </div>
</div>

<!-- Navigation semaine -->
<div class="card card--mb">
    <div class="card-body week-nav">
        <a href="<?= $BASE_URL ?>/employee/shifts/week?week=<?= htmlspecialchars($prev_week) ?>" class="btn btn--ghost btn--sm">← <?= __('prev_week') ?></a>
        <strong class="week-nav__label"><?= htmlspecialchars($week_label) ?></strong>
        <a href="<?= $BASE_URL ?>/employee/shifts/week?week=<?= htmlspecialchars($next_week) ?>" class="btn btn--ghost btn--sm"><?= __('next_week') ?> →</a>
    </div>
</div>

<!-- Légende -->
<div class="week-legend mb-xs">
    <span class="week-legend-item">
        <span class="week-legend-dot week-legend-dot--mine"></span>
        <?= __('my_shifts') ?>
    </span>
    <span class="week-legend-item">
        <span class="week-legend-dot week-legend-dot--colleague"></span>
        <?= __('colleagues') ?>
    </span>
</div>

<!-- Timeline -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table shifts-table">
            <thead>
                <tr>
                    <th class="col-date"><?= __('date') ?></th>
                    <th><?= __('shifts') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day): ?>
                    <?php
                    $dateStr   = $day->format('Y-m-d');
                    $isToday   = ($dateStr === $today);
                    $dayShifts = $shifts_by_date[$dateStr] ?? [];
                    $dayLabel  = __(strtolower($day->format('l')));

                    // Trier : mes shifts d'abord, puis collègues
                    usort($dayShifts, function($a, $b) use ($my_user_id) {
                        $aMe = (int)($a['user_id'] ?? 0) === $my_user_id ? 0 : 1;
                        $bMe = (int)($b['user_id'] ?? 0) === $my_user_id ? 0 : 1;
                        if ($aMe !== $bMe) return $aMe - $bMe;
                        return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
                    });
                    ?>
                    <tr class="<?= $isToday ? 'tr-today' : '' ?>">
                        <td class="shifts-td-date shifts-td-date--<?= $isToday ? 'today' : 'normal' ?>">
                            <a href="<?= $BASE_URL ?>/employee/shifts/day?date=<?= $dateStr ?>" class="link-plain">
                                <?= $dayLabel ?><br>
                                <span class="text-sm-muted"><?= $day->format('d M') ?></span>
                                <?php if ($isToday): ?>
                                    <br><span class="badge badge--active badge--mt"><?= __('today') ?></span>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td class="shifts-td-cells">
                            <?php if (empty($dayShifts)): ?>
                                <span class="shifts-empty-cell">—</span>
                            <?php else: ?>
                                <div class="shifts-cells">
                                    <?php foreach ($dayShifts as $s): ?>
                                        <?php
                                        $isMe  = (int)($s['user_id'] ?? 0) === $my_user_id;
                                        $tid   = (int)($s['shift_type_id'] ?? 0);
                                        $type  = $types_map[$tid] ?? null;
                                        $color = $isMe ? ($type['color'] ?? '#6366f1') : '#94a3b8';
                                        $name  = $type['name'] ?? 'Shift';
                                        $store = $stores_map[(int)($s['store_id'] ?? 0)] ?? '';
                                        $owner = $isMe ? null : ($users_map[(int)($s['user_id'] ?? 0)] ?? null);
                                        $opacity = $isMe ? '20' : '15';
                                        ?>
                                        <div class="shift-card <?= $isMe ? '' : 'shift-card--other' ?>" style="--shift-color:<?= htmlspecialchars($color) ?>">
                                            <div class="shift-card__type" style="color:<?= htmlspecialchars($color) ?>">
                                                <?= htmlspecialchars($name) ?>
                                                <?php if (!$isMe): ?>
                                                    <span class="shift-card__detail"> · <?= htmlspecialchars($owner ?? '') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="shift-card__detail">
                                                <?= htmlspecialchars(substr($s['start_time'] ?? '', 0, 5)) ?> – <?= htmlspecialchars(substr($s['end_time'] ?? '', 0, 5)) ?>
                                                <?php if ($s['cross_midnight'] ?? false): ?>
                                                    <span title="Passe minuit">+1</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($store): ?>
                                                <div class="shift-card__store"><?= htmlspecialchars($store) ?></div>
                                            <?php endif; ?>
                                            <?php if ($isMe && !empty($s['notes'])): ?>
                                                <div class="shift-card__note"><?= htmlspecialchars($s['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
