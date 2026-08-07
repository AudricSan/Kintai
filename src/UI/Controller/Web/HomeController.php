<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\DashboardAlertService;
use kintai\Core\Services\ShiftWageCalculator;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\ViewRenderer;

final class HomeController
{
    public const ADMIN_WIDGETS = [
        'kpi_counters',
        'quick_nav',
        'dashboard_alerts',
        'store_stats_summary',
        'financial_overview',
        'hr_absenteeism',
        'shifts_today',
        'pending_timeoff',
        'pending_swaps',
        'timeclocks_today',
    ];

    /** Fenêtre glissante (jours) du widget "Aperçu statistiques", alignée sur la période "30 jours" des pages analytics. */
    private const STATS_WIDGET_PERIOD_DAYS = 30;

    /** Fenêtre glissante (jours) pour la tendance mensuelle du widget financier (~6 mois). */
    private const FINANCIAL_TREND_PERIOD_DAYS = 180;

    /** Écart (minutes) entre l'heure de pointage réelle et l'heure de début du shift au-delà duquel un pointage est compté "en retard". */
    private const HR_LATE_THRESHOLD_MINUTES = 5;

    /** Heures nettes hebdomadaires au-delà desquelles une semaine (utilisateur) est comptée en heures supplémentaires. */
    private const HR_OVERTIME_WEEKLY_HOURS = 40;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs,
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreStatsServiceInterface $storeStats,
        private readonly DashboardAlertService $dashboardAlerts,
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

        // Alertes actionnables : shifts non pourvus, employés sans planning, demandes/pointages en retard.
        $dashboardAlerts = null;
        if (array_key_exists('dashboard_alerts', $enabledWidgets)) {
            $alertStoreIds   = $managedStoreIds ?? array_column($allStores, 'id');
            $dashboardAlerts = $this->dashboardAlerts->build($alertStoreIds, $storesMap);
        }

        // Vue d'ensemble financière : masse salariale du mois en cours vs précédent + tendance 6 mois.
        $financialOverview = null;
        if ($canViewStats && array_key_exists('financial_overview', $enabledWidgets)) {
            $finStoreIds = $managedStoreIds ?? array_column($allStores, 'id');
            $financialOverview = $this->buildFinancialOverview($finStoreIds);
        }

        // Absentéisme / RH : congés pris, retards, heures supplémentaires sur la période "30 jours".
        $hrStats = null;
        if ($canViewStats && array_key_exists('hr_absenteeism', $enabledWidgets)) {
            $hrStoreIds = $managedStoreIds ?? array_column($allStores, 'id');
            $hrStats    = $this->buildHrStats($hrStoreIds);
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
            'dashboard_alerts'   => $dashboardAlerts,
            'financial_overview' => $financialOverview,
            'hr_stats'           => $hrStats,

            'enabled_widgets'    => $enabledWidgets,
            'all_widgets'        => self::ADMIN_WIDGETS,
        ], 'layout.app'));
    }

    /**
     * Masse salariale globale (stores en scope) : mois en cours vs précédent,
     * + tendance mensuelle sur ~6 mois (somme de costByMonth par store géré).
     */
    private function buildFinancialOverview(array $storeIds): ?array
    {
        if ($storeIds === []) {
            return null;
        }

        $costByMonth = [];
        foreach ($storeIds as $sid) {
            $stats = $this->storeStats->storeStats((int) $sid, self::FINANCIAL_TREND_PERIOD_DAYS);
            foreach ($stats['costByMonth'] as $month => $cost) {
                $costByMonth[$month] = ($costByMonth[$month] ?? 0) + $cost;
            }
        }
        ksort($costByMonth);
        $trend = array_slice($costByMonth, -6, 6, true);

        $currentMonthKey = date('Y-m');
        $prevMonthKey    = date('Y-m', strtotime('-1 month'));
        $currentCost     = $costByMonth[$currentMonthKey] ?? 0.0;
        $prevCost        = $costByMonth[$prevMonthKey] ?? 0.0;

        $firstStore = $this->stores->findById((int) $storeIds[array_key_first($storeIds)]);

        return [
            'current_month'  => $currentCost,
            'previous_month' => $prevCost,
            'delta_pct'      => $prevCost > 0 ? round(($currentCost - $prevCost) / $prevCost * 100, 1) : null,
            'trend_by_month' => $trend,
            'currency'       => $firstStore['currency'] ?? 'EUR',
            'currency_style' => store_currency_style($firstStore),
        ];
    }

    /**
     * Absentéisme / RH sur la période "30 jours" (stores en scope) : taux
     * d'absentéisme moyen, congés pris/par type, retards (pointage vs shift
     * planifié — comparés par utilisateur+date, les timeclocks n'ayant pas de
     * lien direct vers un shift_id), semaines en heures supplémentaires.
     */
    private function buildHrStats(array $storeIds): ?array
    {
        if ($storeIds === []) {
            return null;
        }

        $since = date('Y-m-d', strtotime('-' . self::STATS_WIDGET_PERIOD_DAYS . ' days'));
        $today = date('Y-m-d');
        $wageCalc = new ShiftWageCalculator();

        $absRates       = [];
        $timeoffTaken   = 0;
        $timeoffByType  = [];
        $lateCount      = 0;
        $overtimeWeeks  = 0;

        foreach ($storeIds as $sid) {
            $sid = (int) $sid;
            $stats = $this->storeStats->storeStats($sid, self::STATS_WIDGET_PERIOD_DAYS);
            $absRates[] = $stats['absRate'];
            $timeoffTaken += $stats['timeoffsByStatus']['approved'] ?? 0;
            foreach ($stats['timeoffsByType'] as $type => $count) {
                $timeoffByType[$type] = ($timeoffByType[$type] ?? 0) + $count;
            }

            $periodShifts = array_values(array_filter(
                $this->shifts->findByStore($sid),
                fn($s) => empty($s['deleted_at']) && $s['user_id'] !== null
                    && $s['shift_date'] >= $since && $s['shift_date'] <= $today
            ));

            $shiftStartByUserDate = [];
            foreach ($periodShifts as $s) {
                $shiftStartByUserDate[$s['user_id'] . '|' . $s['shift_date']] = $s['start_time'];
            }

            $periodTimeclocks = array_values(array_filter(
                $this->timeclocks->findByStore($sid),
                fn($tc) => ($tc['shift_date'] ?? '') >= $since && ($tc['shift_date'] ?? '') <= $today
            ));
            foreach ($periodTimeclocks as $tc) {
                $key = ($tc['user_id'] ?? '') . '|' . ($tc['shift_date'] ?? '');
                if (!isset($shiftStartByUserDate[$key])) {
                    continue;
                }
                $expected = strtotime($tc['shift_date'] . ' ' . $shiftStartByUserDate[$key]);
                $actual   = strtotime((string) ($tc['clock_in_time'] ?? ''));
                if ($expected === false || $actual === false) {
                    continue;
                }
                if (($actual - $expected) / 60 > self::HR_LATE_THRESHOLD_MINUTES) {
                    $lateCount++;
                }
            }

            $storeTypesMap = array_column($this->shiftTypes->findByStore($sid), null, 'id');
            $hoursByUserWeek = [];
            foreach ($periodShifts as $s) {
                $wage = $wageCalc->costOf($s, $storeTypesMap);
                $week = date('o-\WW', strtotime($s['shift_date']));
                $key  = $s['user_id'] . '|' . $week;
                $hoursByUserWeek[$key] = ($hoursByUserWeek[$key] ?? 0) + $wage['net_minutes'] / 60;
            }
            foreach ($hoursByUserWeek as $hours) {
                if ($hours > self::HR_OVERTIME_WEEKLY_HOURS) {
                    $overtimeWeeks++;
                }
            }
        }

        arsort($timeoffByType);

        return [
            'abs_rate'        => $absRates !== [] ? round(array_sum($absRates) / count($absRates), 2) : 0.0,
            'timeoff_taken'   => $timeoffTaken,
            'timeoff_by_type' => $timeoffByType,
            'late_count'      => $lateCount,
            'overtime_weeks'  => $overtimeWeeks,
            'period_days'     => self::STATS_WIDGET_PERIOD_DAYS,
        ];
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
