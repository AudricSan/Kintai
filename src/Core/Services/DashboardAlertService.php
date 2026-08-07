<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Regroupe les "alertes actionnables" du dashboard admin : shifts à venir non
 * pourvus, employés actifs sans planning cette semaine, demandes en attente
 * depuis trop longtemps, pointages ouverts depuis trop longtemps. Extrait de
 * HomeController pour ne pas alourdir davantage son index() déjà chargé.
 */
final class DashboardAlertService
{
    private const UPCOMING_WINDOW_DAYS  = 7;
    private const STALE_REQUEST_DAYS    = 3;
    private const STALE_TIMECLOCK_HOURS = 12;

    public function __construct(
        private readonly ShiftRepositoryInterface $shifts,
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly TimeclockRepositoryInterface $timeclocks,
    ) {}

    /**
     * @param int[]  $storeIds  stores en scope (déjà filtrés par managed_store_ids)
     * @param array  $storesMap store_id => nom, pour l'affichage
     */
    public function build(array $storeIds, array $storesMap): array
    {
        $storeIds = array_map('intval', $storeIds);

        return [
            'unfilled_shifts'     => $this->unfilledUpcomingShifts($storeIds, $storesMap),
            'users_without_shift' => $this->usersWithoutShiftThisWeek($storeIds, $storesMap),
            'stale_requests'      => $this->staleRequests($storeIds),
            'stale_timeclocks'    => $this->staleTimeclocks($storeIds, $storesMap),
        ];
    }

    private function unfilledUpcomingShifts(array $storeIds, array $storesMap): array
    {
        $result = [];
        for ($d = 0; $d <= self::UPCOMING_WINDOW_DAYS; $d++) {
            $date = date('Y-m-d', strtotime("+{$d} days"));
            foreach ($this->shifts->findAllByDate($date) as $s) {
                $sid = (int) ($s['store_id'] ?? 0);
                if (!in_array($sid, $storeIds, true) || $s['user_id'] !== null || !empty($s['deleted_at'])) {
                    continue;
                }
                $result[] = [
                    'id'         => (int) $s['id'],
                    'store_id'   => $sid,
                    'store_name' => $storesMap[$sid] ?? ('#' . $sid),
                    'shift_date' => (string) $s['shift_date'],
                    'start_time' => (string) $s['start_time'],
                    'end_time'   => (string) $s['end_time'],
                ];
            }
        }
        return $result;
    }

    private function usersWithoutShiftThisWeek(array $storeIds, array $storesMap): array
    {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $scheduledUserIds = [];
        for ($d = 0; $d < 7; $d++) {
            $date = date('Y-m-d', strtotime("{$weekStart} +{$d} days"));
            foreach ($this->shifts->findAllByDate($date) as $s) {
                if ($s['user_id'] !== null) {
                    $scheduledUserIds[(int) $s['user_id']] = true;
                }
            }
        }

        $result = [];
        foreach ($storeIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $su) {
                $uid = (int) $su['user_id'];
                if (isset($scheduledUserIds[$uid]) || isset($result[$uid])) {
                    continue;
                }
                $user = $this->users->findById($uid);
                if ($user === null || (int) ($user['is_active'] ?? 0) !== 1) {
                    continue;
                }
                $result[$uid] = [
                    'id'         => $uid,
                    'name'       => $user['display_name'] ?? $user['email'] ?? ('#' . $uid),
                    'store_id'   => $sid,
                    'store_name' => $storesMap[$sid] ?? ('#' . $sid),
                ];
            }
        }
        return array_values($result);
    }

    private function staleRequests(array $storeIds): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-' . self::STALE_REQUEST_DAYS . ' days'));
        $result = [];

        foreach ($this->timeoffRequests->findAll() as $r) {
            $sid = (int) ($r['store_id'] ?? 0);
            if (!in_array($sid, $storeIds, true) || ($r['status'] ?? '') !== 'pending') {
                continue;
            }
            if (($r['created_at'] ?? '') > $threshold) {
                continue;
            }
            $result[] = [
                'type'       => 'timeoff',
                'id'         => (int) $r['id'],
                'store_id'   => $sid,
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        foreach ($this->swapRequests->findAll() as $r) {
            $sid = (int) ($r['store_id'] ?? 0);
            if (!in_array($sid, $storeIds, true) || ($r['status'] ?? '') !== 'pending') {
                continue;
            }
            if (($r['created_at'] ?? '') > $threshold) {
                continue;
            }
            $result[] = [
                'type'       => 'swap',
                'id'         => (int) $r['id'],
                'store_id'   => $sid,
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        usort($result, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        return $result;
    }

    private function staleTimeclocks(array $storeIds, array $storesMap): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-' . self::STALE_TIMECLOCK_HOURS . ' hours'));
        $result = [];

        foreach ($this->timeclocks->findAll() as $tc) {
            $sid = (int) ($tc['store_id'] ?? 0);
            if (!in_array($sid, $storeIds, true) || !empty($tc['clock_out_time'])) {
                continue;
            }
            if (($tc['clock_in_time'] ?? '') > $threshold) {
                continue;
            }
            $user = $this->users->findById((int) ($tc['user_id'] ?? 0));
            $result[] = [
                'id'            => (int) $tc['id'],
                'store_id'      => $sid,
                'store_name'    => $storesMap[$sid] ?? ('#' . $sid),
                'user_name'     => $user['display_name'] ?? ('#' . (int) ($tc['user_id'] ?? 0)),
                'clock_in_time' => (string) $tc['clock_in_time'],
            ];
        }

        return $result;
    }
}
