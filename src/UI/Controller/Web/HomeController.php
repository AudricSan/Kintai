<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\ViewRenderer;

final class HomeController
{
    public const ADMIN_WIDGETS = [
        'kpi_counters',
        'quick_nav',
        'store_stats_summary',
        'shifts_today',
        'pending_timeoff',
        'pending_swaps',
        'timeclocks_today',
    ];

    /** Fenêtre glissante (jours) du widget "Aperçu statistiques", alignée sur la période "30 jours" des pages analytics. */
    private const STATS_WIDGET_PERIOD_DAYS = 30;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs,
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreStatsServiceInterface $storeStats,
        private readonly PermissionService $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $user  = $request->getAttribute('auth_user');
        $today = date('Y-m-d');

        // Manager restreint à un sous-ensemble de stores (AdminMiddleware) : null = admin
        // global, aucune restriction. Toutes les données du dashboard doivent être
        // bornées à cette liste pour éviter de divulguer des données d'autres stores.
        $managedStoreIds = $request->getAttribute('managed_store_ids');
        $inScope = fn(int $storeId): bool => $managedStoreIds === null || in_array($storeId, $managedStoreIds, true);

        $allUsers = $this->users->findAll();
        if ($managedStoreIds !== null) {
            $scopedUserIds = [];
            foreach ($managedStoreIds as $sid) {
                foreach ($this->storeUsers->findByStore($sid) as $su) {
                    $scopedUserIds[(int) $su['user_id']] = true;
                }
            }
            $allUsers = array_values(array_filter($allUsers, fn($u) => isset($scopedUserIds[(int) $u['id']])));
        }

        $allStores   = array_values(array_filter($this->stores->findAll(), fn($s) => $inScope((int) $s['id'])));
        $shiftsToday = array_values(array_filter($this->shifts->findAllByDate($today), fn($s) => $inScope((int) ($s['store_id'] ?? 0))));
        $allTimeoff  = array_values(array_filter($this->timeoffRequests->findAll(), fn($r) => $inScope((int) ($r['store_id'] ?? 0))));
        $allSwaps    = array_values(array_filter($this->swapRequests->findAll(), fn($r) => $inScope((int) ($r['store_id'] ?? 0))));

        $pendingTimeoff = array_values(array_filter($allTimeoff, fn($r) => ($r['status'] ?? '') === 'pending'));
        $pendingSwaps   = array_values(array_filter($allSwaps,   fn($r) => ($r['status'] ?? '') === 'pending'));

        $storesMap = [];
        foreach ($allStores as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }
        $usersMap = [];
        foreach ($allUsers as $u) {
            $usersMap[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? ('#' . $u['id']);
        }

        $shiftsToday = array_map(function (array $s) use ($storesMap, $usersMap): array {
            $s['store_name'] = $storesMap[(int) ($s['store_id'] ?? 0)] ?? ('Store #' . (int) ($s['store_id'] ?? 0));
            $s['user_name']  = $usersMap[(int)  ($s['user_id']  ?? 0)] ?? ('User #'  . (int) ($s['user_id']  ?? 0));
            return $s;
        }, $shiftsToday);

        $validSorts = ['start_asc', 'start_desc', 'end_asc', 'end_desc', 'user_asc', 'user_desc', 'store_asc', 'store_desc', 'pause_asc', 'pause_desc'];
        $sort = $request->query('sort', 'start_asc');
        if (!in_array($sort, $validSorts, true)) {
            $sort = 'start_asc';
        }
        [$sortField, $sortDir] = explode('_', $sort, 2);
        usort($shiftsToday, function (array $a, array $b) use ($sortField, $sortDir): int {
            $va = match ($sortField) {
                'start' => $a['start_time'] ?? '',
                'end'   => $a['end_time'] ?? '',
                'user'  => $a['user_name'] ?? '',
                'store' => $a['store_name'] ?? '',
                'pause' => (int) ($a['pause_minutes'] ?? 0),
                default => '',
            };
            $vb = match ($sortField) {
                'start' => $b['start_time'] ?? '',
                'end'   => $b['end_time'] ?? '',
                'user'  => $b['user_name'] ?? '',
                'store' => $b['store_name'] ?? '',
                'pause' => (int) ($b['pause_minutes'] ?? 0),
                default => '',
            };
            $cmp = is_int($va) ? ($va <=> $vb) : strcmp((string) $va, (string) $vb);
            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        // Pointages actifs en ce moment
        $allTimeclocks   = array_values(array_filter($this->timeclocks->findAll(), fn($tc) => $inScope((int) ($tc['store_id'] ?? 0))));
        $activeClocksNow = array_values(array_filter(
            $allTimeclocks,
            fn($tc) => ($tc['shift_date'] ?? '') === $today && empty($tc['clock_out_time'])
        ));
        $activeClocksNow = array_map(function (array $tc) use ($usersMap, $storesMap): array {
            $tc['user_name']  = $usersMap[(int)  ($tc['user_id']  ?? 0)] ?? ('User #'  . (int) ($tc['user_id']  ?? 0));
            $tc['store_name'] = $storesMap[(int) ($tc['store_id'] ?? 0)] ?? ('Store #' . (int) ($tc['store_id'] ?? 0));
            return $tc;
        }, $activeClocksNow);
        usort($activeClocksNow, fn($a, $b) => strcmp($a['clock_in_time'] ?? '', $b['clock_in_time'] ?? ''));

        $saved = $this->dashboardPrefs->getEnabledWidgets((int) $user['id'], 'admin');
        $enabledWidgets = array_flip(array_values(
            $saved !== null
                ? array_intersect($saved, self::ADMIN_WIDGETS)
                : self::ADMIN_WIDGETS
        ));

        // Aperçu statistiques (heures, coût, scores) : même permission que les pages analytics
        // complètes (admin.stores.stats), calculé uniquement si le widget est actif pour éviter
        // le coût de multiStoreComparison() sur chaque chargement du dashboard.
        $canViewStats = !empty($user['is_admin']) || $this->permissions->can($user, 'payroll.view', null);
        $storeStatsRows = [];
        $storeStatsHoursByWeek = [];
        if ($canViewStats && array_key_exists('store_stats_summary', $enabledWidgets)) {
            $statsStoreIds  = $managedStoreIds ?? array_column($allStores, 'id');
            $storeStatsRows = $this->storeStats->multiStoreComparison($statsStoreIds, self::STATS_WIDGET_PERIOD_DAYS);
            // Tendance hebdomadaire : uniquement pour un seul store géré, sinon "heures par
            // semaine" mélangerait plusieurs stores sans les distinguer (peu lisible en graphique).
            if (count($storeStatsRows) === 1) {
                $storeStatsHoursByWeek = $this->storeStats
                    ->storeStats((int) $storeStatsRows[0]['store_id'], self::STATS_WIDGET_PERIOD_DAYS)['hoursByWeek'] ?? [];
            }
        }

        return Response::html($this->view->render('dashboard.index', [
            'title'              => 'Dashboard',
            'stats'              => [
                'users'            => count($allUsers),
                'stores'           => count($allStores),
                'shifts_today'     => count($shiftsToday),
                'pending_requests' => count($pendingTimeoff) + count($pendingSwaps),
            ],
            'shifts_today'       => $shiftsToday,
            'pending_timeoff'    => $pendingTimeoff,
            'pending_swaps'      => $pendingSwaps,
            'users_map'          => $usersMap,
            'sort'               => $sort,
            'active_clocks_now'  => $activeClocksNow,
            'store_stats_rows'   => $storeStatsRows,
            'store_stats_period' => self::STATS_WIDGET_PERIOD_DAYS,
            'store_stats_hours_by_week' => $storeStatsHoursByWeek,

            'enabled_widgets'    => $enabledWidgets,
            'all_widgets'        => self::ADMIN_WIDGETS,
        ], 'layout.app'));
    }

    public function saveDashboardWidgets(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) $user['id'];

        $checked = (array) ($request->allPost()['widgets'] ?? []);
        $widgets = array_values(array_intersect(self::ADMIN_WIDGETS, array_keys($checked)));

        $this->dashboardPrefs->saveWidgets($userId, $widgets, 'admin');

        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        $base = ($base === '.' || $base === '/') ? '' : $base;
        return Response::redirect($base . '/?success=saved');
    }
}
