<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;

final class StoreStatsService implements StoreStatsServiceInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly UserRepositoryInterface $users,
        private readonly DailyReportRepositoryInterface $dailyReports,
    ) {}

    public function storeStats(int $storeId, int $period, int $minShiftMin = 0, int $maxShiftMin = 0): array
    {
        $since = date('Y-m-d', strtotime("-{$period} days"));
        $today = date('Y-m-d');

        $allShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && $s['shift_date'] >= $since && $s['shift_date'] <= $today
        ));
        $n = count($allShifts);

        $members   = $this->storeUsers->findByStore($storeId);
        $memberIds = array_map(fn($m) => (int) $m['user_id'], $members);

        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u;
        }

        $allTimeoffs = array_filter(
            $this->timeoffRequests->findByStore($storeId),
            fn($t) => ($t['start_date'] ?? '') >= $since
        );

        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');

        $rateCache = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        $durations    = array_map(fn($s) => (int) $s['duration_minutes'], $allShifts);
        $netDurations = array_map(fn($s) => max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']), $allShifts);
        $avgDuration  = $n ? array_sum($durations) / $n / 60 : 0;

        $distShort  = count(array_filter($durations, fn($d) => $d < 240));
        $distMedium = count(array_filter($durations, fn($d) => $d >= 240 && $d <= 480));
        $distLong   = count(array_filter($durations, fn($d) => $d > 480));

        $shiftsByUser = [];
        $daysByUser   = [];
        foreach ($allShifts as $s) {
            $uid = (int) $s['user_id'];
            $shiftsByUser[$uid][] = $s;
            $daysByUser[$uid][$s['shift_date']] = true;
        }

        $avgShiftsPerEmployee = count($shiftsByUser) ? $n / count($shiftsByUser) : 0;
        $avgDaysPerEmployee   = count($daysByUser)
            ? array_sum(array_map('count', $daysByUser)) / count($daysByUser) : 0;

        $openingShifts = count(array_filter($allShifts, fn($s) => $s['start_time'] < '12:00:00'));
        $closingShifts = $n - $openingShifts;

        $gaps = [];
        foreach ($shiftsByUser as $userShifts) {
            if (count($userShifts) < 2) continue;
            usort($userShifts, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            for ($i = 1; $i < count($userShifts); $i++) {
                $gap = strtotime($userShifts[$i]['starts_at']) - strtotime($userShifts[$i - 1]['ends_at']);
                if ($gap > 0) $gaps[] = $gap / 3600;
            }
        }

        $totalNetHours   = array_sum($netDurations) / 60;
        $totalGrossHours = array_sum($durations) / 60;
        $activeDays      = count(array_unique(array_column($allShifts, 'shift_date')));

        $hoursBySlot = array_fill(0, 24, 0);
        foreach ($allShifts as $s) {
            $sh = (int) substr($s['start_time'], 0, 2);
            $eh = (int) substr($s['end_time'],   0, 2);
            if (!(bool) $s['cross_midnight']) {
                for ($h = $sh; $h < $eh; $h++) $hoursBySlot[$h]++;
            } else {
                for ($h = $sh; $h < 24; $h++) $hoursBySlot[$h]++;
                for ($h = 0; $h < $eh; $h++) $hoursBySlot[$h]++;
            }
        }
        $avgEmpPerHour = $activeDays > 0
            ? array_sum($hoursBySlot) / $activeDays / 24 : 0;

        $hoursByUser = [];
        foreach ($allShifts as $s) {
            $uid = (int) $s['user_id'];
            $hoursByUser[$uid] = ($hoursByUser[$uid] ?? 0)
                + max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }

        $hours     = array_values($hoursByUser);
        $hoursN    = count($hours);
        $meanHours = $hoursN ? array_sum($hours) / $hoursN : 0;
        $variance  = $hoursN > 1
            ? array_sum(array_map(fn($h) => ($h - $meanHours) ** 2, $hours)) / $hoursN : 0;
        $stdDev    = sqrt($variance);

        $gini = 0;
        if ($hoursN > 1 && array_sum($hours) > 0) {
            $sorted = $hours;
            sort($sorted);
            $num = 0;
            foreach ($sorted as $i => $h) {
                $num += (2 * ($i + 1) - $hoursN - 1) * $h;
            }
            $gini = abs($num / ($hoursN * array_sum($sorted)));
        }

        arsort($hoursByUser);
        $top20count = max(1, (int) ceil($hoursN * 0.2));
        $top20ratio = array_sum($hoursByUser) > 0
            ? round(array_sum(array_slice($hoursByUser, 0, $top20count)) / array_sum($hoursByUser) * 100, 1) : 0;

        $hoursByType = [];
        foreach ($allShifts as $s) {
            $tid   = $s['shift_type_id'] ?? null;
            $label = ($tid && isset($storeTypesMap[$tid])) ? $storeTypesMap[$tid]['name'] : 'Non défini';
            $hoursByType[$label] = ($hoursByType[$label] ?? 0)
                + max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }
        arsort($hoursByType);

        $modifiedShifts = array_filter($allShifts, fn($s) => !empty($s['updated_at']));
        $modRate        = $n ? round(count($modifiedShifts) / $n * 100, 1) : 0;
        $modDelays      = [];
        foreach ($modifiedShifts as $s) {
            $d = strtotime($s['updated_at']) - strtotime($s['created_at']);
            if ($d > 0) $modDelays[] = $d / 3600;
        }

        $modByDow = array_fill(1, 7, 0);
        foreach ($modifiedShifts as $s) {
            $modByDow[(int) date('N', strtotime($s['updated_at']))]++;
        }

        $shiftsCreatedByManager = [];
        $modByManager           = [];
        foreach ($allShifts as $s) {
            $mid = $s['created_by'] ? (int) $s['created_by'] : 0;
            $shiftsCreatedByManager[$mid] = ($shiftsCreatedByManager[$mid] ?? 0) + 1;
        }
        foreach ($modifiedShifts as $s) {
            $mid = $s['created_by'] ? (int) $s['created_by'] : 0;
            $modByManager[$mid] = ($modByManager[$mid] ?? 0) + 1;
        }
        arsort($shiftsCreatedByManager);
        arsort($modByManager);

        $timeoffsByStatus = ['pending' => 0, 'approved' => 0, 'refused' => 0, 'cancelled' => 0];
        $timeoffsByType   = [];
        $timeoffsByUser   = [];
        $approvedDays     = 0;
        foreach ($allTimeoffs as $t) {
            $timeoffsByStatus[$t['status'] ?? 'pending'] = ($timeoffsByStatus[$t['status'] ?? 'pending'] ?? 0) + 1;
            $timeoffsByType[$t['type'] ?? 'other'] = ($timeoffsByType[$t['type'] ?? 'other'] ?? 0) + 1;
            $timeoffsByUser[(int) $t['user_id']] = ($timeoffsByUser[(int) $t['user_id']] ?? 0) + 1;
            if (($t['status'] ?? '') === 'approved') {
                $d = max(1, (int) round((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
                $approvedDays += $d;
            }
        }
        $absRate = (count($memberIds) > 0 && $period > 0)
            ? round($approvedDays / (count($memberIds) * $period) * 100, 2) : 0;

        $totalCost    = 0.0;
        $costByType   = [];
        $costByUser   = [];
        $costByMonth  = [];
        $hoursByMonth = [];
        foreach ($allShifts as $s) {
            $uid  = (int) $s['user_id'];
            $tid  = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate = $tid
                ? ($rateCache[$uid][$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0))
                : 0.0;
            $h    = max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
            $cost = $rate * $h;
            $totalCost += $cost;
            $label = ($tid && isset($storeTypesMap[$tid])) ? $storeTypesMap[$tid]['name'] : 'Non défini';
            $costByType[$label]       = ($costByType[$label] ?? 0) + $cost;
            $costByUser[$uid]         = ($costByUser[$uid] ?? 0) + $cost;
            $month = substr($s['shift_date'], 0, 7);
            $costByMonth[$month]      = ($costByMonth[$month] ?? 0) + $cost;
            $hoursByMonth[$month]     = ($hoursByMonth[$month] ?? 0) + $h;
        }
        ksort($costByMonth);
        ksort($hoursByMonth);
        arsort($costByType);

        $hoursByDow  = array_fill(1, 7, 0.0);
        $shiftsByDow = array_fill(1, 7, 0);
        $costByDow   = array_fill(1, 7, 0.0);
        $hoursByWeek = [];
        foreach ($allShifts as $s) {
            $uid  = (int) $s['user_id'];
            $tid  = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate = $tid
                ? ($rateCache[$uid][$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0))
                : 0.0;
            $dow  = (int) date('N', strtotime($s['shift_date']));
            $h    = max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
            $cost = $rate * $h;
            $hoursByDow[$dow]  += $h;
            $shiftsByDow[$dow] += 1;
            $costByDow[$dow]   += $cost;
            $week = date('Y-W', strtotime($s['shift_date']));
            $hoursByWeek[$week] = ($hoursByWeek[$week] ?? 0) + $h;
        }
        ksort($hoursByWeek);

        $conflictsCount  = 0;
        $shortRestCount  = 0;
        $maxConsecDays   = 0;
        $consecSum       = 0;
        $consecCount     = 0;
        foreach ($shiftsByUser as $userShifts) {
            usort($userShifts, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            for ($i = 1; $i < count($userShifts); $i++) {
                $prevEnd   = strtotime($userShifts[$i - 1]['ends_at']);
                $currStart = strtotime($userShifts[$i]['starts_at']);
                if ($currStart < $prevEnd) {
                    $conflictsCount++;
                } elseif (($currStart - $prevEnd) < 11 * 3600) {
                    $shortRestCount++;
                }
            }
            $dates = array_keys(($daysByUser[(int) ($userShifts[0]['user_id'])] ?? []));
            sort($dates);
            $consec = 1;
            $maxC   = 1;
            for ($i = 1; $i < count($dates); $i++) {
                if ((strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400 == 1) {
                    $consec++;
                    $maxC = max($maxC, $consec);
                } else {
                    if ($consec > 1) {
                        $consecSum += $consec;
                        $consecCount++;
                    }
                    $consec = 1;
                }
            }
            if ($consec > 1) {
                $consecSum += $consec;
                $consecCount++;
            }
            $maxConsecDays = max($maxConsecDays, count($dates) > 0 ? $maxC : 0);
        }

        $equityScore    = round((1 - $gini) * 100);
        $efficiencyScore = $totalGrossHours > 0 ? round($totalNetHours / $totalGrossHours * 100) : 100;
        $stabilityScore = round(max(0, 100 - $modRate));
        $burnoutRisk    = 0;
        if ($maxConsecDays > 6) $burnoutRisk += 30;
        elseif ($maxConsecDays > 4) $burnoutRisk += 15;
        $burnoutRisk += min(30, $shortRestCount * 5);
        $burnoutRisk += (($hours ? max($hours) : 0) > 50) ? 20 : (($hours ? max($hours) : 0) > 40 ? 10 : 0);
        $burnoutRisk = min(100, $burnoutRisk);

        $prevSince  = date('Y-m-d', strtotime("-{$period} days", strtotime($since)));
        $prevEnd    = date('Y-m-d', strtotime($since . ' -1 day'));
        $prevShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && $s['shift_date'] >= $prevSince && $s['shift_date'] <= $prevEnd
        ));
        $prevN     = count($prevShifts);
        $prevHours = array_sum(array_map(
            fn($s) => max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60,
            $prevShifts
        ));
        $prevModRate = $prevN > 0
            ? round(count(array_filter($prevShifts, fn($s) => !empty($s['updated_at']))) / $prevN * 100, 1) : 0;
        $delta = [
            'n'             => $prevN > 0 ? round(($n - $prevN) / $prevN * 100, 1) : null,
            'totalNetHours' => $prevHours > 0 ? round(($totalNetHours - $prevHours) / $prevHours * 100, 1) : null,
            'modRate'       => $prevModRate > 0 ? round($modRate - $prevModRate, 1) : null,
        ];

        return [
            'since'                  => $since,
            'today'                  => $today,
            'period'                 => $period,
            'n'                      => $n,
            'usersMap'               => $usersMap,
            'memberIds'              => $memberIds,
            'avgDuration'            => round($avgDuration, 2),
            'distShort'              => $distShort,
            'distMedium'             => $distMedium,
            'distLong'               => $distLong,
            'avgShiftsPerEmployee'   => round($avgShiftsPerEmployee, 1),
            'avgDaysPerEmployee'     => round($avgDaysPerEmployee, 1),
            'openingShifts'          => $openingShifts,
            'closingShifts'          => $closingShifts,
            'shortRateShifts'        => $minShiftMin > 0 ? count(array_filter($durations, fn($d) => $d < $minShiftMin)) : null,
            'longRateShifts'         => $maxShiftMin > 0 ? count(array_filter($durations, fn($d) => $d > $maxShiftMin)) : null,
            'avgTimeBetweenShifts'   => $gaps ? round(array_sum($gaps) / count($gaps), 1) : null,
            'totalNetHours'          => round($totalNetHours, 1),
            'totalGrossHours'        => round($totalGrossHours, 1),
            'avgEmpPerHour'          => round($avgEmpPerHour, 2),
            'hoursBySlot'            => $hoursBySlot,
            'activeDays'             => $activeDays,
            'hoursByUser'            => $hoursByUser,
            'meanHours'              => round($meanHours, 1),
            'stdDev'                 => round($stdDev, 1),
            'gini'                   => round($gini, 3),
            'top20ratio'             => $top20ratio,
            'hoursByType'            => $hoursByType,
            'modRate'                => $modRate,
            'avgModDelay'            => $modDelays ? round(array_sum($modDelays) / count($modDelays), 1) : null,
            'modByDow'               => $modByDow,
            'shiftsCreatedByManager' => $shiftsCreatedByManager,
            'modByManager'           => $modByManager,
            'timeoffsByStatus'       => $timeoffsByStatus,
            'timeoffsByType'         => $timeoffsByType,
            'timeoffsByUser'         => $timeoffsByUser,
            'absRate'                => $absRate,
            'approvedDays'           => $approvedDays,
            'totalCost'              => round($totalCost, 2),
            'avgCostPerShift'        => $n ? round($totalCost / $n, 2) : 0,
            'avgCostPerHour'         => $totalNetHours > 0 ? round($totalCost / $totalNetHours, 2) : 0,
            'costByType'             => $costByType,
            'costByUser'             => $costByUser,
            'costByMonth'            => $costByMonth,
            'hoursByMonth'           => $hoursByMonth,
            'hoursByDow'             => $hoursByDow,
            'shiftsByDow'            => $shiftsByDow,
            'costByDow'              => $costByDow,
            'hoursByWeek'            => $hoursByWeek,
            'dowLabels'              => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'conflictsCount'         => $conflictsCount,
            'maxConsecDays'          => $maxConsecDays,
            'avgConsecDays'          => $consecCount ? round($consecSum / $consecCount, 1) : 0,
            'shortRestCount'         => $shortRestCount,
            'equityScore'            => $equityScore,
            'efficiencyScore'        => $efficiencyScore,
            'stabilityScore'         => $stabilityScore,
            'burnoutRisk'            => $burnoutRisk,
            'delta'                  => $delta,
        ];
    }

    public function storeStatsExportData(int $storeId, int $period): array
    {
        $since = date('Y-m-d', strtotime("-{$period} days"));
        $today = date('Y-m-d');

        $allShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && $s['shift_date'] >= $since && $s['shift_date'] <= $today
        ));
        $n = count($allShifts);

        $members   = $this->storeUsers->findByStore($storeId);
        $memberIds = array_map(fn($m) => (int) $m['user_id'], $members);

        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u;
        }

        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $rateCache     = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        $durations       = array_map(fn($s) => (int) $s['duration_minutes'], $allShifts);
        $avgDuration     = $n ? array_sum($durations) / $n / 60 : 0;
        $totalGrossHours = array_sum($durations) / 60;
        $totalNetHours   = 0.0;
        $activeDays      = count(array_unique(array_column($allShifts, 'shift_date')));

        $hoursByUser  = [];
        $costByUser   = [];
        $hoursByType  = [];
        $hoursByMonth = [];
        $costByMonth  = [];
        $hoursByWeek  = [];

        foreach ($allShifts as $s) {
            $uid   = (int) $s['user_id'];
            $tid   = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate  = $tid ? ($rateCache[$uid][$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0)) : 0.0;
            $h     = max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
            $cost  = $rate * $h;
            $label = ($tid && isset($storeTypesMap[$tid])) ? $storeTypesMap[$tid]['name'] : 'Non défini';
            $month = substr($s['shift_date'], 0, 7);
            $week  = date('Y-W', strtotime($s['shift_date']));

            $totalNetHours       += $h;
            $hoursByUser[$uid]    = ($hoursByUser[$uid] ?? 0) + $h;
            $costByUser[$uid]     = ($costByUser[$uid] ?? 0) + $cost;
            $hoursByType[$label]  = ($hoursByType[$label] ?? 0) + $h;
            $hoursByMonth[$month] = ($hoursByMonth[$month] ?? 0) + $h;
            $costByMonth[$month]  = ($costByMonth[$month] ?? 0) + $cost;
            $hoursByWeek[$week]   = ($hoursByWeek[$week] ?? 0) + $h;
        }
        ksort($hoursByMonth); ksort($costByMonth); ksort($hoursByWeek);
        arsort($hoursByUser); arsort($hoursByType);

        $modCount  = count(array_filter($allShifts, fn($s) => !empty($s['updated_at'])));
        $modRate   = $n ? round($modCount / $n * 100, 1) : 0;
        $hoursArr  = array_values($hoursByUser);
        $hoursN    = count($hoursArr);
        $gini      = 0;
        if ($hoursN > 1 && array_sum($hoursArr) > 0) {
            $sorted = $hoursArr; sort($sorted); $num = 0;
            foreach ($sorted as $i => $h) { $num += (2 * ($i + 1) - $hoursN - 1) * $h; }
            $gini = abs($num / ($hoursN * array_sum($sorted)));
        }
        $equityScore     = round((1 - $gini) * 100);
        $efficiencyScore = $totalGrossHours > 0 ? round($totalNetHours / $totalGrossHours * 100) : 100;
        $stabilityScore  = round(max(0, 100 - $modRate));

        return [
            'since'           => $since,
            'today'           => $today,
            'period'          => $period,
            'n'               => $n,
            'usersMap'        => $usersMap,
            'memberIds'       => $memberIds,
            'avgDuration'     => $avgDuration,
            'totalNetHours'   => $totalNetHours,
            'totalGrossHours' => $totalGrossHours,
            'activeDays'      => $activeDays,
            'hoursByUser'     => $hoursByUser,
            'costByUser'      => $costByUser,
            'hoursByType'     => $hoursByType,
            'hoursByMonth'    => $hoursByMonth,
            'costByMonth'     => $costByMonth,
            'hoursByWeek'     => $hoursByWeek,
            'modRate'         => $modRate,
            'equityScore'     => $equityScore,
            'efficiencyScore' => $efficiencyScore,
            'stabilityScore'  => $stabilityScore,
        ];
    }

    public function storeProfitability(int $storeId, string $from, string $to): array
    {
        $reports = array_values(array_filter(
            $this->dailyReports->findByStoreAndDateRange($storeId, $from, $to),
            fn($r) => empty($r['deleted_at'])
        ));
        usort($reports, fn($a, $b) => strcmp($a['report_date'], $b['report_date']));

        $allShifts     = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && $s['shift_date'] >= $from && $s['shift_date'] <= $to
        ));
        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $members       = $this->storeUsers->findByStore($storeId);
        $memberIds     = array_map(fn($m) => (int) $m['user_id'], $members);
        $rateCache     = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }
        $payrollByDate = [];
        $hoursByDate   = [];
        foreach ($allShifts as $s) {
            $uid  = (int) $s['user_id'];
            $tid  = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate = $tid ? ($rateCache[$uid][$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0)) : 0.0;
            $h    = max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
            $date = $s['shift_date'];
            $payrollByDate[$date] = ($payrollByDate[$date] ?? 0.0) + $rate * $h;
            $hoursByDate[$date]   = ($hoursByDate[$date]   ?? 0.0) + $h;
        }

        $rows              = [];
        $totalSales        = 0.0;
        $totalLaborMnl     = 0.0;
        $totalLaborCalc    = 0.0;
        $totalCustomers    = 0;
        $salesByMonth      = [];
        $laborMnlByMonth   = [];
        $laborCalcByMonth  = [];
        $lcPctByMonth      = [];
        $customersByMonth  = [];

        foreach ($reports as $r) {
            $date      = $r['report_date'];
            $sales     = (float) $r['sales_total'];
            $labor     = (float) $r['labor_cost'];
            $calc      = $payrollByDate[$date] ?? 0.0;
            $customers = (int) $r['customer_count'];
            $lcPct     = $sales > 0 ? round($labor / $sales * 100, 1) : null;
            $lcPctCalc = $sales > 0 ? round($calc  / $sales * 100, 1) : null;

            $rows[] = [
                'date'        => $date,
                'status'      => $r['status'] ?? 'draft',
                'sales'       => $sales,
                'labor_mnl'   => $labor,
                'labor_calc'  => $calc,
                'hours'       => round($hoursByDate[$date] ?? 0.0, 1),
                'customers'   => $customers,
                'rev_per_cust'=> $customers > 0 ? round($sales / $customers, 2) : null,
                'lc_pct'      => $lcPct,
                'lc_pct_calc' => $lcPctCalc,
                'variance'    => round($labor - $calc, 2),
            ];

            $totalSales     += $sales;
            $totalLaborMnl  += $labor;
            $totalLaborCalc += $calc;
            $totalCustomers += $customers;

            $month = substr($date, 0, 7);
            $salesByMonth[$month]     = ($salesByMonth[$month]     ?? 0.0) + $sales;
            $laborMnlByMonth[$month]  = ($laborMnlByMonth[$month]  ?? 0.0) + $labor;
            $laborCalcByMonth[$month] = ($laborCalcByMonth[$month] ?? 0.0) + $calc;
            if (($salesByMonth[$month] ?? 0) > 0) {
                $lcPctByMonth[$month] = round($laborMnlByMonth[$month] / $salesByMonth[$month] * 100, 1);
            }
            $customersByMonth[$month] = ($customersByMonth[$month] ?? 0) + $customers;
        }
        ksort($salesByMonth);    ksort($laborMnlByMonth);  ksort($lcPctByMonth);
        ksort($laborCalcByMonth); ksort($customersByMonth);

        $salesByDay  = []; $lcPctByDay = [];
        foreach ($rows as $row) {
            $salesByDay[$row['date']] = $row['sales'];
            $lcPctByDay[$row['date']] = $row['lc_pct'];
        }

        $avgLaborPct  = $totalSales > 0 ? round($totalLaborMnl / $totalSales * 100, 1) : null;
        $revPerCust   = $totalCustomers > 0 ? round($totalSales / $totalCustomers, 2) : null;
        $reportCount  = count(array_filter($rows, fn($row) => $row['sales'] > 0));

        return [
            'rows'             => $rows,
            'totalSales'       => round($totalSales, 2),
            'totalLaborMnl'    => round($totalLaborMnl, 2),
            'totalLaborCalc'   => round($totalLaborCalc, 2),
            'totalCustomers'   => $totalCustomers,
            'avgLaborPct'      => $avgLaborPct,
            'revPerCust'       => $revPerCust,
            'variance'         => round($totalLaborMnl - $totalLaborCalc, 2),
            'reportCount'      => $reportCount,
            'salesByMonth'     => $salesByMonth,
            'laborMnlByMonth'  => $laborMnlByMonth,
            'laborCalcByMonth' => $laborCalcByMonth,
            'lcPctByMonth'     => $lcPctByMonth,
            'customersByMonth' => $customersByMonth,
            'salesByDay'       => $salesByDay,
            'lcPctByDay'       => $lcPctByDay,
        ];
    }

    public function employeeStats(int $storeId, int $userId, int $period): array
    {
        $since = date('Y-m-d', strtotime("-{$period} days"));
        $today = date('Y-m-d');

        $allShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && (int) $s['user_id'] === $userId
        ));

        $periodShifts = array_values(array_filter(
            $allShifts,
            fn($s) => $s['shift_date'] >= $since && $s['shift_date'] <= $today
        ));

        $since12m   = date('Y-m-d', strtotime('-365 days'));
        $shifts12m  = array_values(array_filter(
            $allShifts,
            fn($s) => $s['shift_date'] >= $since12m && $s['shift_date'] <= $today
        ));

        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $personalRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
        }

        $grossMin = 0;
        $netMin   = 0;
        $cost     = 0.0;
        $anyRate  = false;
        $workDays = [];

        foreach ($periodShifts as $s) {
            $dur  = (int) $s['duration_minutes'];
            $paus = (int) $s['pause_minutes'];
            $net  = max(0, $dur - $paus);
            $grossMin += $dur;
            $netMin   += $net;
            $workDays[$s['shift_date']] = true;

            $tid  = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate = $tid ? ($personalRates[$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0)) : 0.0;
            if ($rate > 0) $anyRate = true;
            $cost += $rate * ($net / 60);
        }

        $dateKeys  = array_keys($workDays);
        sort($dateKeys);
        $maxConsec = $dateKeys ? 1 : 0;
        $consec    = 1;
        for ($i = 1; $i < count($dateKeys); $i++) {
            if ((strtotime($dateKeys[$i]) - strtotime($dateKeys[$i - 1])) / 86400 == 1) {
                $consec++;
                $maxConsec = max($maxConsec, $consec);
            } else {
                $consec = 1;
            }
        }

        $absDays = 0;
        foreach ($this->timeoffRequests->findByStore($storeId) as $t) {
            if ((int) ($t['user_id'] ?? 0) !== $userId) continue;
            if (($t['status'] ?? '') !== 'approved') continue;
            if (($t['start_date'] ?? '') < $since) continue;
            $absDays += max(1, (int) round((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
        }

        $swapCount = 0;
        foreach ($this->swapRequests->findByStore($storeId) as $sw) {
            if (((int) ($sw['requester_id'] ?? 0) === $userId || (int) ($sw['target_id'] ?? 0) === $userId)
                && ($sw['created_at'] ?? '') >= $since
            ) {
                $swapCount++;
            }
        }

        $monthlyHours = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthlyHours[date('Y-m', strtotime("-{$i} months"))] = 0.0;
        }
        foreach ($shifts12m as $s) {
            $ym = substr($s['shift_date'], 0, 7);
            if (isset($monthlyHours[$ym])) {
                $monthlyHours[$ym] += max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
            }
        }

        $typeHours = [];
        foreach ($periodShifts as $s) {
            $tid   = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $label = $tid ? ($storeTypesMap[$tid]['name'] ?? 'Type ' . $tid) : 'Sans type';
            $typeHours[$label] = ($typeHours[$label] ?? 0.0)
                + max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }
        arsort($typeHours);

        $dowLabels = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $dowBuckets = array_fill(0, 7, 0.0);
        foreach ($periodShifts as $s) {
            $dow = (int) (new \DateTime($s['shift_date']))->format('w');
            $dowBuckets[$dow] += max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }
        $dowChart = array_combine($dowLabels, $dowBuckets);

        $recentShifts = array_slice(
            array_reverse(array_filter($allShifts, fn($s) => $s['shift_date'] <= $today)),
            0,
            15
        );

        return [
            'since'        => $since,
            'today'        => $today,
            'period'       => $period,
            'storeTypesMap' => $storeTypesMap,
            'totalShifts'  => count($periodShifts),
            'grossHours'   => round($grossMin / 60, 1),
            'netHours'     => round($netMin / 60, 1),
            'cost'         => round($cost, 2),
            'anyRate'      => $anyRate,
            'workDays'     => count($workDays),
            'absDays'      => $absDays,
            'maxConsec'    => $maxConsec,
            'swapCount'    => $swapCount,
            'monthlyHours' => $monthlyHours,
            'typeHours'    => $typeHours,
            'dowChart'     => $dowChart,
            'recentShifts' => $recentShifts,
        ];
    }

    public function employeeReport(int $storeId, int $period): array
    {
        $since = date('Y-m-d', strtotime("-{$period} days"));
        $today = date('Y-m-d');

        $allShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && $s['shift_date'] >= $since && $s['shift_date'] <= $today
        ));

        $members   = $this->storeUsers->findByStore($storeId);
        $memberIds = array_map(fn($m) => (int) $m['user_id'], $members);

        $membersMap = [];
        foreach ($members as $m) {
            $membersMap[(int) $m['user_id']] = $m;
        }

        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u;
        }

        $allTimeoffs = array_filter(
            $this->timeoffRequests->findByStore($storeId),
            fn($t) => ($t['start_date'] ?? '') >= $since && ($t['status'] ?? '') === 'approved'
        );

        $allSwaps = array_filter(
            $this->swapRequests->findByStore($storeId),
            fn($sw) => ($sw['created_at'] ?? '') >= $since
        );

        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');

        $rateCache = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        $shiftsByUser = [];
        foreach ($allShifts as $s) {
            $shiftsByUser[(int) $s['user_id']][] = $s;
        }

        $absencesByUser = [];
        foreach ($allTimeoffs as $t) {
            $uid  = (int) $t['user_id'];
            $days = max(1, (int) round((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
            $absencesByUser[$uid] = ($absencesByUser[$uid] ?? 0) + $days;
        }

        $swapsByUser = [];
        foreach ($allSwaps as $sw) {
            $req = (int) $sw['requester_id'];
            $tgt = (int) $sw['target_id'];
            if (in_array($req, $memberIds, true)) {
                $swapsByUser[$req] = ($swapsByUser[$req] ?? 0) + 1;
            }
            if ($tgt !== $req && in_array($tgt, $memberIds, true)) {
                $swapsByUser[$tgt] = ($swapsByUser[$tgt] ?? 0) + 1;
            }
        }

        $employeeStats = [];
        foreach ($memberIds as $uid) {
            $userShifts = $shiftsByUser[$uid] ?? [];
            $n = count($userShifts);

            $grossMin = 0;
            $netMin   = 0;
            $cost     = 0.0;
            $anyRate  = isset($rateCache[$uid]) && count($rateCache[$uid]) > 0;
            $days     = [];

            foreach ($userShifts as $s) {
                $dur   = (int) $s['duration_minutes'];
                $pause = (int) $s['pause_minutes'];
                $net   = max(0, $dur - $pause);
                $grossMin += $dur;
                $netMin   += $net;
                $days[$s['shift_date']] = true;

                $tid  = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
                $rate = $tid
                    ? ($rateCache[$uid][$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0))
                    : 0.0;
                if ($rate > 0) {
                    $anyRate = true;
                }
                $cost += $rate * ($net / 60);
            }

            $dateKeys  = array_keys($days);
            sort($dateKeys);
            $maxConsec = $dateKeys ? 1 : 0;
            $consec    = 1;
            for ($i = 1; $i < count($dateKeys); $i++) {
                if ((strtotime($dateKeys[$i]) - strtotime($dateKeys[$i - 1])) / 86400 == 1) {
                    $consec++;
                    $maxConsec = max($maxConsec, $consec);
                } else {
                    $consec = 1;
                }
            }

            $employeeStats[$uid] = [
                'shifts'      => $n,
                'gross_hours' => round($grossMin / 60, 1),
                'net_hours'   => round($netMin / 60, 1),
                'cost'        => round($cost, 2),
                'work_days'   => count($days),
                'abs_days'    => $absencesByUser[$uid] ?? 0,
                'swaps'       => $swapsByUser[$uid] ?? 0,
                'max_consec'  => $maxConsec,
                'has_rate'    => $anyRate,
            ];
        }

        uasort($employeeStats, fn($a, $b) => $b['net_hours'] <=> $a['net_hours']);

        $activeStats     = array_filter($employeeStats, fn($e) => $e['shifts'] > 0);
        $activeCount     = count($activeStats);
        $totalShifts     = array_sum(array_column($employeeStats, 'shifts'));
        $totalNetHours   = array_sum(array_column($employeeStats, 'net_hours'));
        $totalGrossHours = array_sum(array_column($employeeStats, 'gross_hours'));
        $totalCost       = array_sum(array_column($employeeStats, 'cost'));
        $totalAbsDays    = array_sum(array_column($employeeStats, 'abs_days'));
        $anyHasRate      = !empty(array_filter($employeeStats, fn($e) => $e['has_rate']));

        return [
            'since'           => $since,
            'today'           => $today,
            'period'          => $period,
            'usersMap'        => $usersMap,
            'membersMap'      => $membersMap,
            'memberIds'       => $memberIds,
            'employeeStats'   => $employeeStats,
            'storeTypesMap'   => $storeTypesMap,
            'totalShifts'     => $totalShifts,
            'totalNetHours'   => round($totalNetHours, 1),
            'totalGrossHours' => round($totalGrossHours, 1),
            'totalCost'       => round($totalCost, 2),
            'totalAbsDays'    => $totalAbsDays,
            'activeCount'     => $activeCount,
            'anyHasRate'      => $anyHasRate,
        ];
    }

    public function buildPayslipData(int $storeId, int $userId, string $from, string $to): array
    {
        $since = $from;
        $today = $to;

        $shifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at'])
                && (int) $s['user_id'] === $userId
                && $s['shift_date'] >= $since
                && $s['shift_date'] <= $today
        ));
        usort($shifts, fn($a, $b) => strcmp($a['shift_date'] . $a['start_time'], $b['shift_date'] . $b['start_time']));

        $storeTypesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $personalRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
        }

        $shiftRows     = [];
        $totalGrossMin = 0;
        $totalNetMin   = 0;
        $totalCost     = 0.0;
        $anyRate       = false;

        foreach ($shifts as $s) {
            $tid      = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $rate     = $tid
                ? ($personalRates[$tid] ?? (float) ($storeTypesMap[$tid]['hourly_rate'] ?? 0))
                : 0.0;
            $grossMin = (int) $s['duration_minutes'];
            $pauseMin = (int) $s['pause_minutes'];
            $netMin   = max(0, $grossMin - $pauseMin);
            $cost     = $rate * ($netMin / 60);
            if ($rate > 0) $anyRate = true;
            $totalGrossMin += $grossMin;
            $totalNetMin   += $netMin;
            $totalCost     += $cost;

            $shiftRows[] = [
                'date'      => $s['shift_date'],
                'type'      => $tid ? ($storeTypesMap[$tid]['name'] ?? '—') : '—',
                'start'     => substr($s['start_time'] ?? '00:00', 0, 5),
                'end'       => substr($s['end_time'] ?? '00:00', 0, 5),
                'gross_min' => $grossMin,
                'pause_min' => $pauseMin,
                'net_min'   => $netMin,
                'rate'      => $rate,
                'cost'      => round($cost, 2),
                'has_rate'  => $rate > 0,
            ];
        }

        $deductionSettings  = $this->stores->getDeductionSettings($storeId);
        $membership         = $this->storeUsers->findMembership($storeId, $userId);
        $membershipId       = (int) ($membership['id'] ?? 0);
        $subjectToDeductions = $membershipId > 0
            ? $this->storeUsers->getSubjectToDeductions($membershipId)
            : false;
        $deductions         = [];
        $totalDeductions    = 0.0;
        $netPay             = $totalCost;

        if (!empty($deductionSettings['enabled']) && $subjectToDeductions) {
            $items = [
                'health_insurance'     => ['label_key' => 'ded_health_insurance',     'rate_key' => 'health_insurance_rate'],
                'pension'              => ['label_key' => 'ded_pension',              'rate_key' => 'pension_rate'],
                'employment_insurance' => ['label_key' => 'ded_employment_insurance', 'rate_key' => 'employment_insurance_rate'],
                'income_tax'           => ['label_key' => 'ded_income_tax',           'rate_key' => 'income_tax_rate'],
            ];
            foreach ($items as $key => $cfg) {
                $rate = (float) ($deductionSettings[$cfg['rate_key']] ?? 0);
                if ($rate > 0) {
                    $amount = round($totalCost * $rate / 100, 2);
                    $deductions[$key] = [
                        'label_key' => $cfg['label_key'],
                        'amount'    => $amount,
                        'rate'      => $rate,
                        'is_flat'   => false,
                    ];
                    $totalDeductions += $amount;
                }
            }
            $residentTax = (float) ($deductionSettings['resident_tax_monthly'] ?? 0);
            if ($residentTax > 0) {
                $deductions['resident_tax'] = [
                    'label_key' => 'ded_resident_tax',
                    'amount'    => round($residentTax, 2),
                    'rate'      => $residentTax,
                    'is_flat'   => true,
                ];
                $totalDeductions += $residentTax;
            }
            $totalDeductions = round($totalDeductions, 2);
            $netPay          = round($totalCost - $totalDeductions, 2);
        }

        return [
            'since'             => $since,
            'today'             => $today,
            'shiftRows'         => $shiftRows,
            'totalGrossMin'     => $totalGrossMin,
            'totalNetMin'       => $totalNetMin,
            'totalCost'         => round($totalCost, 2),
            'anyRate'           => $anyRate,
            'deductions'        => $deductions,
            'totalDeductions'   => $totalDeductions,
            'netPay'            => $netPay,
            'deductionsEnabled' => !empty($deductionSettings['enabled']) && $subjectToDeductions,
        ];
    }

    public function multiStoreComparison(array $storeIds, int $period): array
    {
        $rows = [];
        foreach ($storeIds as $storeId) {
            try {
                $stats = $this->storeStats($storeId, $period);
            } catch (\Throwable) {
                continue;
            }
            $rows[] = [
                'store_id'       => $storeId,
                'store_name'     => $stats['store']['name'] ?? '',
                'total_shifts'   => $stats['planning']['totalShifts'] ?? 0,
                'total_hours'    => round(($stats['planning']['totalMinutes'] ?? 0) / 60, 1),
                'avg_hourly'     => $stats['financial']['avgHourlyCost'] ?? 0,
                'total_cost'     => $stats['financial']['totalCost'] ?? 0,
                'employee_count' => $stats['hr']['activeEmployees'] ?? 0,
                'stability'      => $stats['stability']['stabilityScore'] ?? null,
                'efficiency'     => $stats['efficiency']['efficiencyScore'] ?? null,
                'equity'         => $stats['equity']['equityScore'] ?? null,
            ];
        }
        return $rows;
    }
}
