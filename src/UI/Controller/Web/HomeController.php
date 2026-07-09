<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class HomeController
{
    public const ADMIN_WIDGETS = [
        'kpi_counters',
        'quick_nav',
        'shifts_today',
        'pending_timeoff',
        'pending_swaps',
        'timeclocks_today',
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs,
        private readonly TimeclockRepositoryInterface $timeclocks,
    ) {}

    public function index(Request $request): Response
    {
        $user  = $request->getAttribute('auth_user');
        $today = date('Y-m-d');

        $allUsers    = $this->users->findAll();
        $allStores   = $this->stores->findAll();
        $shiftsToday = $this->shifts->findAllByDate($today);
        $allTimeoff  = $this->timeoffRequests->findAll();
        $allSwaps    = $this->swapRequests->findAll();

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
        $allTimeclocks   = $this->timeclocks->findAll();
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
