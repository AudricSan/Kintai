<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
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

final class AdminController
{
    private const ROLES = ['admin' => 'Administrateur', 'manager' => 'Manager', 'staff' => 'Employé'];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly AuditLogger $auditLogger,
    ) {}

    // -------------------------------------------------------------------------
    // Listes
    // -------------------------------------------------------------------------

    public function users(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        if ($managedIds !== null) {
            $memberIds = $this->memberUserIds($managedIds);
            $users = array_values(array_filter(
                $this->users->findAll(),
                fn($u) => in_array((int) $u['id'], $memberIds, true)
            ));
        } else {
            $users = $this->users->findAll();
        }

        // Statistiques du mois en cours
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        // Nombre de semaines ISO distinctes dans le mois
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

        $userStats = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];

            $personalRates = [];
            foreach ($this->userRates->findByUser($uid) as $r) {
                $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }

            $totalMinutes = 0;
            $estimatedPay = 0.0;
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d < $monthStart || $d > $monthEnd) {
                    continue;
                }
                [$sh, $sm] = explode(':', substr($s['start_time'] ?? '00:00', 0, 5));
                [$eh, $em] = explode(':', substr($s['end_time'] ?? '00:00', 0, 5));
                $startMin = (int) $sh * 60 + (int) $sm;
                $endMin   = (int) $eh * 60 + (int) $em;
                if (!empty($s['cross_midnight']) || $endMin <= $startMin) {
                    $endMin += 24 * 60;
                }
                $minutes       = max(0, $endMin - $startMin);
                $totalMinutes += $minutes;

                $tid  = (int) ($s['shift_type_id'] ?? 0);
                $rate = $personalRates[$tid] ?? (float) ($typesMap[$tid]['hourly_rate'] ?? 0);
                $estimatedPay += ($minutes / 60) * $rate;
            }

            $userStats[$uid] = [
                'hours_month'   => $totalMinutes / 60,
                'hours_week'    => ($totalMinutes / 60) / $numWeeks,
                'estimated_pay' => $estimatedPay,
            ];
        }

        $availableStores = $this->availableStores($managedIds);
        $storeCurrency   = strtoupper(trim($availableStores[0]['currency'] ?? 'JPY'));

        // Map uid → premier storeId accessible (pour liens stats/fiche de paie)
        $availableStoreIds = array_map(fn($s) => (int) $s['id'], $availableStores);
        $userStoreMap = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];
            foreach ($this->storeUsers->findByUser($uid) as $m) {
                $sid = (int) $m['store_id'];
                if (in_array($sid, $availableStoreIds, true)) {
                    $userStoreMap[$uid] = $sid;
                    break;
                }
            }
        }

        // Tri
        $sort = $request->query('sort') ?? 'name_asc';
        usort($users, function ($a, $b) use ($sort, $userStats) {
            $nameA   = strtolower($a['display_name'] ?? $a['email'] ?? '');
            $nameB   = strtolower($b['display_name'] ?? $b['email'] ?? '');
            $emailA  = strtolower($a['email'] ?? '');
            $emailB  = strtolower($b['email'] ?? '');
            $roleA   = (int) ($a['is_admin'] ?? 0);
            $roleB   = (int) ($b['is_admin'] ?? 0);
            $statA   = (int) (!empty($a['is_active']) && empty($a['deleted_at']));
            $statB   = (int) (!empty($b['is_active']) && empty($b['deleted_at']));
            $hA      = $userStats[(int)($a['id'] ?? 0)]['hours_month'] ?? 0;
            $hB      = $userStats[(int)($b['id'] ?? 0)]['hours_month'] ?? 0;
            return match ($sort) {
                'name_desc'   => strcmp($nameB, $nameA),
                'email_asc'   => strcmp($emailA, $emailB) ?: strcmp($nameA, $nameB),
                'email_desc'  => strcmp($emailB, $emailA) ?: strcmp($nameA, $nameB),
                'role_asc'    => $roleA <=> $roleB ?: strcmp($nameA, $nameB),
                'role_desc'   => $roleB <=> $roleA ?: strcmp($nameA, $nameB),
                'status_asc'  => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc' => $statB <=> $statA ?: strcmp($nameA, $nameB),
                'hours_asc'   => $hA <=> $hB ?: strcmp($nameA, $nameB),
                'hours_desc'  => $hB <=> $hA ?: strcmp($nameA, $nameB),
                default       => strcmp($nameA, $nameB), // name_asc
            };
        });

        return Response::html($this->view->render('admin.users', [
            'title'          => 'Personnel',
            'users'          => $users,
            'user_stats'     => $userStats,
            'month_label'    => date('F Y'),
            'store_currency' => $storeCurrency,
            'sort'           => $sort,
            'user_store_map' => $userStoreMap,
        ], 'layout.app'));
    }

    public function stores(Request $request): Response
    {
        $stores = $this->availableStores($this->managedIds($request));

        $sort = $request->query('sort') ?? 'name_asc';
        usort($stores, function ($a, $b) use ($sort) {
            $codeA  = strtolower($a['code'] ?? '');
            $codeB  = strtolower($b['code'] ?? '');
            $nameA  = strtolower($a['name'] ?? '');
            $nameB  = strtolower($b['name'] ?? '');
            $typeA  = strtolower($a['type'] ?? '');
            $typeB  = strtolower($b['type'] ?? '');
            $statA  = (int) (!empty($a['is_active']) && empty($a['deleted_at']));
            $statB  = (int) (!empty($b['is_active']) && empty($b['deleted_at']));
            return match ($sort) {
                'name_desc'   => strcmp($nameB, $nameA),
                'code_asc'    => strcmp($codeA, $codeB) ?: strcmp($nameA, $nameB),
                'code_desc'   => strcmp($codeB, $codeA) ?: strcmp($nameA, $nameB),
                'type_asc'    => strcmp($typeA, $typeB) ?: strcmp($nameA, $nameB),
                'type_desc'   => strcmp($typeB, $typeA) ?: strcmp($nameA, $nameB),
                'status_asc'  => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc' => $statB <=> $statA ?: strcmp($nameA, $nameB),
                default       => strcmp($nameA, $nameB), // name_asc
            };
        });

        return Response::html($this->view->render('admin.stores', [
            'title'  => 'Stores',
            'stores' => $stores,
            'sort'   => $sort,
        ], 'layout.app'));
    }

    public function shifts(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $usersMap   = $this->buildUsersMap();

        // Filtre mois (défaut = mois en cours)
        $filterMonth = $request->query('month') ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $filterMonth)) {
            $filterMonth = date('Y-m');
        }
        $monthStart = $filterMonth . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        // Filtre store et personnel
        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        $filterUserId  = (int) ($request->query('user_id')  ?? 0);

        $allShifts = $this->filterByStore($this->shifts->findAll(), $managedIds);

        // Filtrer par mois
        $allShifts = array_values(array_filter(
            $allShifts,
            fn($s) => ($s['shift_date'] ?? '') >= $monthStart && ($s['shift_date'] ?? '') <= $monthEnd
        ));

        if ($filterStoreId > 0) {
            $allShifts = array_values(array_filter($allShifts, fn($s) => (int) ($s['store_id'] ?? 0) === $filterStoreId));
        }

        if ($filterUserId > 0) {
            $allShifts = array_values(array_filter($allShifts, fn($s) => (int) ($s['user_id'] ?? 0) === $filterUserId));
        }

        // Types map pour le tri et l'affichage
        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        // Tri
        $sort = $request->query('sort') ?? 'date_asc';
        usort($allShifts, function ($a, $b) use ($sort, $usersMap, $typesMap) {
            $nameA  = $usersMap[(int) ($a['user_id'] ?? 0)] ?? '';
            $nameB  = $usersMap[(int) ($b['user_id'] ?? 0)] ?? '';
            $dateA  = ($a['shift_date'] ?? '') . ($a['start_time'] ?? '');
            $dateB  = ($b['shift_date'] ?? '') . ($b['start_time'] ?? '');
            $typeA  = strtolower($typesMap[(int) ($a['shift_type_id'] ?? 0)]['name'] ?? '');
            $typeB  = strtolower($typesMap[(int) ($b['shift_type_id'] ?? 0)]['name'] ?? '');
            $netA   = (int) ($a['duration_minutes'] ?? 0) - (int) ($a['pause_minutes'] ?? 0);
            $netB   = (int) ($b['duration_minutes'] ?? 0) - (int) ($b['pause_minutes'] ?? 0);
            return match ($sort) {
                'staff_asc'     => strcmp($nameA, $nameB)  ?: strcmp($dateA, $dateB),
                'staff_desc'    => strcmp($nameB, $nameA)  ?: strcmp($dateA, $dateB),
                'type_asc'      => strcmp($typeA, $typeB)  ?: strcmp($dateA, $dateB),
                'type_desc'     => strcmp($typeB, $typeA)  ?: strcmp($dateA, $dateB),
                'duration_asc'  => ($netA <=> $netB)       ?: strcmp($dateA, $dateB),
                'duration_desc' => ($netB <=> $netA)       ?: strcmp($dateA, $dateB),
                'date_asc'      => strcmp($dateA, $dateB),
                default         => strcmp($dateB, $dateA), // date_desc
            };
        });

        // Pour le filtre UI, ne proposer que les stores accessibles
        $storesMap = [];
        foreach ($this->availableStores($managedIds) as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
        }

        // Liste du personnel accessible pour le filtre
        $memberIds = $managedIds !== null ? $this->memberUserIds($managedIds) : null;
        $usersForFilter = [];
        foreach ($this->users->findAll() as $u) {
            $uid = (int) $u['id'];
            if ($memberIds === null || in_array($uid, $memberIds, true)) {
                $usersForFilter[$uid] = $usersMap[$uid] ?? ('#' . $uid);
            }
        }
        asort($usersForFilter);

        return Response::html($this->view->render('admin.shifts', [
            'title'            => 'Shifts',
            'shifts'           => $allShifts,
            'users_map'        => $usersMap,
            'stores_map'       => $storesMap,
            'types_map'        => $typesMap,
            'users_for_filter' => $usersForFilter,
            'filter_store_id'  => $filterStoreId,
            'filter_month'     => $filterMonth,
            'filter_user_id'   => $filterUserId,
            'sort'             => $sort,
        ], 'layout.app'));
    }

    public function shiftTypes(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $types = $this->filterByStore($this->shiftTypes->findAll(), $managedIds);

        // Construire map store pour le tri/affichage
        $storesMap = $this->buildStoresMap($managedIds);

        $sort = $request->query('sort') ?? 'name_asc';
        usort($types, function ($a, $b) use ($sort, $storesMap) {
            $storeA  = strtolower($storesMap[(int)($a['store_id'] ?? 0)] ?? '');
            $storeB  = strtolower($storesMap[(int)($b['store_id'] ?? 0)] ?? '');
            $codeA   = strtolower($a['code'] ?? '');
            $codeB   = strtolower($b['code'] ?? '');
            $nameA   = strtolower($a['name'] ?? '');
            $nameB   = strtolower($b['name'] ?? '');
            $statA   = (int) !empty($a['is_active']);
            $statB   = (int) !empty($b['is_active']);
            return match ($sort) {
                'name_desc'    => strcmp($nameB, $nameA),
                'store_asc'    => strcmp($storeA, $storeB) ?: strcmp($nameA, $nameB),
                'store_desc'   => strcmp($storeB, $storeA) ?: strcmp($nameA, $nameB),
                'code_asc'     => strcmp($codeA, $codeB) ?: strcmp($nameA, $nameB),
                'code_desc'    => strcmp($codeB, $codeA) ?: strcmp($nameA, $nameB),
                'status_asc'   => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc'  => $statB <=> $statA ?: strcmp($nameA, $nameB),
                default        => strcmp($nameA, $nameB), // name_asc
            };
        });

        return Response::html($this->view->render('admin.shift-types', [
            'title'       => 'Types de shifts',
            'shift_types' => $types,
            'stores_map'  => $storesMap,
            'sort'        => $sort,
        ], 'layout.app'));
    }

    public function timeoff(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);
        $requests  = $this->filterByStore($this->timeoffRequests->findAll(), $managedIds);

        $sort = $request->query('sort') ?? 'date_desc';
        usort($requests, function ($a, $b) use ($sort, $usersMap) {
            $dateA  = ($a['start_date'] ?? '');
            $dateB  = ($b['start_date'] ?? '');
            $userA  = strtolower($usersMap[(int)($a['user_id'] ?? 0)] ?? '');
            $userB  = strtolower($usersMap[(int)($b['user_id'] ?? 0)] ?? '');
            $typeA  = $a['type'] ?? '';
            $typeB  = $b['type'] ?? '';
            $statA  = $a['status'] ?? '';
            $statB  = $b['status'] ?? '';
            return match ($sort) {
                'date_asc'    => strcmp($dateA, $dateB),
                'user_asc'    => strcmp($userA, $userB) ?: strcmp($dateA, $dateB),
                'user_desc'   => strcmp($userB, $userA) ?: strcmp($dateA, $dateB),
                'type_asc'    => strcmp($typeA, $typeB) ?: strcmp($dateA, $dateB),
                'type_desc'   => strcmp($typeB, $typeA) ?: strcmp($dateA, $dateB),
                'status_asc'  => strcmp($statA, $statB) ?: strcmp($dateA, $dateB),
                'status_desc' => strcmp($statB, $statA) ?: strcmp($dateA, $dateB),
                default       => strcmp($dateB, $dateA), // date_desc
            };
        });

        return Response::html($this->view->render('admin.timeoff', [
            'title'      => 'Congés',
            'requests'   => $requests,
            'users_map'  => $usersMap,
            'stores_map' => $storesMap,
            'sort'       => $sort,
        ], 'layout.app'));
    }

    public function swapRequests(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);
        $swaps     = $this->filterByStore($this->swapRequests->findAll(), $managedIds);

        $sort = $request->query('sort') ?? 'date_desc';
        usort($swaps, function ($a, $b) use ($sort, $usersMap) {
            $dateA  = ($a['created_at'] ?? '');
            $dateB  = ($b['created_at'] ?? '');
            $reqA   = strtolower($usersMap[(int)($a['requester_id'] ?? 0)] ?? '');
            $reqB   = strtolower($usersMap[(int)($b['requester_id'] ?? 0)] ?? '');
            $statA  = $a['status'] ?? '';
            $statB  = $b['status'] ?? '';
            return match ($sort) {
                'date_asc'        => strcmp($dateA, $dateB),
                'requester_asc'   => strcmp($reqA, $reqB) ?: strcmp($dateA, $dateB),
                'requester_desc'  => strcmp($reqB, $reqA) ?: strcmp($dateA, $dateB),
                'status_asc'      => strcmp($statA, $statB) ?: strcmp($dateA, $dateB),
                'status_desc'     => strcmp($statB, $statA) ?: strcmp($dateA, $dateB),
                default           => strcmp($dateB, $dateA), // date_desc
            };
        });

        return Response::html($this->view->render('admin.swap-requests', [
            'title'      => 'Échanges de shifts',
            'swaps'      => $swaps,
            'users_map'  => $usersMap,
            'stores_map' => $storesMap,
            'sort'       => $sort,
        ], 'layout.app'));
    }

    // -------------------------------------------------------------------------
    // Shifts — CRUD
    // -------------------------------------------------------------------------

    /**
     * GET /admin/shifts/calendar — vue calendrier mensuel.
     * Paramètres : month (YYYY-MM), u[] (filtre user IDs), store_id
     */
    public function shiftsCalendar(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

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

        // Tous les membres accessibles (avec leur couleur)
        $allMembers  = [];
        $membersMap  = []; // uid → user row
        $usersColour = []; // uid → color
        foreach ($this->availableStores($managedIds) as $store) {
            foreach ($this->storeUsers->findByStore((int) $store['id']) as $m) {
                $uid = (int) $m['user_id'];
                if (isset($membersMap[$uid])) continue;
                $u = $this->users->findById($uid);
                if (!$u) continue;
                $membersMap[$uid]  = $u;
                $usersColour[$uid] = $u['color'] ?? '#6366f1';
                $allMembers[]      = $u;
            }
        }
        usort($allMembers, fn($a, $b) => strcmp(
            strtolower($a['display_name'] ?? $a['email'] ?? ''),
            strtolower($b['display_name'] ?? $b['email'] ?? '')
        ));

        // Filtre utilisateurs (GET u[])
        $filterUids = array_map('intval', (array) ($request->query('u') ?? []));
        $activeUids = $filterUids ?: array_keys($membersMap);

        // Store filter (optionnel)
        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        $storesMap     = [];
        foreach ($this->availableStores($managedIds) as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '';
        }

        // Charger les shifts du mois pour les utilisateurs actifs
        $shiftsByDate = []; // 'Y-m-d' → shift[]
        foreach ($activeUids as $uid) {
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d < $monthStart || $d > $monthEnd) continue;
                if ($filterStoreId > 0 && (int) ($s['store_id'] ?? 0) !== $filterStoreId) continue;
                $shiftsByDate[$d][] = $s;
            }
        }

        // Types de shift
        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        return Response::html($this->view->render('admin.shifts-calendar', [
            'title'         => 'Calendrier',
            'year'          => $year,
            'month'         => $month,
            'month_label'   => $monthLabel,
            'month_start'   => $monthStart,
            'month_end'     => $monthEnd,
            'prev_month'    => $prevMonth,
            'next_month'    => $nextMonth,
            'today'         => date('Y-m-d'),
            'shifts_by_date' => $shiftsByDate,
            'all_members'   => $allMembers,
            'members_map'   => $membersMap,
            'users_colour'  => $usersColour,
            'filter_uids'   => $filterUids,
            'types_map'     => $typesMap,
            'stores_map'    => $storesMap,
            'filter_store_id' => $filterStoreId,
            'is_manager_view' => true,
        ], 'layout.app'));
    }

    /**
     * GET /admin/shifts/timeline — vue Timeline pour les managers/admins.
     * Paramètres : store_id, start (Y-m-d), view (week|3days)
     */
    public function shiftsTimeline(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $today      = date('Y-m-d');

        // Mode d'affichage
        $viewMode = $request->query('view') ?? 'week';
        if (!in_array($viewMode, ['week', '3days'], true)) $viewMode = 'week';

        // Plage de dates
        $startParam = (string) ($request->query('start') ?? $today);
        try {
            $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startParam)
                ? new \DateTimeImmutable($startParam)
                : new \DateTimeImmutable($today);
        } catch (\Throwable) {
            $anchor = new \DateTimeImmutable($today);
        }

        if ($viewMode === 'week') {
            $firstDay = $anchor->setISODate((int) $anchor->format('o'), (int) $anchor->format('W'), 1);
            $numDays  = 7;
        } else {
            $firstDay = $anchor;
            $numDays  = 3;
        }

        $days      = array_map(fn($i) => $firstDay->modify("+$i days"), range(0, $numDays - 1));
        $lastDay   = $days[$numDays - 1];
        $prevStart = $firstDay->modify("-{$numDays} days")->format('Y-m-d');
        $nextStart = $firstDay->modify("+{$numDays} days")->format('Y-m-d');

        // Stores accessibles
        $availStores = $this->availableStores($managedIds);
        $storesMap   = [];
        foreach ($availStores as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }

        // Store sélectionné (par défaut : premier store accessible)
        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        if ($filterStoreId === 0 && !empty($availStores)) {
            $filterStoreId = (int) $availStores[0]['id'];
        }
        if ($filterStoreId > 0) {
            try {
                $this->assertStoreAccess($request, $filterStoreId);
            } catch (ForbiddenException) {
                $filterStoreId = 0;
            }
        }

        // Membres du store (triés par nom)
        $memberIds = [];
        if ($filterStoreId > 0) {
            foreach ($this->storeUsers->findByStore($filterStoreId) as $m) {
                $memberIds[] = (int) $m['user_id'];
            }
        }

        // Map noms et couleurs
        $usersMap    = $this->buildUsersMap();
        $colorMap    = [];
        foreach ($this->users->findAll() as $u) {
            $colorMap[(int) $u['id']] = $u['color'] ?? null;
        }
        usort($memberIds, fn($a, $b) => strcmp($usersMap[$a] ?? '', $usersMap[$b] ?? ''));

        // Types de shifts (tous les stores gérés)
        $typesMap = [];
        foreach ($this->filterByStore($this->shiftTypes->findAll(), $managedIds) as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        // Shifts par date et utilisateur
        $dateFrom         = $firstDay->format('Y-m-d');
        $dateTo           = $lastDay->format('Y-m-d');
        $shiftsByDateUser = [];
        if ($filterStoreId > 0) {
            foreach ($this->shifts->findByStore($filterStoreId) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d < $dateFrom || $d > $dateTo) continue;
                $uid = (int) ($s['user_id'] ?? 0);
                $shiftsByDateUser[$d][$uid][] = $s;
            }
        }

        // Taux horaires et devise
        $ratesMap    = [];
        $currencyMap = [];
        $storeObj    = $filterStoreId > 0 ? $this->stores->findById($filterStoreId) : null;
        $currency    = strtoupper(trim($storeObj['currency'] ?? 'JPY'));
        foreach ($memberIds as $uid) {
            $currencyMap[$uid] = $currency;
            foreach ($this->userRates->findByUser($uid) as $r) {
                $ratesMap[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        // ID de l'utilisateur courant (pour highlight éventuel)
        $authUser = $request->getAttribute('auth_user');
        $myUserId = (int) ($authUser['id'] ?? 0);

        // Paramètres de planification du store (pour les alertes timeline)
        $storeSettings = [
            'min_staff_per_day'    => (int) ($storeObj['min_staff_per_day']    ?? 0),
            'min_shift_minutes'    => (int) ($storeObj['min_shift_minutes']    ?? 0),
            'max_shift_minutes'    => (int) ($storeObj['max_shift_minutes']    ?? 0),
            'low_staff_hour_start' => (int) ($storeObj['low_staff_hour_start'] ?? -1),
            'low_staff_hour_end'   => (int) ($storeObj['low_staff_hour_end']   ?? -1),
        ];

        return Response::html($this->view->render('admin.shifts-timeline', [
            'title'               => 'Planning — Timeline',
            'days'                => $days,
            'period_mode'         => $viewMode,
            'prev_start'          => $prevStart,
            'next_start'          => $nextStart,
            'shifts_by_date_user' => $shiftsByDateUser,
            'all_user_ids'        => $memberIds,
            'users_map'           => $usersMap,
            'user_color_map'      => $colorMap,
            'types_map'           => $typesMap,
            'rates_map'           => $ratesMap,
            'currency_map'        => $currencyMap,
            'filter_store_id'     => $filterStoreId,
            'stores_map'          => $storesMap,
            'available_stores'    => $availStores,
            'today'               => $today,
            'my_user_id'          => $myUserId,
            'store_settings'      => $storeSettings,
        ], 'layout.app'));
    }

    /**
     * GET /admin/shifts/timeline/print — Document d'impression autonome de la timeline.
     * Accepte les mêmes paramètres que shiftsTimeline (store_id, start, view).
     */
    public function shiftsTimelinePrint(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $today      = date('Y-m-d');

        $viewMode = $request->query('view') ?? 'week';
        if (!in_array($viewMode, ['week', '3days'], true)) $viewMode = 'week';

        $startParam = (string) ($request->query('start') ?? $today);
        try {
            $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startParam)
                ? new \DateTimeImmutable($startParam)
                : new \DateTimeImmutable($today);
        } catch (\Throwable) {
            $anchor = new \DateTimeImmutable($today);
        }

        if ($viewMode === 'week') {
            $firstDay = $anchor->setISODate((int) $anchor->format('o'), (int) $anchor->format('W'), 1);
            $numDays  = 7;
        } else {
            $firstDay = $anchor;
            $numDays  = 3;
        }

        $days    = array_map(fn($i) => $firstDay->modify("+$i days"), range(0, $numDays - 1));
        $lastDay = $days[$numDays - 1];

        $availStores = $this->availableStores($managedIds);
        $storesMap   = [];
        foreach ($availStores as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }

        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        if ($filterStoreId === 0 && !empty($availStores)) {
            $filterStoreId = (int) $availStores[0]['id'];
        }
        if ($filterStoreId > 0) {
            try {
                $this->assertStoreAccess($request, $filterStoreId);
            } catch (ForbiddenException) {
                $filterStoreId = 0;
            }
        }

        $memberIds = [];
        if ($filterStoreId > 0) {
            foreach ($this->storeUsers->findByStore($filterStoreId) as $m) {
                $memberIds[] = (int) $m['user_id'];
            }
        }

        $usersMap = $this->buildUsersMap();
        $colorMap = [];
        foreach ($this->users->findAll() as $u) {
            $colorMap[(int) $u['id']] = $u['color'] ?? null;
        }
        usort($memberIds, fn($a, $b) => strcmp($usersMap[$a] ?? '', $usersMap[$b] ?? ''));

        $typesMap = [];
        foreach ($this->filterByStore($this->shiftTypes->findAll(), $managedIds) as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        $dateFrom         = $firstDay->format('Y-m-d');
        $dateTo           = $lastDay->format('Y-m-d');
        $shiftsByDateUser = [];
        if ($filterStoreId > 0) {
            foreach ($this->shifts->findByStore($filterStoreId) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d < $dateFrom || $d > $dateTo) continue;
                $uid = (int) ($s['user_id'] ?? 0);
                $shiftsByDateUser[$d][$uid][] = $s;
            }
        }

        return Response::html($this->view->render('admin.shifts-timeline-print', [
            'days'                => $days,
            'period_mode'         => $viewMode,
            'shifts_by_date_user' => $shiftsByDateUser,
            'all_user_ids'        => $memberIds,
            'users_map'           => $usersMap,
            'user_color_map'      => $colorMap,
            'types_map'           => $typesMap,
            'filter_store_id'     => $filterStoreId,
            'stores_map'          => $storesMap,
            'today'               => $today,
            'autoprint'           => $request->query('autoprint') === '1',
            'showLegend'          => $request->query('legend') === '1',
        ]));
    }

    public function createShift(Request $request): Response
    {
        $managedIds    = $this->managedIds($request);
        $availStores   = $this->availableStores($managedIds);
        $availStoreIds = array_map(fn($s) => (int) $s['id'], $availStores);

        // Utilisateurs : membres des stores accessibles uniquement
        $allUsers = $managedIds !== null
            ? array_values(array_filter($this->users->findAll(), fn($u) => in_array((int) $u['id'], $this->memberUserIds($managedIds), true)))
            : $this->users->findAll();

        return Response::html($this->view->render('admin.shifts-form', [
            'title'      => 'Nouveau shift',
            'mode'       => 'create',
            'shift'      => [],
            'all_users'  => $allUsers,
            'all_stores' => $availStores,
            'all_types'  => $this->filterByStore($this->shiftTypes->findAll(), $managedIds),
        ], 'layout.app'));
    }

    public function storeShift(Request $request): Response
    {
        $storeId = (int) $request->post('store_id', 0);
        $this->assertStoreAccess($request, $storeId);

        $startTime = $request->post('start_time', '08:00');
        $endTime   = $request->post('end_time', '16:00');

        $shiftDate = $request->post('shift_date', '');

        $start = strtotime($startTime);
        $end   = strtotime($endTime);
        $duration = (int) (($end - $start) / 60);
        if ($duration < 0) {
            $duration += 24 * 60;
        }
        $crossMidnight = ($end < $start) ? 1 : 0;
        $endsDate = $crossMidnight ? date('Y-m-d', strtotime($shiftDate . ' +1 day')) : $shiftDate;

        $saved = $this->shifts->save([
            'store_id'         => $storeId,
            'user_id'          => (int) $request->post('user_id', 0),
            'shift_type_id'    => ($request->post('shift_type_id') !== '' ? (int) $request->post('shift_type_id') : null),
            'shift_date'       => $shiftDate,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $duration,
            'pause_minutes'    => (int) $request->post('pause_minutes', 0),
            'cross_midnight'   => $crossMidnight,
            'starts_at'        => $shiftDate . ' ' . $startTime . ':00',
            'ends_at'          => $endsDate  . ' ' . $endTime   . ':00',
            'notes'            => $request->post('notes', '') ?: null,
        ]);

        $this->auditLogger->log($request, 'shift.created', 'shift', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'shift_date' => $shiftDate,
            'start' => $startTime,
            'end' => $endTime,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/shifts?success=created');
    }

    public function editShift(Request $request): Response
    {
        $shift = $this->shifts->findById((int) $request->param('id'));
        if ($shift === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $shift['store_id']);

        $managedIds = $this->managedIds($request);
        $allUsers   = $managedIds !== null
            ? array_values(array_filter($this->users->findAll(), fn($u) => in_array((int) $u['id'], $this->memberUserIds($managedIds), true)))
            : $this->users->findAll();

        return Response::html($this->view->render('admin.shifts-form', [
            'title'      => 'Modifier le shift #' . $shift['id'],
            'mode'       => 'edit',
            'shift'      => $shift,
            'all_users'  => $allUsers,
            'all_stores' => $this->availableStores($managedIds),
            'all_types'  => $this->filterByStore($this->shiftTypes->findAll(), $managedIds),
        ], 'layout.app'));
    }

    public function updateShift(Request $request): Response
    {
        $shift = $this->shifts->findById((int) $request->param('id'));
        if ($shift === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $shift['store_id']);

        $newStoreId = (int) $request->post('store_id', $shift['store_id']);
        // Vérifier aussi le store cible si changement
        if ($newStoreId !== (int) $shift['store_id']) {
            $this->assertStoreAccess($request, $newStoreId);
        }

        $startTime = $request->post('start_time', $shift['start_time']);
        $endTime   = $request->post('end_time', $shift['end_time']);

        $start = strtotime($startTime);
        $end   = strtotime($endTime);
        $duration = (int) (($end - $start) / 60);
        if ($duration < 0) {
            $duration += 24 * 60;
        }
        $crossMidnight = ($end < $start) ? 1 : 0;

        $shiftDate = $request->post('shift_date', $shift['shift_date'] ?? '');
        $endsDate  = $crossMidnight ? date('Y-m-d', strtotime($shiftDate . ' +1 day')) : $shiftDate;

        $this->shifts->save(array_merge($shift, [
            'store_id'         => $newStoreId,
            'user_id'          => (int) $request->post('user_id', $shift['user_id']),
            'shift_type_id'    => ($request->post('shift_type_id') !== '' ? (int) $request->post('shift_type_id') : null),
            'shift_date'       => $shiftDate,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $duration,
            'pause_minutes'    => (int) $request->post('pause_minutes', $shift['pause_minutes'] ?? 0),
            'cross_midnight'   => $crossMidnight,
            'starts_at'        => $shiftDate . ' ' . $startTime . ':00',
            'ends_at'          => $endsDate  . ' ' . $endTime   . ':00',
            'notes'            => $request->post('notes', '') ?: null,
        ]));

        $this->auditLogger->log($request, 'shift.updated', 'shift', (int) $shift['id'], [
            'store_id' => $newStoreId,
            'shift_date' => $shiftDate,
            'start' => $startTime,
            'end' => $endTime,
        ], $newStoreId);

        return Response::redirect($this->base() . '/admin/shifts?success=updated');
    }

    public function deleteShift(Request $request): Response
    {
        $id    = (int) $request->param('id');
        $shift = $this->shifts->findById($id);
        if ($shift !== null) {
            $this->assertStoreAccess($request, (int) $shift['store_id']);
        }
        $this->shifts->delete($id);
        $this->auditLogger->log($request, 'shift.deleted', 'shift', $id, [
            'shift_date' => $shift['shift_date'] ?? null,
        ], $shift ? (int) $shift['store_id'] : null);
        return Response::redirect($this->base() . '/admin/shifts?success=deleted');
    }

    /**
     * POST /admin/shifts/bulk-delete — supprime plusieurs shifts en une opération.
     * Body : ids[] = tableau d'IDs, redirect_month, redirect_store
     */
    public function bulkDeleteShifts(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $ids        = $request->post('ids') ?? [];
        if (!is_array($ids) || empty($ids)) {
            return Response::redirect($this->base() . '/admin/shifts');
        }
        // Limite côté serveur : 100 IDs maximum
        $ids = array_slice($ids, 0, 100);

        $deleted    = 0;
        $deletedIds = [];
        foreach ($ids as $rawId) {
            $id    = (int) $rawId;
            if ($id <= 0) continue;
            $shift = $this->shifts->findById($id);
            if (!$shift) continue;
            // Vérification d'accès au store
            $storeId = (int) ($shift['store_id'] ?? 0);
            if ($managedIds !== null && !in_array($storeId, $managedIds, true)) continue;
            $this->shifts->delete($id);
            $deletedIds[] = $id;
            $deleted++;
        }

        if ($deleted > 0) {
            $this->auditLogger->log($request, 'shift.bulk_deleted', 'shift', null, [
                'ids' => $deletedIds,
                'count' => $deleted,
            ]);
        }

        $month = $request->post('redirect_month') ?? date('Y-m');
        $store = (int) ($request->post('redirect_store') ?? 0);
        $qs    = http_build_query(array_filter([
            'month'    => $month,
            'store_id' => $store ?: null,
            'success'  => 'bulk_deleted',
            'count'    => $deleted,
        ], fn($v) => $v !== null && $v !== 0 && $v !== ''));
        return Response::redirect($this->base() . '/admin/shifts?' . $qs);
    }

    /**
     * GET /admin/shifts/conflicts — détecte les chevauchements de shifts par employé.
     */
    public function shiftConflicts(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $usersMap   = $this->buildUsersMap();

        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        $filterMonth   = $request->query('month') ?? '';
        if (!preg_match('/^\d{4}-\d{2}$/', $filterMonth)) $filterMonth = '';

        $allShifts = $this->filterByStore($this->shifts->findAll(), $managedIds);

        if ($filterStoreId > 0) {
            $allShifts = array_values(array_filter(
                $allShifts,
                fn($s) => (int) ($s['store_id'] ?? 0) === $filterStoreId
            ));
        }

        if ($filterMonth !== '') {
            $mStart    = $filterMonth . '-01';
            $mEnd      = date('Y-m-t', strtotime($mStart));
            $allShifts = array_values(array_filter(
                $allShifts,
                fn($s) => ($s['shift_date'] ?? '') >= $mStart && ($s['shift_date'] ?? '') <= $mEnd
            ));
        }

        // Détection des conflits par employé (chevauchements temporels)
        $byUser = [];
        foreach ($allShifts as $s) {
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid > 0) $byUser[$uid][] = $s;
        }

        $conflicts = [];
        foreach ($byUser as $userId => $userShifts) {
            usort($userShifts, fn($a, $b) => strcmp(
                ($a['shift_date'] ?? '') . ($a['start_time'] ?? ''),
                ($b['shift_date'] ?? '') . ($b['start_time'] ?? '')
            ));

            $n = count($userShifts);
            for ($i = 0; $i < $n; $i++) {
                $a      = $userShifts[$i];
                $startA = strtotime(($a['shift_date'] ?? '') . ' ' . ($a['start_time'] ?? '00:00'));
                $endA   = !empty($a['cross_midnight'])
                    ? strtotime(date('Y-m-d', strtotime(($a['shift_date'] ?? '') . ' +1 day')) . ' ' . ($a['end_time'] ?? '00:00'))
                    : strtotime(($a['shift_date'] ?? '') . ' ' . ($a['end_time'] ?? '00:00'));

                for ($j = $i + 1; $j < $n; $j++) {
                    $b      = $userShifts[$j];
                    $startB = strtotime(($b['shift_date'] ?? '') . ' ' . ($b['start_time'] ?? '00:00'));
                    if ($startB >= $endA) break; // plus de chevauchement possible
                    $endB = !empty($b['cross_midnight'])
                        ? strtotime(date('Y-m-d', strtotime(($b['shift_date'] ?? '') . ' +1 day')) . ' ' . ($b['end_time'] ?? '00:00'))
                        : strtotime(($b['shift_date'] ?? '') . ' ' . ($b['end_time'] ?? '00:00'));

                    if ($startA < $endB && $startB < $endA) {
                        $overlapMin = (int) ((min($endA, $endB) - max($startA, $startB)) / 60);
                        $conflicts[] = [
                            'user_id'         => $userId,
                            'shift_a'         => $a,
                            'shift_b'         => $b,
                            'overlap_minutes' => $overlapMin,
                        ];
                    }
                }
            }
        }

        // Plus récent en premier
        usort($conflicts, fn($a, $b) => strcmp(
            $b['shift_a']['shift_date'] ?? '',
            $a['shift_a']['shift_date'] ?? ''
        ));

        $storesMap    = [];
        $storesMinMap = []; // store_id → min_shift_minutes (0 = non configuré)
        foreach ($this->availableStores($managedIds) as $s) {
            $sid                = (int) $s['id'];
            $storesMap[$sid]    = $s['name'] ?? '#' . $sid;
            $storesMinMap[$sid] = (int) ($s['min_shift_minutes'] ?? 0);
        }

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        // Shifts trop courts : durée < min_shift_minutes du store (si paramètre > 0)
        $tooShort = [];
        foreach ($allShifts as $s) {
            $sid = (int) ($s['store_id'] ?? 0);
            $min = $storesMinMap[$sid] ?? 0;
            if ($min <= 0) continue;
            $dur = (int) ($s['duration_minutes'] ?? 0);
            if ($dur > 0 && $dur < $min) {
                $tooShort[] = $s;
            }
        }
        usort($tooShort, fn($a, $b) => strcmp(
            ($b['shift_date'] ?? ''),
            ($a['shift_date'] ?? '')
        ));

        return Response::html($this->view->render('admin.shifts-conflicts', [
            'title'           => __('shift_conflicts_title'),
            'conflicts'       => $conflicts,
            'too_short'       => $tooShort,
            'stores_min_map'  => $storesMinMap,
            'users_map'       => $usersMap,
            'stores_map'      => $storesMap,
            'types_map'       => $typesMap,
            'filter_store_id' => $filterStoreId,
            'filter_month'    => $filterMonth,
        ], 'layout.app'));
    }

    /**
     * POST /admin/shifts/conflicts/resolve-newer
     * Conserve le shift le plus récent (ID le plus élevé) et supprime l'ancien.
     *   - Mode unitaire : id_keep + id_delete
     *   - Mode vrac     : bulk=1 + month (YYYY-MM) [+ store_id]
     */
    public function resolveNewerConflict(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $bulk       = $request->post('bulk') === '1';
        $month      = trim($request->post('month', ''));
        $storeId    = (int) $request->post('store_id', 0);

        if (!$bulk) {
            // --- Mode unitaire ---
            $idKeep   = (int) $request->post('id_keep',   0);
            $idDelete = (int) $request->post('id_delete', 0);
            if ($idKeep <= 0 || $idDelete <= 0) {
                return Response::redirect($this->base() . '/admin/shifts/conflicts');
            }

            $shiftKeep   = $this->shifts->findById($idKeep);
            $shiftDelete = $this->shifts->findById($idDelete);
            if ($shiftKeep)   $this->assertStoreAccess($request, (int) $shiftKeep['store_id']);
            if ($shiftDelete) $this->assertStoreAccess($request, (int) $shiftDelete['store_id']);

            $this->shifts->delete($idDelete);
            $this->auditLogger->log($request, 'shift.conflict_resolved', 'shift', $idKeep, [
                'kept' => $idKeep,
                'deleted' => $idDelete,
            ], $shiftKeep ? (int) $shiftKeep['store_id'] : null);

            $qs = http_build_query(array_filter([
                'month'    => $month ?: null,
                'store_id' => $storeId ?: null,
                'success'  => 'conflict_resolved',
            ], fn($v) => $v !== null && $v !== 0 && $v !== ''));
            return Response::redirect($this->base() . '/admin/shifts/conflicts?' . $qs);
        }

        // --- Mode vrac : résoudre tous les conflits du mois ---
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Response::redirect($this->base() . '/admin/shifts/conflicts');
        }

        $mStart    = $month . '-01';
        $mEnd      = date('Y-m-t', strtotime($mStart));
        $allShifts = $this->filterByStore($this->shifts->findAll(), $managedIds);

        if ($storeId > 0) {
            $allShifts = array_values(array_filter(
                $allShifts,
                fn($s) => (int) ($s['store_id'] ?? 0) === $storeId
            ));
        }
        $allShifts = array_values(array_filter(
            $allShifts,
            fn($s) => ($s['shift_date'] ?? '') >= $mStart && ($s['shift_date'] ?? '') <= $mEnd
        ));

        $byUser = [];
        foreach ($allShifts as $s) {
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid > 0) $byUser[$uid][] = $s;
        }

        $toDelete = [];
        foreach ($byUser as $userShifts) {
            usort($userShifts, fn($a, $b) => strcmp(
                ($a['shift_date'] ?? '') . ($a['start_time'] ?? ''),
                ($b['shift_date'] ?? '') . ($b['start_time'] ?? '')
            ));
            $n = count($userShifts);
            for ($i = 0; $i < $n; $i++) {
                $a      = $userShifts[$i];
                $startA = strtotime(($a['shift_date'] ?? '') . ' ' . ($a['start_time'] ?? '00:00'));
                $endA   = !empty($a['cross_midnight'])
                    ? strtotime(date('Y-m-d', strtotime(($a['shift_date'] ?? '') . ' +1 day')) . ' ' . ($a['end_time'] ?? '00:00'))
                    : strtotime(($a['shift_date'] ?? '') . ' ' . ($a['end_time'] ?? '00:00'));

                for ($j = $i + 1; $j < $n; $j++) {
                    $b      = $userShifts[$j];
                    $startB = strtotime(($b['shift_date'] ?? '') . ' ' . ($b['start_time'] ?? '00:00'));
                    if ($startB >= $endA) break;
                    $endB = !empty($b['cross_midnight'])
                        ? strtotime(date('Y-m-d', strtotime(($b['shift_date'] ?? '') . ' +1 day')) . ' ' . ($b['end_time'] ?? '00:00'))
                        : strtotime(($b['shift_date'] ?? '') . ' ' . ($b['end_time'] ?? '00:00'));

                    if ($startA < $endB && $startB < $endA) {
                        // Garder le plus récent (ID le plus élevé), supprimer l'ancien
                        $idOlder = min((int) $a['id'], (int) $b['id']);
                        $toDelete[$idOlder] = true;
                    }
                }
            }
        }

        $deleted = 0;
        foreach (array_keys($toDelete) as $idDel) {
            $shift = $this->shifts->findById($idDel);
            if (!$shift) continue;
            $this->shifts->delete($idDel);
            $deleted++;
        }

        $this->auditLogger->log($request, 'shift.bulk_conflict_resolved', 'shift', null, [
            'month' => $month,
            'store_id' => $storeId,
            'deleted' => $deleted,
        ], $storeId ?: null);

        $qs = http_build_query(array_filter([
            'month'    => $month,
            'store_id' => $storeId ?: null,
            'success'  => 'conflicts_resolved',
            'count'    => $deleted,
        ], fn($v) => $v !== null && $v !== 0 && $v !== ''));
        return Response::redirect($this->base() . '/admin/shifts/conflicts?' . $qs);
    }

    /**
     * POST /admin/shifts/{id}/move — déplace un shift (heure) via drag & drop.
     * Corps JSON : { shift_date?, start_time, end_time }
     * Retourne le shift mis à jour en JSON.
     */
    public function moveShift(Request $request): Response
    {
        $shift = $this->shifts->findById((int) $request->param('id'));
        if ($shift === null) {
            return Response::json(['error' => 'Shift introuvable.'], 404);
        }
        try {
            $this->assertStoreAccess($request, (int) $shift['store_id']);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'Accès refusé.'], 403);
        }

        $body      = $request->json() ?? [];
        $shiftDate = $body['shift_date'] ?? $shift['shift_date'];
        $startTime = $body['start_time'] ?? $shift['start_time'];
        $endTime   = $body['end_time']   ?? $shift['end_time'];

        $start         = strtotime($startTime);
        $end           = strtotime($endTime);
        $duration      = (int) (($end - $start) / 60);
        if ($duration < 0) $duration += 24 * 60;
        $crossMidnight = ($end < $start) ? 1 : 0;
        $endsDate      = $crossMidnight
            ? date('Y-m-d', strtotime($shiftDate . ' +1 day'))
            : $shiftDate;

        $saved = $this->shifts->save(array_merge($shift, [
            'shift_date'       => $shiftDate,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $duration,
            'cross_midnight'   => $crossMidnight,
            'starts_at'        => $shiftDate . ' ' . $startTime . ':00',
            'ends_at'          => $endsDate  . ' ' . $endTime   . ':00',
        ]));

        $this->auditLogger->log($request, 'shift.moved', 'shift', (int) $shift['id'], [
            'shift_date' => $shiftDate,
            'start' => $startTime,
            'end' => $endTime,
        ], (int) $shift['store_id']);

        return Response::json($saved);
    }

    /**
     * POST /admin/shifts/quick — création rapide d'un shift via drag & drop.
     * Corps JSON : { store_id, user_id, shift_date, start_time, end_time, shift_type_id? }
     * Retourne le shift créé en JSON (201).
     */
    public function quickShift(Request $request): Response
    {
        $body    = $request->json() ?? [];
        $storeId = (int) ($body['store_id'] ?? 0);

        try {
            $this->assertStoreAccess($request, $storeId);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'Accès refusé.'], 403);
        }

        $shiftDate = $body['shift_date'] ?? '';
        $startTime = $body['start_time'] ?? '08:00';
        $endTime   = $body['end_time']   ?? '16:00';

        $start         = strtotime($startTime);
        $end           = strtotime($endTime);
        $duration      = (int) (($end - $start) / 60);
        if ($duration < 0) $duration += 24 * 60;
        $crossMidnight = ($end < $start) ? 1 : 0;
        $endsDate      = $crossMidnight
            ? date('Y-m-d', strtotime($shiftDate . ' +1 day'))
            : $shiftDate;

        $saved = $this->shifts->save([
            'store_id'         => $storeId,
            'user_id'          => (int) ($body['user_id'] ?? 0),
            'shift_type_id'    => !empty($body['shift_type_id']) ? (int) $body['shift_type_id'] : null,
            'shift_date'       => $shiftDate,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $duration,
            'pause_minutes'    => 0,
            'cross_midnight'   => $crossMidnight,
            'starts_at'        => $shiftDate . ' ' . $startTime . ':00',
            'ends_at'          => $endsDate  . ' ' . $endTime   . ':00',
            'notes'            => null,
        ]);

        $this->auditLogger->log($request, 'shift.created', 'shift', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'shift_date' => $shiftDate,
            'start' => $startTime,
            'end' => $endTime,
            'via' => 'drag',
        ], $storeId);

        return Response::json($saved, 201);
    }

    // -------------------------------------------------------------------------
    // Timeoff — actions statut
    // -------------------------------------------------------------------------

    public function approveTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        $this->timeoffRequests->save(array_merge($req, ['status' => 'approved']));
        $this->auditLogger->log($request, 'timeoff.approved', 'timeoff_request', (int) $req['id'], [
            'user_id' => $req['user_id'] ?? null,
        ], (int) ($req['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/admin/timeoff?success=approved');
    }

    public function refuseTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        $this->timeoffRequests->save(array_merge($req, ['status' => 'refused']));
        $this->auditLogger->log($request, 'timeoff.refused', 'timeoff_request', (int) $req['id'], [
            'user_id' => $req['user_id'] ?? null,
        ], (int) ($req['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/admin/timeoff?success=refused');
    }

    // -------------------------------------------------------------------------
    // Swap requests — actions statut
    // -------------------------------------------------------------------------

    public function approveSwap(Request $request): Response
    {
        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($swap['store_id'] ?? 0));

        // Exécuter le swap : échanger les user_id entre les deux shifts
        $reqShiftId = (int) ($swap['requester_shift_id'] ?? 0);
        $tgtShiftId = (int) ($swap['target_shift_id'] ?? 0);
        if ($reqShiftId > 0 && $tgtShiftId > 0) {
            $reqShift = $this->shifts->findById($reqShiftId);
            $tgtShift = $this->shifts->findById($tgtShiftId);
            if ($reqShift !== null && $tgtShift !== null) {
                $this->shifts->save(array_merge($reqShift, ['user_id' => (int) $tgtShift['user_id']]));
                $this->shifts->save(array_merge($tgtShift, ['user_id' => (int) $reqShift['user_id']]));
            }
        }

        $this->swapRequests->save(array_merge($swap, ['status' => 'accepted']));
        $this->auditLogger->log($request, 'swap.approved', 'shift_swap_request', (int) $swap['id'], [
            'requester_id' => $swap['requester_id'] ?? null,
            'target_id' => $swap['target_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/admin/swap-requests?success=approved');
    }

    public function refuseSwap(Request $request): Response
    {
        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($swap['store_id'] ?? 0));
        $this->swapRequests->save(array_merge($swap, ['status' => 'refused']));
        $this->auditLogger->log($request, 'swap.refused', 'shift_swap_request', (int) $swap['id'], [
            'requester_id' => $swap['requester_id'] ?? null,
            'target_id' => $swap['target_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/admin/swap-requests?success=refused');
    }

    // -------------------------------------------------------------------------
    // Users — CRUD
    // -------------------------------------------------------------------------

    public function createUser(Request $request): Response
    {
        return Response::html($this->view->render('admin.users-form', [
            'title'      => 'Nouvel utilisateur',
            'mode'       => 'create',
            'user'       => [],
            'all_stores' => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function storeUser(Request $request): Response
    {
        $password = $request->post('password', '');
        $empCode  = strtoupper(trim($request->post('employee_code', ''))) ?: null;
        $saved    = $this->users->save([
            'display_name'  => $request->post('display_name', ''),
            'first_name'    => $request->post('first_name', ''),
            'last_name'     => $request->post('last_name', ''),
            'email'         => $request->post('email', ''),
            'phone'         => $request->post('phone', '') ?: null,
            'color'         => $request->post('color', '#3B82F6'),
            'employee_code' => $empCode,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'is_admin'      => $request->post('is_admin') === '1' ? 1 : 0,
            'is_active'     => 1,
        ]);

        // Affecter au magasin si sélectionné
        $storeId = (int) $request->post('store_id', 0);
        if ($storeId > 0 && !empty($saved['id'])) {
            $this->assertStoreAccess($request, $storeId);
            $validRoles = ['staff', 'manager', 'admin'];
            $role = $request->post('store_role', 'staff');
            if (!in_array($role, $validRoles, true)) $role = 'staff';
            $this->storeUsers->save([
                'store_id' => $storeId,
                'user_id'  => (int) $saved['id'],
                'role'     => $role,
            ]);
        }

        $this->auditLogger->log($request, 'user.created', 'user', (int) ($saved['id'] ?? 0), [
            'email' => $request->post('email', ''),
        ]);
        return Response::redirect($this->base() . '/admin/users?success=created');
    }

    /**
     * Création rapide d'un utilisateur depuis la prévisualisation d'import Excel.
     * Retourne JSON {success, user: {id, name}} ou {success: false, error}.
     */
    public function quickCreateUser(Request $request): Response
    {
        $firstName = trim($request->post('first_name', ''));
        $lastName  = trim($request->post('last_name', ''));
        $storeId   = (int) $request->post('store_id', 0);
        $empCode   = strtoupper(trim($request->post('employee_code', ''))) ?: null;

        if ($firstName === '') {
            return Response::json(['success' => false, 'error' => 'Prénom requis.'], 422);
        }

        // Vérifier unicité du code employé si fourni
        if ($empCode !== null && $this->users->findByEmployeeCode($empCode) !== null) {
            return Response::json(['success' => false, 'error' => 'employee_code_taken'], 409);
        }

        // Générer un email unique @kintai.local
        $base  = mb_strtolower($firstName . '.' . $lastName);
        $base  = preg_replace('/[^a-z0-9.]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base));
        $base  = trim($base, '.');
        if ($base === '') {
            $base = 'user';
        }
        $email   = $base . '@kintai.local';
        $attempt = 0;
        while ($this->users->findByEmail($email) !== null) {
            $attempt++;
            $email = $base . $attempt . '@kintai.local';
            if ($attempt > 99) {
                break;
            }
        }

        $colors      = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];
        $color       = $colors[array_rand($colors)];
        $displayName = trim($firstName . ' ' . $lastName);
        $tempPass    = 'Temp' . rand(1000, 9999) . '!';

        $saved = $this->users->save([
            'display_name'  => $displayName,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $email,
            'employee_code' => $empCode,
            'password_hash' => password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]),
            'is_admin'      => 0,
            'is_active'     => 1,
            'color'         => $color,
        ]);

        if (empty($saved['id'])) {
            return Response::json(['success' => false, 'error' => 'Création échouée.'], 500);
        }

        if ($storeId > 0) {
            $this->assertStoreAccess($request, $storeId);
            $this->storeUsers->save([
                'store_id' => $storeId,
                'user_id'  => (int) $saved['id'],
                'role'     => 'staff',
            ]);
        }

        $this->auditLogger->log($request, 'user.created', 'user', (int) $saved['id'], [
            'email'  => $email,
            'source' => 'excel_import',
        ]);

        return Response::json([
            'success' => true,
            'user'    => ['id' => (int) $saved['id'], 'name' => $displayName],
        ]);
    }

    /** Vérifie en temps réel si un code employé est disponible. Retourne JSON {available: bool}. */
    public function checkEmployeeCode(Request $request): Response
    {
        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') {
            return Response::json(['available' => true]);
        }
        $taken = $this->users->findByEmployeeCode($code) !== null;
        return Response::json(['available' => !$taken]);
    }

    public function editUser(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $userId = (int) $user['id'];

        // Shift types des stores où l'utilisateur est membre
        $memberships   = $this->storeUsers->findByUser($userId);
        $userStoreIds  = array_map(fn($m) => (int) $m['store_id'], $memberships);
        $userStoreIds  = array_unique($userStoreIds);

        $userShiftTypes = empty($userStoreIds)
            ? []
            : $this->filterByStore($this->shiftTypes->findAll(), $userStoreIds);

        // Taux actuels indexés par shift_type_id
        $userRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $userRates[(int) $r['shift_type_id']] = $r;
        }

        // Map store_id → store name pour l'affichage
        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
        }

        // Map store_id → deduction_settings (JSON décodé)
        $storeDeductionSettings = [];
        foreach ($this->stores->findAll() as $s) {
            $storeDeductionSettings[(int) $s['id']] = json_decode($s['deduction_settings'] ?? '{}', true) ?? [];
        }

        // Appartenance aux stores (enrichie avec nom du store, rôle, cotisations)
        $userMemberships = [];
        foreach ($memberships as $m) {
            $sid = (int) $m['store_id'];
            $userMemberships[] = array_merge($m, [
                'store_name'         => $storesMap[$sid] ?? '#' . $sid,
                'store_ded_settings' => $storeDeductionSettings[$sid] ?? [],
                'ded_overrides'      => json_decode($m['deduction_overrides'] ?? '{}', true) ?? [],
            ]);
        }

        // Stores disponibles pour ajouter l'utilisateur (ceux où il n'est pas encore)
        $availableStores = array_values(array_filter(
            $this->availableStores($this->managedIds($request)),
            fn($s) => !in_array((int) $s['id'], $userStoreIds, true)
        ));

        return Response::html($this->view->render('admin.users-form', [
            'title'            => 'Modifier ' . htmlspecialchars($user['display_name'] ?? ''),
            'mode'             => 'edit',
            'user'             => $user,
            'user_shift_types' => $userShiftTypes,
            'user_rates'       => $userRates,
            'stores_map'       => $storesMap,
            'user_memberships' => $userMemberships,
            'available_stores' => $availableStores,
            'all_stores'       => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function updateUser(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $empCode = strtoupper(trim($request->post('employee_code', ''))) ?: null;
        $data = array_merge($user, [
            'display_name'  => $request->post('display_name', $user['display_name'] ?? ''),
            'first_name'    => $request->post('first_name', $user['first_name'] ?? ''),
            'last_name'     => $request->post('last_name', $user['last_name'] ?? ''),
            'email'         => $request->post('email', $user['email'] ?? ''),
            'phone'         => $request->post('phone', '') ?: null,
            'color'         => $request->post('color', $user['color'] ?? '#3B82F6'),
            'employee_code' => $empCode,
            'is_admin'      => $request->post('is_admin') === '1' ? 1 : 0,
            'is_active'     => $request->post('is_active') === '1' ? 1 : 0,
        ]);

        // Mettre à jour le mot de passe uniquement si fourni
        $newPassword = $request->post('password', '');
        if ($newPassword !== '') {
            $data['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->users->save($data);
        $this->auditLogger->log($request, 'user.updated', 'user', (int) $user['id'], [
            'email' => $data['email'] ?? null,
        ]);
        return Response::redirect($this->base() . '/admin/users?success=updated');
    }

    public function deleteUser(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->users->delete($id);
        $this->auditLogger->log($request, 'user.deleted', 'user', $id, []);
        return Response::redirect($this->base() . '/admin/users?success=deleted');
    }

    // -------------------------------------------------------------------------
    // Users — taux horaires par type de shift
    // -------------------------------------------------------------------------

    public function setUserRate(Request $request): Response
    {
        $userId = (int) $request->param('id');
        $user   = $this->users->findById($userId);
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $shiftTypeId = (int) $request->post('shift_type_id', 0);
        $rateRaw     = $request->post('hourly_rate', '');

        // Vérifier que le type de shift est dans un store accessible
        $type = $this->shiftTypes->findById($shiftTypeId);
        if ($type !== null) {
            $this->assertStoreAccess($request, (int) $type['store_id']);
        }

        $existing = $this->userRates->findRate($userId, $shiftTypeId);

        if ($rateRaw === '') {
            // Supprimer le taux si le champ est vide
            if ($existing !== null) {
                $this->userRates->delete((int) $existing['id']);
            }
        } else {
            $data = [
                'user_id'       => $userId,
                'shift_type_id' => $shiftTypeId,
                'hourly_rate'   => (float) $rateRaw,
            ];
            if ($existing !== null) {
                $data = array_merge($existing, $data);
            }
            $this->userRates->save($data);
        }

        return Response::redirect($this->base() . '/admin/users/' . $userId . '/edit?success=rate_updated');
    }

    public function deleteUserRate(Request $request): Response
    {
        $userId = (int) $request->param('id');
        $rid    = (int) $request->param('rid');

        $rate = $this->userRates->findById($rid);
        if ($rate !== null && (int) $rate['user_id'] === $userId) {
            $type = $this->shiftTypes->findById((int) $rate['shift_type_id']);
            if ($type !== null) {
                $this->assertStoreAccess($request, (int) $type['store_id']);
            }
            $this->userRates->delete($rid);
        }

        return Response::redirect($this->base() . '/admin/users/' . $userId . '/edit?success=rate_deleted');
    }

    // -------------------------------------------------------------------------
    // Stores — CRUD
    // -------------------------------------------------------------------------

    public function createStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut créer un store.');
        }

        return Response::html($this->view->render('admin.stores-form', [
            'title' => 'Nouveau store',
            'mode'  => 'create',
            'store' => [],
        ], 'layout.app'));
    }

    public function storeStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut créer un store.');
        }
        $savedStore = $this->stores->save([
            'code'            => strtoupper(trim($request->post('code', ''))),
            'name'            => $request->post('name', ''),
            'type'            => $request->post('type', 'retail'),
            'timezone'        => $request->post('timezone', 'UTC'),
            'locale'          => $request->post('locale', 'en'),
            'currency'        => strtoupper(trim($request->post('currency', 'EUR'))),
            'phone'           => $request->post('phone', '') ?: null,
            'email'           => $request->post('email', '') ?: null,
            'address_street'  => $request->post('address_street', '') ?: null,
            'address_city'    => $request->post('address_city', '') ?: null,
            'address_postal'  => $request->post('address_postal', '') ?: null,
            'address_country' => $request->post('address_country', '') ?: null,
            'is_active'       => 1,
        ]);

        $this->auditLogger->log($request, 'store.created', 'store', (int) ($savedStore['id'] ?? 0), [
            'name' => $request->post('name', ''),
        ]);
        return Response::redirect($this->base() . '/admin/stores?success=created');
    }

    public function editStore(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->assertStoreAccess($request, (int) $store['id']);

        // Enrichir les membres avec les données utilisateur
        $rawMembers = $this->storeUsers->findByStore((int) $store['id']);
        $memberUserIds = array_map(fn($m) => (int) $m['user_id'], $rawMembers);

        $members = array_map(function ($m) {
            $user = $this->users->findById((int) $m['user_id']);
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            return array_merge($m, [
                'user_name'  => $name ?: ($user['email'] ?? '—'),
                'user_email' => $user['email'] ?? '',
            ]);
        }, $rawMembers);

        // Utilisateurs non encore membres (pour le dropdown d'ajout)
        $allUsers = $this->users->findAll();
        $available = array_values(array_filter(
            $allUsers,
            fn($u) => !in_array((int) $u['id'], $memberUserIds, true)
        ));

        return Response::html($this->view->render('admin.stores-form', [
            'title'             => 'Modifier ' . htmlspecialchars($store['name'] ?? ''),
            'mode'              => 'edit',
            'store'             => $store,
            'members'           => $members,
            'available'         => $available,
            'roles'             => self::ROLES,
            'deductionSettings' => json_decode($store['deduction_settings'] ?? '{}', true) ?? [],
        ], 'layout.app'));
    }

    public function updateStore(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->assertStoreAccess($request, (int) $store['id']);

        // Paramètres d'import Excel (JSON)
        $excelDefaults = \kintai\Core\Services\ExcelShiftImport\ExcelShiftImportService::DEFAULTS;
        $excelSettings = [
            'col_start'            => (int)   ($request->post('excel_col_start',            $excelDefaults['col_start'])),
            'col_end'              => (int)   ($request->post('excel_col_end',              $excelDefaults['col_end'])),
            'base_hour'            => (int)   ($request->post('excel_base_hour',            $excelDefaults['base_hour'])),
            'minutes_per_col'      => (int)   ($request->post('excel_minutes_per_col',      $excelDefaults['minutes_per_col'])),
            'block_size'           => (int)   ($request->post('excel_block_size',           $excelDefaults['block_size'])),
            'shift_rows'           => (int)   ($request->post('excel_shift_rows',           $excelDefaults['shift_rows'])),
            'sheet_filter_pattern' => (string)($request->post('excel_sheet_filter_pattern', $excelDefaults['sheet_filter_pattern'])),
            'auto_pause_after_minutes'  => (int) ($request->post('auto_pause_after_minutes',  $excelDefaults['auto_pause_after_minutes'])),
            'auto_pause_minutes'        => (int) ($request->post('auto_pause_minutes',        $excelDefaults['auto_pause_minutes'])),
        ];

        // Cotisations sociales
        $deductionSettings = [
            'enabled'                   => !empty($request->post('ded_enabled')),
            'health_insurance_rate'     => (float) ($request->post('ded_health_rate') ?? 0),
            'pension_rate'              => (float) ($request->post('ded_pension_rate') ?? 0),
            'employment_insurance_rate' => (float) ($request->post('ded_employment_rate') ?? 0),
            'income_tax_rate'           => (float) ($request->post('ded_income_tax_rate') ?? 0),
            'resident_tax_monthly'      => (float) ($request->post('ded_resident_tax') ?? 0),
        ];

        $this->stores->save(array_merge($store, [
            'code'                  => strtoupper(trim($request->post('code', $store['code'] ?? ''))),
            'name'                  => $request->post('name', $store['name'] ?? ''),
            'type'                  => $request->post('type', $store['type'] ?? 'retail'),
            'timezone'              => $request->post('timezone', $store['timezone'] ?? 'UTC'),
            'locale'                => $request->post('locale', $store['locale'] ?? 'en'),
            'currency'              => strtoupper(trim($request->post('currency', $store['currency'] ?? 'EUR'))),
            'phone'                 => $request->post('phone', '') ?: null,
            'email'                 => $request->post('email', '') ?: null,
            'address_street'        => $request->post('address_street', '') ?: null,
            'address_city'          => $request->post('address_city', '') ?: null,
            'address_postal'        => $request->post('address_postal', '') ?: null,
            'address_country'       => $request->post('address_country', '') ?: null,
            'min_shift_minutes'     => (int) $request->post('min_shift_minutes', 120),
            'max_shift_minutes'     => (int) $request->post('max_shift_minutes', 480),
            'min_staff_per_day'     => (int) $request->post('min_staff_per_day', 0),
            'low_staff_hour_start'  => max(-1, min(23, (int) $request->post('low_staff_hour_start', -1))),
            'low_staff_hour_end'    => max(-1, min(23, (int) $request->post('low_staff_hour_end',   -1))),
            'is_active'             => $request->post('is_active') === '1' ? 1 : 0,
            'excel_import_settings' => json_encode($excelSettings),
            'deduction_settings'    => json_encode($deductionSettings),
        ]));

        $this->auditLogger->log($request, 'store.updated', 'store', (int) $store['id'], [
            'name' => $request->post('name', $store['name'] ?? ''),
        ], (int) $store['id']);
        return Response::redirect($this->base() . '/admin/stores?success=updated');
    }

    public function deleteStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut supprimer un store.');
        }

        $id    = (int) $request->param('id');
        $store = $this->stores->findById($id);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }

        $this->stores->delete($id);
        $this->auditLogger->log($request, 'store.deleted', 'store', $id, [
            'name' => $store['name'] ?? null,
        ]);
        return Response::redirect($this->base() . '/admin/stores?success=deleted');
    }

    // -------------------------------------------------------------------------
    // Stores — gestion des membres (rôles par store)
    // -------------------------------------------------------------------------

    public function addMember(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store   = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $userId = (int) $request->post('user_id', 0);
        $role   = $request->post('role', 'staff');
        if (!array_key_exists($role, self::ROLES)) {
            $role = 'staff';
        }

        // Ignorer si déjà membre
        if ($userId > 0 && $this->storeUsers->findMembership($storeId, $userId) === null) {
            $this->storeUsers->save([
                'store_id' => $storeId,
                'user_id'  => $userId,
                'role'     => $role,
            ]);
            $this->auditLogger->log($request, 'store.member_added', 'store_user', null, [
                'store_id' => $storeId,
                'user_id' => $userId,
                'role' => $role,
            ], $storeId);
        }

        $redirectTo = $request->post('redirect_to', '');
        $dest = $redirectTo !== '' ? $redirectTo : $this->base() . '/admin/stores/' . $storeId . '/edit?success=member_added';
        return Response::redirect($dest);
    }

    public function updateMemberRole(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $role = $request->post('role', 'staff');
        if (!array_key_exists($role, self::ROLES)) {
            $role = 'staff';
        }

        $this->storeUsers->save(array_merge($membership, ['role' => $role]));
        $this->auditLogger->log($request, 'store.member_role_updated', 'store_user', $mid, [
            'store_id' => $storeId,
            'user_id' => $membership['user_id'] ?? null,
            'role' => $role,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/edit?success=role_updated');
    }

    public function editMemberDeductions(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Membre introuvable.');
        }

        $user = $this->users->findById((int) $membership['user_id']);
        if ($user === null) throw new NotFoundException('Utilisateur introuvable.');

        $deductionSettings  = json_decode($store['deduction_settings'] ?? '{}', true) ?? [];
        $deductionOverrides = json_decode($membership['deduction_overrides'] ?? '{}', true) ?? [];

        return Response::html($this->view->render('admin.member-deductions', [
            'title'              => 'Cotisations — ' . ($user['display_name'] ?? $user['email'] ?? ''),
            'store'              => $store,
            'membership'         => $membership,
            'user'               => $user,
            'deductionSettings'  => $deductionSettings,
            'deductionOverrides' => $deductionOverrides,
        ], 'layout.app'));
    }

    public function saveMemberDeductions(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');
        $this->assertStoreAccess($request, $storeId);

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Membre introuvable.');
        }

        $overrides = [
            'subject_to_deductions' => !empty($request->post('subject_to_deductions')),
        ];

        $this->storeUsers->save(array_merge($membership, [
            'deduction_overrides' => json_encode($overrides),
        ]));

        $redirectTo = $request->post('_redirect_to', '');
        $fallback   = $this->base() . '/admin/stores/' . $storeId . '/edit?success=deductions_saved';
        $target     = ($redirectTo !== '' && str_starts_with($redirectTo, $this->base() . '/admin/'))
            ? $redirectTo
            : $fallback;

        return Response::redirect($target);
    }

    public function removeMember(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $this->storeUsers->delete($mid);
        $this->auditLogger->log($request, 'store.member_removed', 'store_user', $mid, [
            'store_id' => $storeId,
            'user_id' => $membership['user_id'] ?? null,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/edit?success=member_removed');
    }

    // -------------------------------------------------------------------------
    // Shift types — CRUD
    // -------------------------------------------------------------------------

    public function createShiftType(Request $request): Response
    {
        return Response::html($this->view->render('admin.shift-types-form', [
            'title'      => 'Nouveau type de shift',
            'mode'       => 'create',
            'shift_type' => [],
            'all_stores' => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function storeShiftType(Request $request): Response
    {
        $storeId = (int) $request->post('store_id', 0);
        $this->assertStoreAccess($request, $storeId);

        $savedType = $this->shiftTypes->save([
            'store_id'    => $storeId,
            'code'        => strtoupper(trim($request->post('code', ''))),
            'name'        => $request->post('name', ''),
            'start_time'  => $request->post('start_time', '08:00'),
            'end_time'    => $request->post('end_time', '16:00'),
            'color'       => $request->post('color', '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => 1,
        ]);

        $this->auditLogger->log($request, 'shift_type.created', 'shift_type', (int) ($savedType['id'] ?? 0), [
            'name' => $request->post('name', ''),
            'store_id' => $storeId,
        ], $storeId);
        return Response::redirect($this->base() . '/admin/shift-types?success=created');
    }

    public function editShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $type['store_id']);

        return Response::html($this->view->render('admin.shift-types-form', [
            'title'      => 'Modifier ' . htmlspecialchars($type['name'] ?? ''),
            'mode'       => 'edit',
            'shift_type' => $type,
            'all_stores' => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function updateShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $type['store_id']);

        $this->shiftTypes->save(array_merge($type, [
            'store_id'    => (int) $request->post('store_id', $type['store_id'] ?? 0),
            'code'        => strtoupper(trim($request->post('code', $type['code'] ?? ''))),
            'name'        => $request->post('name', $type['name'] ?? ''),
            'start_time'  => $request->post('start_time', $type['start_time'] ?? '08:00'),
            'end_time'    => $request->post('end_time', $type['end_time'] ?? '16:00'),
            'color'       => $request->post('color', $type['color'] ?? '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => $request->post('is_active') === '1' ? 1 : 0,
        ]));

        $this->auditLogger->log($request, 'shift_type.updated', 'shift_type', (int) $type['id'], [
            'name' => $request->post('name', $type['name'] ?? ''),
        ], (int) ($type['store_id'] ?? 0) ?: null);
        return Response::redirect($this->base() . '/admin/shift-types?success=updated');
    }

    public function deleteShiftType(Request $request): Response
    {
        $id   = (int) $request->param('id');
        $type = $this->shiftTypes->findById($id);
        if ($type !== null) {
            $this->assertStoreAccess($request, (int) $type['store_id']);
        }
        $this->shiftTypes->delete($id);
        $this->auditLogger->log($request, 'shift_type.deleted', 'shift_type', $id, [
            'name' => $type['name'] ?? null,
        ], $type ? (int) $type['store_id'] : null);
        return Response::redirect($this->base() . '/admin/shift-types?success=deleted');
    }

    // -------------------------------------------------------------------------
    // Shifts — Import Excel
    // -------------------------------------------------------------------------

    public function importShifts(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        return Response::html($this->view->render('admin.shifts-import', [
            'title'      => 'Importer des shifts Excel',
            'all_stores' => $this->availableStores($managedIds),
            'error'      => $request->query('error'),
        ], 'layout.app'));
    }

    public function processImport(Request $request): Response
    {
        $storeId = (int) $request->post('store_id', 0);
        $this->assertStoreAccess($request, $storeId);

        $file = $_FILES['excel_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return Response::redirect($this->base() . '/admin/shifts/import?error=upload');
        }

        // Sauvegarde de sécurité du fichier uploadé dans storage/uploads/
        $uploadDir = dirname(__DIR__, 4) . '/storage/uploads/';

        // Nom original sécurisé
        $originalName = basename($file['name']);
        $safeName = preg_replace('/[\p{C}]/u', '', $originalName);
        
        // Nouveau format : Upload_YYYY_MM_DD-File(filename.ext)
        $backupName = sprintf(
            'Upload_%s-File(%s)',
            date(format: 'Y_m_d'),
            $safeName
        );

        $backupPath = $uploadDir . $backupName;

        // Déplacement du fichier
        $filePath = move_uploaded_file($file['tmp_name'], $backupPath)
            ? $backupPath
            : $file['tmp_name'];

        try {
            $store    = $this->stores->findById($storeId);
            $settings = json_decode($store['excel_import_settings'] ?? '{}', true) ?: [];
            $service  = new \kintai\Core\Services\ExcelShiftImport\ExcelShiftImportService($settings);
            $entries  = $service->parse($filePath, $file['name']);
        } catch (\Throwable $e) {
            return Response::redirect($this->base() . '/admin/shifts/import?error=parse');
        }

        if (empty($entries)) {
            return Response::redirect($this->base() . '/admin/shifts/import?error=empty');
        }

        // Fusionner les shifts adjacents du même employé le même jour
        // (fin d'un shift = début du suivant → un seul shift)
        // Les paramètres min/max du magasin servent aux alertes timeline, pas à filtrer l'aperçu.
        $entries = $this->mergeAdjacentEntries($entries);

        // Construction de l'index nom → user_id (insensible à la casse)
        $allUsers  = $this->users->findAll();
        $nameIndex = [];
        foreach ($allUsers as $u) {
            $full    = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $display = trim($u['display_name'] ?? '');
            if ($full !== '')    $nameIndex[mb_strtolower($full)]    = (int) $u['id'];
            if ($display !== '') $nameIndex[mb_strtolower($display)] = (int) $u['id'];
        }

        foreach ($entries as &$e) {
            $e['user_id'] = $nameIndex[mb_strtolower($e['staff_name'])] ?? 0;
        }
        unset($e);

        // Analyse des conflits pour chaque entrée (3 cas — même utilisateur uniquement)
        foreach ($entries as &$e) {
            $e['db_exact_match']    = false; // cas 1 : même créneau, même user  → ignorer
            $e['db_overlap']        = false; // cas 2a : chevauchement, même user → remplacer l'ancien
            $e['db_overlap_shifts'] = [];    // détails des shifts remplacés (cas 2a)
            $e['db_duplicate']      = false; // compat UI : true si l'un des cas ci-dessus

            $uid   = (int) ($e['user_id'] ?? 0);
            $date  = $e['date'] ?? '';
            $start = $e['start_time'] ?? '00:00';
            $end   = $e['end_time']   ?? '00:00';
            $cross = ($start !== $end && strtotime($end) !== false && strtotime($start) !== false
                      && strtotime($end) <= strtotime($start)) ? 1 : 0;

            if ($uid <= 0 || $date === '') continue;

            foreach ($this->shifts->findByUserAndDate($uid, $storeId, $date) as $ex) {
                $exCross = (int) ($ex['cross_midnight'] ?? 0);
                if (($ex['start_time'] ?? '') === $start && ($ex['end_time'] ?? '') === $end) {
                    $e['db_exact_match'] = true; // cas 1
                } elseif ($this->timeSlotsOverlap($date, $start, $end, $cross,
                              $ex['shift_date'], $ex['start_time'] ?? '', $ex['end_time'] ?? '', $exCross)) {
                    $e['db_overlap'] = true; // cas 2a
                    $e['db_overlap_shifts'][] = [
                        'start_time' => $ex['start_time'] ?? '',
                        'end_time'   => $ex['end_time']   ?? '',
                    ];
                }
                // cas 2b : même user, même jour, sans chevauchement → coexistent, rien à faire
            }

            $e['db_duplicate'] = $e['db_exact_match'] || $e['db_overlap'];
        }
        unset($e);

        // Détecter si l'import couvre un seul mois (YYYY-MM)
        $importMonths = array_unique(array_filter(
            array_map(fn($e) => substr($e['date'] ?? '', 0, 7), $entries)
        ));
        $importMonth = count($importMonths) === 1 ? reset($importMonths) : '';

        return Response::html($this->view->render('admin.shifts-import-preview', [
            'title'        => 'Prévisualisation — Import shifts',
            'entries'      => $entries,
            'store_id'     => $storeId,
            'all_users'    => $allUsers,
            'import_month' => $importMonth,
        ], 'layout.app'));
    }

    public function confirmImport(Request $request): Response
    {
        $storeId = (int) $request->post('store_id', 0);
        $this->assertStoreAccess($request, $storeId);

        $shiftsJson = $request->post('shifts_json', '');
        $shifts     = is_string($shiftsJson) ? (json_decode($shiftsJson, true) ?: []) : [];
        if (empty($shifts)) {
            return Response::redirect($this->base() . '/admin/shifts?success=imported&count=0');
        }

        // Chargement des paramètres du store et des types actifs
        $store       = $this->stores->findById($storeId);
        $cfg         = array_merge(
            \kintai\Core\Services\ExcelShiftImport\ExcelShiftImportService::DEFAULTS,
            json_decode($store['excel_import_settings'] ?? '{}', true) ?: []
        );
        $pauseAfter  = (int) ($cfg['auto_pause_after_minutes'] ?? 0);
        $pauseDur    = (int) ($cfg['auto_pause_minutes']       ?? 30);
        $shiftTypes  = $this->shiftTypes->findActive($storeId);
        $wageCalc    = new \kintai\Core\Services\ShiftWageCalculator();

        // Pré-collecter les dates couvertes par l'import pour chaque user.
        // Une entrée skippée signifie "conserver l'existant ce jour-là", pas "ce jour n'existe pas".
        // Une date absente de l'import pour un user = ce jour a disparu → shift à supprimer en phase 2.
        $coveredDates = []; // [userId => [date => true]]
        foreach ($shifts as $s) {
            $uid  = (int) ($s['user_id'] ?? 0);
            $date = trim($s['date'] ?? '');
            if ($uid > 0 && $date !== '') {
                $coveredDates[$uid][$date] = true;
            }
        }

        $count   = 0; // nouveaux shifts insérés
        $updated = 0; // shifts remplacés (ancien supprimé)
        $skipped = 0; // ignorés
        foreach ($shifts as $s) {
            if (($s['skip'] ?? '') === '1') { $skipped++; continue; }
            $userId = (int) ($s['user_id'] ?? 0);
            if ($userId <= 0) continue;

            $date      = trim($s['date'] ?? '');
            $startTime = trim($s['start_time'] ?? '00:00');
            $endTime   = trim($s['end_time']   ?? '00:00');
            if ($date === '') continue;

            $start    = strtotime($startTime);
            $end      = strtotime($endTime);
            $duration = (int) (($end - $start) / 60);
            $cross    = 0;
            if ($duration < 0) { $duration += 24 * 60; $cross = 1; }
            $endsDate = $cross ? date('Y-m-d', strtotime($date . ' +1 day')) : $date;

            $pause      = ($pauseAfter > 0 && $duration >= $pauseAfter) ? $pauseDur : 0;
            $netMinutes = $duration - $pause;

            $wage            = $wageCalc->calculate($startTime, $endTime, $shiftTypes, $netMinutes);
            $shiftTypeId     = $wage['dominant_type_id'];
            $estimatedSalary = $wage['estimated_salary'];
            $wageBreakdown   = !empty($wage['breakdown']) ? json_encode($wage['breakdown']) : null;

            // --- Cas 1 & 2 : même utilisateur, même jour ---
            $hasExact    = false;
            $toDeleteIds = [];
            foreach ($this->shifts->findByUserAndDate($userId, $storeId, $date) as $ex) {
                $exCross = (int) ($ex['cross_midnight'] ?? 0);
                if (($ex['start_time'] ?? '') === $startTime && ($ex['end_time'] ?? '') === $endTime) {
                    $hasExact = true; // cas 1 : identique → ignorer
                } elseif ($this->timeSlotsOverlap($date, $startTime, $endTime, $cross,
                              $ex['shift_date'], $ex['start_time'] ?? '', $ex['end_time'] ?? '', $exCross)) {
                    $toDeleteIds[] = (int) $ex['id']; // cas 2a : chevauche → supprimer l'ancien
                }
                // cas 2b : même user, même jour, sans chevauchement → coexistent, rien à supprimer
            }
            if ($hasExact) { $skipped++; continue; }
            foreach ($toDeleteIds as $delId) { $this->shifts->delete($delId); }

            // Compteur
            if (!empty($toDeleteIds)) { $updated++; } else { $count++; }

            $record = [
                'store_id'         => $storeId,
                'user_id'          => $userId,
                'shift_type_id'    => $shiftTypeId,
                'shift_date'       => $date,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'duration_minutes' => $duration,
                'pause_minutes'    => $pause,
                'cross_midnight'   => $cross,
                'starts_at'        => $date . ' ' . $startTime . ':00',
                'ends_at'          => $endsDate . ' ' . $endTime . ':00',
                'estimated_salary' => $estimatedSalary > 0 ? $estimatedSalary : null,
                'wage_breakdown'   => $wageBreakdown,
                'notes'            => null,
            ];

            $this->shifts->save($record);
        }

        // Phase 2 : supprimer les shifts obsolètes.
        // Pour chaque user présent dans l'import, tout shift DB dans la plage de dates
        // dont la date n'apparaît pas dans l'import est considéré supprimé du planning.
        $obsoleteDeleted = 0;
        if (!empty($coveredDates)) {
            $allDates      = array_merge(...array_map('array_keys', $coveredDates));
            $importMinDate = min($allDates);
            $importMaxDate = max($allDates);

            foreach ($coveredDates as $uid => $datesMap) {
                foreach ($this->shifts->findByUser($uid) as $ex) {
                    if ((int) ($ex['store_id'] ?? 0) !== $storeId) continue;
                    $exDate = $ex['shift_date'] ?? '';
                    if ($exDate < $importMinDate || $exDate > $importMaxDate) continue;
                    if (!isset($datesMap[$exDate])) {
                        $this->shifts->delete((int) $ex['id']);
                        $obsoleteDeleted++;
                    }
                }
            }
        }

        $this->auditLogger->log($request, 'shifts.imported', 'shift', null, [
            'store_id'         => $storeId,
            'count'            => $count,
            'updated'          => $updated,
            'skipped'          => $skipped,
            'deleted_obsolete' => $obsoleteDeleted,
        ], $storeId);
        return Response::redirect($this->base() . '/admin/shifts?success=imported&count=' . ($count + $updated));
    }

    // -------------------------------------------------------------------------
    // Helpers import Excel
    // -------------------------------------------------------------------------

    /**
     * Fusionne les entrées adjacentes du même employé le même jour.
     * Deux entrées sont adjacentes si la fin de l'une correspond au début de la suivante.
     *
     * @param array<array{date:string, staff_name:string, start_time:string, end_time:string, hours:float}> $entries
     * @return array
     */
    private function mergeAdjacentEntries(array $entries): array
    {
        // Grouper par (date, staff_name insensible à la casse)
        $groups = [];
        foreach ($entries as $e) {
            $key = $e['date'] . '|' . mb_strtolower($e['staff_name']);
            $groups[$key][] = $e;
        }

        $result = [];
        foreach ($groups as $group) {
            // Trier par heure de début
            usort($group, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

            $merged = $group[0];
            for ($i = 1; $i < count($group); $i++) {
                $cur = $group[$i];
                // Adjacent si fin du précédent = début du courant
                if ($merged['end_time'] === $cur['start_time']) {
                    $merged['end_time'] = $cur['end_time'];
                    $merged['hours']    = round((float) $merged['hours'] + (float) $cur['hours'], 1);
                } else {
                    $result[] = $merged;
                    $merged   = $cur;
                }
            }
            $result[] = $merged;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Helpers d'accès
    // -------------------------------------------------------------------------

    /** Construit un map id → nom d'affichage pour tous les utilisateurs. */
    private function buildUsersMap(): array
    {
        $map = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $map[(int) $u['id']] = $name ?: ($u['email'] ?? '#' . $u['id']);
        }
        return $map;
    }

    /** Construit un map id → nom pour les stores accessibles. */
    private function buildStoresMap(?array $managedIds): array
    {
        $map = [];
        foreach ($this->availableStores($managedIds) as $s) {
            $map[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }
        return $map;
    }

    // -------------------------------------------------------------------------
    // Statistiques d'un store
    // -------------------------------------------------------------------------

    public function storeStats(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $period = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $since  = date('Y-m-d', strtotime("-{$period} days"));
        $today  = date('Y-m-d');

        // --- Chargement des données ---
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

        // Taux horaires (chargement groupé pour éviter N×N requêtes)
        $rateCache = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        // ---- 1. PLANIFICATION ----
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

        $excelCfg    = json_decode($store['excel_import_settings'] ?? '{}', true) ?: [];
        $minShiftMin = (int) ($excelCfg['min_shift_minutes'] ?? 0);
        $maxShiftMin = (int) ($excelCfg['max_shift_minutes'] ?? 0);

        $gaps = [];
        foreach ($shiftsByUser as $userShifts) {
            if (count($userShifts) < 2) continue;
            usort($userShifts, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            for ($i = 1; $i < count($userShifts); $i++) {
                $gap = strtotime($userShifts[$i]['starts_at']) - strtotime($userShifts[$i - 1]['ends_at']);
                if ($gap > 0) $gaps[] = $gap / 3600;
            }
        }

        // ---- 2. PERFORMANCE OPÉRATIONNELLE ----
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

        // ---- 3. CHARGE DE TRAVAIL ----
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

        // ---- 4. STABILITÉ DU PLANNING ----
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

        // ---- 5. RH ----
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

        // ---- 6. ANALYSE FINANCIÈRE ----
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

        // ---- 7. ANALYSE TEMPORELLE ----
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

        // ---- 9. QUALITÉ DU PLANNING ----
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

        // ---- 10. SCORES AVANCÉS ----
        $equityScore    = round((1 - $gini) * 100);
        $efficiencyScore = $totalGrossHours > 0 ? round($totalNetHours / $totalGrossHours * 100) : 100;
        $stabilityScore = round(max(0, 100 - $modRate));
        $burnoutRisk    = 0;
        if ($maxConsecDays > 6) $burnoutRisk += 30;
        elseif ($maxConsecDays > 4) $burnoutRisk += 15;
        $burnoutRisk += min(30, $shortRestCount * 5);
        $burnoutRisk += (($hours ? max($hours) : 0) > 50) ? 20 : (($hours ? max($hours) : 0) > 40 ? 10 : 0);
        $burnoutRisk = min(100, $burnoutRisk);

        return Response::html($this->view->render('admin.store-stats', [
            'title'                  => 'Statistiques — ' . ($store['name'] ?? ''),
            'store'                  => $store,
            'period'                 => $period,
            'since'                  => $since,
            'today'                  => $today,
            'currency'               => $store['currency'] ?? 'EUR',
            'n'                      => $n,
            'usersMap'               => $usersMap,
            'memberIds'              => $memberIds,
            // 1. Planification
            'avgDuration'            => round($avgDuration, 2),
            'distShort'              => $distShort,
            'distMedium'             => $distMedium,
            'distLong'               => $distLong,
            'avgShiftsPerEmployee'   => round($avgShiftsPerEmployee, 1),
            'avgDaysPerEmployee'     => round($avgDaysPerEmployee, 1),
            'openingShifts'          => $openingShifts,
            'closingShifts'          => $closingShifts,
            'minShiftMin'            => $minShiftMin,
            'maxShiftMin'            => $maxShiftMin,
            'shortRateShifts'        => $minShiftMin > 0 ? count(array_filter($durations, fn($d) => $d < $minShiftMin)) : null,
            'longRateShifts'         => $maxShiftMin > 0 ? count(array_filter($durations, fn($d) => $d > $maxShiftMin)) : null,
            'avgTimeBetweenShifts'   => $gaps ? round(array_sum($gaps) / count($gaps), 1) : null,
            // 2. Performance
            'totalNetHours'          => round($totalNetHours, 1),
            'totalGrossHours'        => round($totalGrossHours, 1),
            'avgEmpPerHour'          => round($avgEmpPerHour, 2),
            'hoursBySlot'            => $hoursBySlot,
            'activeDays'             => $activeDays,
            // 3. Charge
            'hoursByUser'            => $hoursByUser,
            'meanHours'              => round($meanHours, 1),
            'stdDev'                 => round($stdDev, 1),
            'gini'                   => round($gini, 3),
            'top20ratio'             => $top20ratio,
            'hoursByType'            => $hoursByType,
            // 4. Stabilité
            'modRate'                => $modRate,
            'avgModDelay'            => $modDelays ? round(array_sum($modDelays) / count($modDelays), 1) : null,
            'modByDow'               => $modByDow,
            'shiftsCreatedByManager' => $shiftsCreatedByManager,
            'modByManager'           => $modByManager,
            // 5. RH
            'timeoffsByStatus'       => $timeoffsByStatus,
            'timeoffsByType'         => $timeoffsByType,
            'timeoffsByUser'         => $timeoffsByUser,
            'absRate'                => $absRate,
            'approvedDays'           => $approvedDays,
            // 6. Financier
            'totalCost'              => round($totalCost, 2),
            'avgCostPerShift'        => $n ? round($totalCost / $n, 2) : 0,
            'avgCostPerHour'         => $totalNetHours > 0 ? round($totalCost / $totalNetHours, 2) : 0,
            'costByType'             => $costByType,
            'costByUser'             => $costByUser,
            'costByMonth'            => $costByMonth,
            'hoursByMonth'           => $hoursByMonth,
            // 7. Temporel
            'hoursByDow'             => $hoursByDow,
            'shiftsByDow'            => $shiftsByDow,
            'costByDow'              => $costByDow,
            'hoursByWeek'            => $hoursByWeek,
            'dowLabels'              => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            // 9. Qualité
            'conflictsCount'         => $conflictsCount,
            'maxConsecDays'          => $maxConsecDays,
            'avgConsecDays'          => $consecCount ? round($consecSum / $consecCount, 1) : 0,
            'shortRestCount'         => $shortRestCount,
            // 10. Scores
            'equityScore'            => $equityScore,
            'efficiencyScore'        => $efficiencyScore,
            'stabilityScore'         => $stabilityScore,
            'burnoutRisk'            => $burnoutRisk,
        ], 'layout.app'));
    }

    public function employeePayslip(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        [$from, $to] = $this->parseDateRange($request);
        $data        = $this->buildPayslipData($storeId, $userId, $from, $to, $store, $membership);

        return Response::html($this->view->render('admin.employee-payslip', array_merge($data, [
            'store'      => $store,
            'user'       => $user,
            'membership' => $membership,
            'currency'   => $store['currency'] ?? 'EUR',
            'autoprint'  => $request->query('autoprint') === '1',
        ])));
    }

    public function employeePayslipPdf(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        [$from, $to] = $this->parseDateRange($request);
        $data        = $this->buildPayslipData($storeId, $userId, $from, $to, $store, $membership);

        $html = $this->view->render('admin.employee-payslip-pdf', array_merge($data, [
            'store'      => $store,
            'user'       => $user,
            'membership' => $membership,
            'currency'   => $store['currency'] ?? 'EUR',
        ]));

        $tmpDir = dirname(__DIR__, 4) . '/storage/app/mpdf';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 16,
            'margin_bottom' => 16,
            'tempDir'       => $tmpDir,
        ]);
        $mpdf->SetTitle('Fiche de paie');
        $mpdf->WriteHTML($html);

        $slug     = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', trim(($user['first_name'] ?? '') . '_' . ($user['last_name'] ?? '')))) ?: 'employe';
        $filename = 'payslip_' . $slug . '_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.pdf';

        $pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        return Response::pdf($pdfBytes, $filename);
    }

    public function employeeStats(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        $period = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $since  = date('Y-m-d', strtotime("-{$period} days"));
        $today  = date('Y-m-d');

        // Tous les shifts actifs de l'employé dans ce store
        $allShifts = array_values(array_filter(
            $this->shifts->findByStore($storeId),
            fn($s) => empty($s['deleted_at']) && (int) $s['user_id'] === $userId
        ));

        // Shifts de la période sélectionnée
        $periodShifts = array_values(array_filter(
            $allShifts,
            fn($s) => $s['shift_date'] >= $since && $s['shift_date'] <= $today
        ));

        // Shifts des 12 derniers mois (pour le graphique mensuel)
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

        // --- KPIs période ---
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

        // Jours consécutifs max
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

        // Absences approuvées sur la période
        $absDays = 0;
        foreach ($this->timeoffRequests->findByStore($storeId) as $t) {
            if ((int) ($t['user_id'] ?? 0) !== $userId) continue;
            if (($t['status'] ?? '') !== 'approved') continue;
            if (($t['start_date'] ?? '') < $since) continue;
            $absDays += max(1, (int) round((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
        }

        // Échanges sur la période
        $swapCount = 0;
        foreach ($this->swapRequests->findByStore($storeId) as $sw) {
            if (((int) ($sw['requester_id'] ?? 0) === $userId || (int) ($sw['target_id'] ?? 0) === $userId)
                && ($sw['created_at'] ?? '') >= $since
            ) {
                $swapCount++;
            }
        }

        // --- Graphique mensuel (12 mois) ---
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

        // --- Répartition par type de shift (période) ---
        $typeHours = [];
        foreach ($periodShifts as $s) {
            $tid   = $s['shift_type_id'] ? (int) $s['shift_type_id'] : null;
            $label = $tid ? ($storeTypesMap[$tid]['name'] ?? 'Type ' . $tid) : 'Sans type';
            $typeHours[$label] = ($typeHours[$label] ?? 0.0)
                + max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }
        arsort($typeHours);

        // --- Répartition par jour de semaine (période) ---
        $dowLabels = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $dowBuckets = array_fill(0, 7, 0.0);
        foreach ($periodShifts as $s) {
            $dow = (int) (new \DateTime($s['shift_date']))->format('w');
            $dowBuckets[$dow] += max(0, (int) $s['duration_minutes'] - (int) $s['pause_minutes']) / 60;
        }
        $dowChart = array_combine($dowLabels, $dowBuckets);

        // --- Shifts récents ---
        $recentShifts = array_slice(
            array_reverse(array_filter($allShifts, fn($s) => $s['shift_date'] <= $today)),
            0,
            15
        );

        $empName  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['email'] ?? '');
        $payslipUrl = $this->base() . '/admin/stores/' . $storeId . '/employee-report/' . $userId . '/payslip?period=' . $period;
        $pdfUrl     = $this->base() . '/admin/stores/' . $storeId . '/employee-report/' . $userId . '/payslip/pdf?period=' . $period;

        return Response::html($this->view->render('admin.employee-stats', [
            'title'         => 'Statistiques — ' . $empName,
            'store'         => $store,
            'user'          => $user,
            'membership'    => $membership,
            'period'        => $period,
            'since'         => $since,
            'today'         => $today,
            'currency'      => $store['currency'] ?? 'EUR',
            'totalShifts'   => count($periodShifts),
            'grossHours'    => round($grossMin / 60, 1),
            'netHours'      => round($netMin / 60, 1),
            'cost'          => round($cost, 2),
            'anyRate'       => $anyRate,
            'workDays'      => count($workDays),
            'absDays'       => $absDays,
            'maxConsec'     => $maxConsec,
            'swapCount'     => $swapCount,
            'monthlyHours'  => $monthlyHours,
            'typeHours'     => $typeHours,
            'dowChart'      => $dowChart,
            'recentShifts'  => $recentShifts,
            'storeTypesMap' => $storeTypesMap,
            'payslipUrl'    => $payslipUrl,
            'pdfUrl'        => $pdfUrl,
        ], 'layout.app'));
    }

    public function employeeReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $period = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $since  = date('Y-m-d', strtotime("-{$period} days"));
        $today  = date('Y-m-d');

        // --- Chargement des données ---
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

        // Taux horaires (chargement groupé)
        $rateCache = [];
        foreach ($memberIds as $uid) {
            foreach ($this->userRates->findByUser($uid) as $r) {
                $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }
        }

        // Grouper les shifts par user
        $shiftsByUser = [];
        foreach ($allShifts as $s) {
            $shiftsByUser[(int) $s['user_id']][] = $s;
        }

        // Grouper les absences approuvées par user (en jours)
        $absencesByUser = [];
        foreach ($allTimeoffs as $t) {
            $uid  = (int) $t['user_id'];
            $days = max(1, (int) round((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1);
            $absencesByUser[$uid] = ($absencesByUser[$uid] ?? 0) + $days;
        }

        // Grouper les échanges (demandeur ou cible) par user
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

        // --- Calcul des statistiques par employé ---
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

            // Calcul jours consécutifs max
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

        // Tri par heures nettes décroissantes
        uasort($employeeStats, fn($a, $b) => $b['net_hours'] <=> $a['net_hours']);

        // Totaux
        $activeStats     = array_filter($employeeStats, fn($e) => $e['shifts'] > 0);
        $activeCount     = count($activeStats);
        $totalShifts     = array_sum(array_column($employeeStats, 'shifts'));
        $totalNetHours   = array_sum(array_column($employeeStats, 'net_hours'));
        $totalGrossHours = array_sum(array_column($employeeStats, 'gross_hours'));
        $totalCost       = array_sum(array_column($employeeStats, 'cost'));
        $totalAbsDays    = array_sum(array_column($employeeStats, 'abs_days'));
        $anyHasRate      = !empty(array_filter($employeeStats, fn($e) => $e['has_rate']));

        return Response::html($this->view->render('admin.employee-report', [
            'title'           => 'Rapport employés — ' . ($store['name'] ?? ''),
            'store'           => $store,
            'period'          => $period,
            'since'           => $since,
            'today'           => $today,
            'currency'        => $store['currency'] ?? 'EUR',
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
        ], 'layout.app'));
    }

    /**
     * Prépare les données communes pour l'affichage/export de la fiche de paie.
     * Retourne : since, today, shiftRows, totalGrossMin, totalNetMin, totalCost, anyRate
     */
    private function buildPayslipData(int $storeId, int $userId, string $from, string $to, array $store = [], array $membership = []): array
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

        // --- Cotisations sociales ---
        $deductionSettings  = json_decode($store['deduction_settings'] ?? '{}', true) ?? [];
        $deductionOverrides = json_decode($membership['deduction_overrides'] ?? '{}', true) ?? [];
        $deductions         = [];
        $totalDeductions    = 0.0;
        $netPay             = $totalCost;

        $subjectToDeductions = !empty($deductionOverrides['subject_to_deductions']);

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
            // Impôt résidence (montant fixe mensuel)
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

    private function parseDateRange(Request $request): array
    {
        $from = (string) ($request->query('from') ?? '');
        $to   = (string) ($request->query('to')   ?? '');

        $valid = fn(string $d): bool => (bool) \DateTime::createFromFormat('Y-m-d', $d);

        if (!$valid($from) || !$valid($to) || $from > $to) {
            $from = date('Y-m-01');
            $to   = date('Y-m-t');
        }

        return [$from, $to];
    }

    /**
     * Vérifie si deux créneaux horaires se chevauchent.
     * Gère les shifts en cross-midnight (fin < début = lendemain).
     */
    private function timeSlotsOverlap(
        string $dateA, string $startA, string $endA, int $crossA,
        string $dateB, string $startB, string $endB, int $crossB
    ): bool {
        $tsStartA = strtotime($dateA . ' ' . $startA);
        $tsEndA   = $crossA
            ? strtotime(date('Y-m-d', strtotime($dateA . ' +1 day')) . ' ' . $endA)
            : strtotime($dateA . ' ' . $endA);
        $tsStartB = strtotime($dateB . ' ' . $startB);
        $tsEndB   = $crossB
            ? strtotime(date('Y-m-d', strtotime($dateB . ' +1 day')) . ' ' . $endB)
            : strtotime($dateB . ' ' . $endB);
        if ($tsStartA === false || $tsEndA === false || $tsStartB === false || $tsEndB === false) return false;
        return $tsStartA < $tsEndB && $tsStartB < $tsEndA;
    }

    /**
     * Retourne les IDs des stores gérés, ou null pour un admin global (pas de restriction).
     * @return int[]|null
     */
    private function managedIds(Request $request): ?array
    {
        return $request->getAttribute('managed_store_ids');
    }

    /**
     * Filtre un tableau d'entités en ne gardant que ceux dont la colonne $key
     * est dans la liste des stores gérés.
     * Si $managedIds === null (admin global), retourne tout sans filtrage.
     */
    private function filterByStore(array $items, ?array $managedIds, string $key = 'store_id'): array
    {
        if ($managedIds === null) {
            return $items;
        }
        return array_values(array_filter(
            $items,
            fn($i) => in_array((int) ($i[$key] ?? 0), $managedIds, true)
        ));
    }

    /**
     * Retourne les stores accessibles selon le profil (tous pour admin, filtrés pour manager).
     */
    private function availableStores(?array $managedIds): array
    {
        $all = $this->stores->findAll();
        if ($managedIds === null) {
            return $all;
        }
        return array_values(array_filter($all, fn($s) => in_array((int) $s['id'], $managedIds, true)));
    }

    /**
     * Retourne les IDs des utilisateurs membres des stores gérés.
     * @return int[]
     */
    private function memberUserIds(array $managedIds): array
    {
        $ids = [];
        foreach ($managedIds as $storeId) {
            foreach ($this->storeUsers->findByStore($storeId) as $m) {
                $ids[] = (int) $m['user_id'];
            }
        }
        return array_unique($ids);
    }

    /** Calcule le BASE_URL (chemin de base sans slash final) pour les redirections. */
    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }

    /**
     * Vérifie que l'utilisateur courant a le droit de gérer ce store.
     * Les admins globaux (managed_store_ids = null) passent toujours.
     * Les managers ne passent que si le store est dans leur liste.
     *
     * @throws ForbiddenException
     */
    private function assertStoreAccess(Request $request, int $storeId): void
    {
        $managedIds = $request->getAttribute('managed_store_ids'); // null = admin global
        if ($managedIds !== null && !in_array($storeId, $managedIds, true)) {
            throw new ForbiddenException('Vous n\'êtes pas gestionnaire de ce store.');
        }
    }
}
