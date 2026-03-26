<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;

final class EmployeeController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly AuditLogger $auditLogger,
    ) {}

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);
        $today  = date('Y-m-d');

        $myShifts  = $this->shifts->findByUser($userId);
        $myTimeoff = $this->timeoffRequests->findByUser($userId);

        $shiftsToday = array_values(array_filter(
            $myShifts,
            fn($s) => ($s['shift_date'] ?? '') === $today
        ));

        $upcomingShifts = array_values(array_filter(
            $myShifts,
            fn($s) => ($s['shift_date'] ?? '') > $today
        ));
        usort($upcomingShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));
        $upcomingShifts = array_slice($upcomingShifts, 0, 5);

        $pendingTimeoff = array_values(array_filter(
            $myTimeoff,
            fn($r) => ($r['status'] ?? '') === 'pending'
        ));

        $sentSwaps    = $this->swapRequests->findByRequester($userId);
        $received     = $this->swapRequests->findByTarget($userId);
        $pendingSwaps = array_values(array_filter(
            $received,
            fn($s) => ($s['status'] ?? '') === 'pending' && ($s['peer_accepted_at'] ?? null) === null
        ));

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }

        return Response::html($this->view->render('employee.dashboard', [
            'title'           => 'Mon planning',
            'user'            => $user,
            'shifts_today'    => $shiftsToday,
            'upcoming'        => $upcomingShifts,
            'pending_timeoff' => $pendingTimeoff,
            'my_swaps'        => $sentSwaps,
            'pending_swaps'   => $pendingSwaps,
            'stores_map'      => $storesMap,
        ], 'layout.app'));
    }

    // -------------------------------------------------------------------------
    // Timeline hebdomadaire
    // -------------------------------------------------------------------------

    public function shifts(Request $request): Response
    {
        // Redirige vers la vue Timeline (3 jours par défaut à partir d'aujourd'hui)
        return Response::redirect($this->base() . '/employee/shifts/day?start=' . date('Y-m-d') . '&view=3days');
    }

    public function shiftsCalendar(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        // Mois affiché
        $monthParam = (string) ($request->query('month') ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = date('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $monthParam));
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
        $nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));
        $monthLabel = (new \DateTimeImmutable($monthStart))->format('F Y');

        // Collègues du même store
        $memberships = $this->storeUsers->findByUser($userId);
        $myStoreIds  = array_unique(array_map(fn($m) => (int) $m['store_id'], $memberships));
        $colleagueIds = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid !== $userId) $colleagueIds[$mid] = true;
            }
        }

        // Shifts du mois (employé + collègues pour contexte)
        $allUserIds   = array_merge([$userId], array_keys($colleagueIds));
        $shiftsByDate = [];
        foreach ($allUserIds as $uid) {
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d >= $monthStart && $d <= $monthEnd) {
                    $shiftsByDate[$d][] = $s;
                }
            }
        }

        // Map couleurs (la couleur de l'employé courant)
        $usersColour = [];
        foreach ($this->users->findAll() as $u) {
            $usersColour[(int) $u['id']] = $u['color'] ?? '#6366f1';
        }

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }
        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '';
        }

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.shifts-calendar', [
            'title'          => __('my_planning'),
            'year'           => $year,
            'month'          => $month,
            'month_label'    => $monthLabel,
            'month_start'    => $monthStart,
            'month_end'      => $monthEnd,
            'prev_month'     => $prevMonth,
            'next_month'     => $nextMonth,
            'today'          => date('Y-m-d'),
            'shifts_by_date' => $shiftsByDate,
            'my_user_id'     => $userId,
            'users_colour'   => $usersColour,
            'types_map'      => $typesMap,
            'stores_map'     => $storesMap,
            'is_manager_view'=> false,
        ], 'layout.app'));
    }

    public function shiftsWeek(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $weekParam = $request->query('week', '');
        if ($weekParam && preg_match('/^\d{4}-\d{1,2}$/', (string) $weekParam)) {
            [$y, $w] = explode('-', (string) $weekParam);
            $monday = (new \DateTimeImmutable())->setISODate((int) $y, (int) $w, 1);
        } else {
            $now    = new \DateTimeImmutable();
            $monday = $now->setISODate((int) $now->format('o'), (int) $now->format('W'), 1);
        }

        $sunday   = $monday->modify('+6 days');
        $prevWeek = $monday->modify('-7 days')->format('o-W');
        $nextWeek = $monday->modify('+7 days')->format('o-W');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $monday->modify("+{$i} days");
        }

        $weekStart = $monday->format('Y-m-d');
        $weekEnd   = $sunday->format('Y-m-d');

        // Collègues du même store
        $memberships = $this->storeUsers->findByUser($userId);
        $myStoreIds  = array_unique(array_map(fn($m) => (int) $m['store_id'], $memberships));

        $colleagueIds = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid !== $userId) {
                    $colleagueIds[$mid] = true;
                }
            }
        }

        // Charger tous les shifts de la semaine (moi + collègues)
        $allUserIds   = array_merge([$userId], array_keys($colleagueIds));
        $shiftsByDate = [];
        foreach ($allUserIds as $uid) {
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d >= $weekStart && $d <= $weekEnd) {
                    $shiftsByDate[$d][] = $s;
                }
            }
        }

        // Construire la map des noms pour l'affichage
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $usersMap[(int) $u['id']] = $name ?: ($u['email'] ?? '#' . $u['id']);
        }

        $typesMap  = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }
        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
        }

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.shifts', [
            'title'          => 'Mon planning',
            'days'           => $days,
            'shifts_by_date' => $shiftsByDate,
            'types_map'      => $typesMap,
            'stores_map'     => $storesMap,
            'users_map'      => $usersMap,
            'my_user_id'     => $userId,
            'prev_week'      => $prevWeek,
            'next_week'      => $nextWeek,
            'week_label'     => 'Semaine ' . $monday->format('W') . ' · ' . $monday->format('d M') . ' – ' . $sunday->format('d M Y'),
            'today'          => date('Y-m-d'),
        ], 'layout.app'));
    }

    // -------------------------------------------------------------------------
    // Timeline jour (Gantt)
    // -------------------------------------------------------------------------

    public function shiftDay(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        // Mode d'affichage : '3days' (défaut) ou 'week'
        $viewMode = $request->query('view', '3days');
        if (!in_array($viewMode, ['3days', 'week'], true)) {
            $viewMode = '3days';
        }

        // Jour de départ
        $startParam = (string) $request->query('start', date('Y-m-d'));
        $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startParam)
            ? new \DateTimeImmutable($startParam)
            : new \DateTimeImmutable();

        // Calcul de la plage de jours
        if ($viewMode === 'week') {
            // Lundi de la semaine ISO contenant $anchor
            $firstDay = $anchor->setISODate((int) $anchor->format('o'), (int) $anchor->format('W'), 1);
            $numDays  = 7;
        } else {
            $firstDay = $anchor;
            $numDays  = 3;
        }

        $days = [];
        for ($i = 0; $i < $numDays; $i++) {
            $days[] = $firstDay->modify("+{$i} days");
        }

        $rangeStart = $days[0]->format('Y-m-d');
        $rangeEnd   = $days[$numDays - 1]->format('Y-m-d');
        $prevStart  = $firstDay->modify("-{$numDays} days")->format('Y-m-d');
        $nextStart  = $firstDay->modify("+{$numDays} days")->format('Y-m-d');

        // Collègues du même store
        $memberships = $this->storeUsers->findByUser($userId);
        $myStoreIds  = array_unique(array_map(fn($m) => (int) $m['store_id'], $memberships));

        // Peut gérer les shifts (admin global ou manager/admin d'au moins un store)
        $canManage = !empty($user['is_admin']);
        if (!$canManage) {
            foreach ($memberships as $m) {
                if (in_array($m['role'] ?? '', ['admin', 'manager'], true)) {
                    $canManage = true;
                    break;
                }
            }
        }

        $colleagueIds = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid !== $userId) {
                    $colleagueIds[$mid] = true;
                }
            }
        }

        // Shifts de la plage : moi + collègues, groupés par date puis user
        $allUserIds       = array_merge([$userId], array_keys($colleagueIds));
        $shiftsByDateUser = []; // date → uid → shift[]
        foreach ($allUserIds as $uid) {
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d >= $rangeStart && $d <= $rangeEnd) {
                    $shiftsByDateUser[$d][$uid][] = $s;
                }
            }
        }

        $usersMap      = [];
        $userColorMap  = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $usersMap[(int) $u['id']]     = $name ?: ($u['email'] ?? '#' . $u['id']);
            $userColorMap[(int) $u['id']] = $u['color'] ?? '#6366f1';
        }

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        // Taux horaires par user+type et devise par user (pour le modal timeline)
        $ratesMap    = []; // uid → shift_type_id → hourly_rate
        $currencyMap = []; // uid → currency string
        foreach ($allUserIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $ratesMap[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
            $userMemberships = $this->storeUsers->findByUser($uid);
            $currency = 'JPY';
            if (!empty($userMemberships)) {
                $st = $this->stores->findById((int) $userMemberships[0]['store_id']);
                $currency = strtoupper(trim($st['currency'] ?? 'JPY'));
            }
            $currencyMap[$uid] = $currency;
        }

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.shifts-day', [
            'title'              => 'Mon planning',
            'days'               => $days,
            'period_mode'        => $viewMode,
            'shifts_by_date_user'=> $shiftsByDateUser,
            'all_user_ids'       => $allUserIds,
            'users_map'          => $usersMap,
            'user_color_map'     => $userColorMap,
            'types_map'          => $typesMap,
            'my_user_id'         => $userId,
            'today'              => date('Y-m-d'),
            'prev_start'         => $prevStart,
            'next_start'         => $nextStart,
            'can_manage'         => $canManage,
            'rates_map'          => $ratesMap,
            'currency_map'       => $currencyMap,
        ], 'layout.app'));
    }

    // -------------------------------------------------------------------------
    // Congés
    // -------------------------------------------------------------------------

    public function timeoff(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $requests = $this->timeoffRequests->findByUser($userId);
        usort($requests, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.timeoff', [
            'title'    => 'Mes congés',
            'requests' => $requests,
        ], 'layout.app'));
    }

    public function storeTimeoff(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $memberships = $this->storeUsers->findByUser($userId);
        $storeId     = $memberships ? (int) $memberships[0]['store_id'] : 0;

        $validTypes = ['vacation', 'sick', 'personal', 'unpaid', 'other'];
        $type       = $request->post('type', 'vacation');
        if (!in_array($type, $validTypes, true)) {
            $type = 'vacation';
        }
        $startDate = $request->post('start_date', '');
        $endDate   = $request->post('end_date', $startDate) ?: $startDate;
        $reason    = $request->post('reason', '') ?: null;

        $savedTimeoff = $this->timeoffRequests->save([
            'store_id'   => $storeId,
            'user_id'    => $userId,
            'type'       => $type,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => $reason,
            'status'     => 'pending',
        ]);

        $this->auditLogger->log($request, 'timeoff.created', 'timeoff_request', (int) ($savedTimeoff['id'] ?? 0), [
            'type' => $type, 'start' => $startDate, 'end' => $endDate,
        ], $storeId ?: null);
        return Response::redirect($this->base() . '/employee/timeoff?success=created');
    }

    public function cancelTimeoff(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null || (int) $req['user_id'] !== $userId) {
            throw new ForbiddenException('Demande introuvable.');
        }
        if (($req['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/timeoff?error=not_pending');
        }

        $this->timeoffRequests->save(array_merge($req, ['status' => 'cancelled']));
        $this->auditLogger->log($request, 'timeoff.cancelled', 'timeoff_request', (int) $req['id'], []);
        return Response::redirect($this->base() . '/employee/timeoff?success=cancelled');
    }

    // -------------------------------------------------------------------------
    // Échanges de shifts
    // -------------------------------------------------------------------------

    public function swaps(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $sent     = $this->swapRequests->findByRequester($userId);
        $received = $this->swapRequests->findByTarget($userId);

        usort($sent,     fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($received, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.swaps', [
            'title'      => 'Échanges de shifts',
            'sent'       => $sent,
            'received'   => $received,
            'users_map'  => $this->buildUsersMap(),
            'shifts_map' => $this->buildShiftsMap(),
        ], 'layout.app'));
    }

    public function createSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);
        $today  = date('Y-m-d');

        $myShifts = array_values(array_filter(
            $this->shifts->findByUser($userId),
            fn($s) => ($s['shift_date'] ?? '') >= $today
        ));
        usort($myShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));

        // Collègues du même store (hors moi)
        $memberships = $this->storeUsers->findByUser($userId);
        $myStoreIds  = array_unique(array_map(fn($m) => (int) $m['store_id'], $memberships));

        $colleagues = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid !== $userId) {
                    $u = $this->users->findById($mid);
                    if ($u) {
                        $colleagues[$mid] = $u;
                    }
                }
            }
        }

        $targetId     = (int) ($request->query('target_id') ?? 0);
        $targetShifts = [];
        if ($targetId > 0 && isset($colleagues[$targetId])) {
            $targetShifts = array_values(array_filter(
                $this->shifts->findByUser($targetId),
                fn($s) => ($s['shift_date'] ?? '') >= $today
            ));
            usort($targetShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));
        }

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        $this->shareMonthStats($userId, (string) ($request->query('month') ?? ''));

        return Response::html($this->view->render('employee.swaps-form', [
            'title'         => 'Demander un échange',
            'my_shifts'     => $myShifts,
            'colleagues'    => array_values($colleagues),
            'target_id'     => $targetId,
            'target_shifts' => $targetShifts,
            'types_map'     => $typesMap,
        ], 'layout.app'));
    }

    public function storeSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $myShiftId     = (int) $request->post('requester_shift_id', 0);
        $targetId      = (int) $request->post('target_id', 0);
        $targetShiftId = (int) $request->post('target_shift_id', 0);
        $reason        = $request->post('reason', '') ?: null;

        $myShift = $this->shifts->findById($myShiftId);
        if ($myShift === null || (int) $myShift['user_id'] !== $userId) {
            throw new ForbiddenException('Shift introuvable.');
        }

        $targetShift = $this->shifts->findById($targetShiftId);
        if ($targetShift === null || (int) $targetShift['user_id'] !== $targetId) {
            throw new ForbiddenException('Shift cible introuvable.');
        }

        if ((int) $myShift['store_id'] !== (int) $targetShift['store_id']) {
            throw new ForbiddenException('Les shifts doivent appartenir au même store.');
        }

        $savedSwap = $this->swapRequests->save([
            'store_id'           => (int) $myShift['store_id'],
            'requester_id'       => $userId,
            'target_id'          => $targetId,
            'requester_shift_id' => $myShiftId,
            'target_shift_id'    => $targetShiftId,
            'reason'             => $reason,
            'status'             => 'pending',
            'peer_accepted_at'   => null,
        ]);

        $this->auditLogger->log($request, 'swap.created', 'shift_swap_request', (int) ($savedSwap['id'] ?? 0), [
            'target_id' => $targetId,
        ], (int) $myShift['store_id']);
        return Response::redirect($this->base() . '/employee/swaps?success=created');
    }

    public function acceptSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['target_id'] !== $userId) {
            throw new ForbiddenException('Demande introuvable.');
        }
        if (($swap['status'] ?? '') !== 'pending' || ($swap['peer_accepted_at'] ?? null) !== null) {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $this->swapRequests->save(array_merge($swap, [
            'peer_accepted_at' => date('Y-m-d H:i:s'),
        ]));

        $this->auditLogger->log($request, 'swap.peer_accepted', 'shift_swap_request', (int) $swap['id'], [
            'requester_id' => $swap['requester_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/employee/swaps?success=accepted');
    }

    public function refuseSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['target_id'] !== $userId) {
            throw new ForbiddenException('Demande introuvable.');
        }
        if (($swap['status'] ?? '') !== 'pending' || ($swap['peer_accepted_at'] ?? null) !== null) {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $this->swapRequests->save(array_merge($swap, ['status' => 'refused']));
        $this->auditLogger->log($request, 'swap.peer_refused', 'shift_swap_request', (int) $swap['id'], [
            'requester_id' => $swap['requester_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/employee/swaps?success=refused');
    }

    public function cancelSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['requester_id'] !== $userId) {
            throw new ForbiddenException('Demande introuvable.');
        }
        if (($swap['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $this->swapRequests->save(array_merge($swap, ['status' => 'cancelled']));
        $this->auditLogger->log($request, 'swap.cancelled', 'shift_swap_request', (int) $swap['id'], []);
        return Response::redirect($this->base() . '/employee/swaps?success=cancelled');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Calcule les stats du mois sélectionné et les partage dans les vues.
     * $month : 'YYYY-MM' (défaut = mois courant).
     */
    private function shareMonthStats(int $userId, string $month = ''): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
        $nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));

        // Nombre de semaines ISO dans le mois
        $weekSet = [];
        $cur = new \DateTime($monthStart);
        $endDt = new \DateTime($monthEnd);
        while ($cur <= $endDt) {
            $weekSet[$cur->format('o-W')] = true;
            $cur->modify('+1 day');
        }
        $numWeeks = max(1, count($weekSet));

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }
        $personalRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
        }

        // Devise du premier store de l'employé
        $memberships = $this->storeUsers->findByUser($userId);
        $currency    = 'JPY';
        if (!empty($memberships)) {
            $store    = $this->stores->findById((int) $memberships[0]['store_id']);
            $currency = strtoupper(trim($store['currency'] ?? 'JPY'));
        }

        $frDays   = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
        $frMonths = ['Jan'=>'jan.','Feb'=>'fév.','Mar'=>'mars','Apr'=>'avr.','May'=>'mai','Jun'=>'juin',
                     'Jul'=>'juil.','Aug'=>'août','Sep'=>'sep.','Oct'=>'oct.','Nov'=>'nov.','Dec'=>'déc.'];
        $frMonthsFull = [
            '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
            '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre',
        ];

        $monthMinutes = 0;
        $estimatedPay = 0.0;
        $hasRate      = false;
        $shiftDetails = [];

        foreach ($this->shifts->findByUser($userId) as $s) {
            $d = $s['shift_date'] ?? '';
            if ($d < $monthStart || $d > $monthEnd) {
                continue;
            }
            [$sh, $sm] = explode(':', substr($s['start_time'] ?? '00:00', 0, 5));
            [$eh, $em] = explode(':', substr($s['end_time']   ?? '00:00', 0, 5));
            $startMin  = (int) $sh * 60 + (int) $sm;
            $endMin    = (int) $eh * 60 + (int) $em;
            if (!empty($s['cross_midnight']) || $endMin <= $startMin) {
                $endMin += 24 * 60;
            }
            $pauseMin      = (int) ($s['pause_minutes'] ?? 0);
            $netMin        = max(0, $endMin - $startMin - $pauseMin);
            $monthMinutes += $netMin;

            $tid  = (int) ($s['shift_type_id'] ?? 0);
            $rate = $personalRates[$tid] ?? (float) ($typesMap[$tid]['hourly_rate'] ?? 0);
            $pay  = ($netMin / 60) * $rate;
            if ($rate > 0) {
                $hasRate = true;
            }
            $estimatedPay += $pay;

            $dt       = new \DateTimeImmutable($d);
            $dateLabel = ($frDays[$dt->format('D')] ?? $dt->format('D'))
                . ' ' . $dt->format('j')
                . ' ' . ($frMonths[$dt->format('M')] ?? $dt->format('M'));
            $h = intdiv($netMin, 60);
            $m = $netMin % 60;
            $shiftDetails[] = [
                'date'          => $d,
                'date_label'    => $dateLabel,
                'start'         => substr($s['start_time'] ?? '—', 0, 5),
                'end'           => substr($s['end_time']   ?? '—', 0, 5),
                'type_name'     => $typesMap[$tid]['name'] ?? '—',
                'net_min'       => $netMin,
                'net_hours_fmt' => $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT),
                'pause_min'     => $pauseMin,
                'rate'          => $rate,
                'rate_fmt'      => $rate > 0 ? format_currency($rate, $currency) . '/h' : '—',
                'pay_fmt'       => $rate > 0 ? format_currency($pay, $currency) : '—',
                'has_rate'      => $rate > 0,
            ];
        }

        usort($shiftDetails, fn($a, $b) => strcmp($a['date'] . $a['start'], $b['date'] . $b['start']));

        [$y, $mNum] = array_pad(explode('-', $month), 2, '');
        $monthLabel = ($frMonthsFull[$mNum] ?? $mNum) . ' ' . $y;

        $this->view->share('employee_month_stats', [
            'hours_month'   => $monthMinutes / 60,
            'hours_week'    => ($monthMinutes / 60) / $numWeeks,
            'estimated_pay' => $estimatedPay,
            'has_rate'      => $hasRate,
            'month_label'   => $monthLabel,
            'month_key'     => $month,
            'prev_month'    => $prevMonth,
            'next_month'    => $nextMonth,
            'is_current'    => ($month === date('Y-m')),
            'currency'      => $currency,
            'shift_details' => $shiftDetails,
        ]);
    }

    private function buildUsersMap(): array
    {
        $map = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $map[(int) $u['id']] = $name ?: ($u['display_name'] ?? $u['email'] ?? '#' . $u['id']);
        }
        return $map;
    }

    private function buildShiftsMap(): array
    {
        $map = [];
        foreach ($this->shifts->findAll() as $s) {
            $map[(int) $s['id']] = $s;
        }
        return $map;
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }
}
