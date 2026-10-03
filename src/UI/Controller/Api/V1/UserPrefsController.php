<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Auth\UserTargetGuard;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class UserPrefsController
{
    public function __construct(
        private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs,
        private readonly UserNavPrefsRepositoryInterface $navPrefs,
        private readonly UserTargetGuard $guard,
    ) {}

    /** GET /api/v1/users/{user_id}/dashboard-prefs?type=admin */
    public function getDashboardPrefs(Request $request): Response
    {
        $userId        = (int) $request->param('user_id');
        $dashboardType = $request->query('type', 'admin') ?? 'admin';
        $this->guard->require($request, $userId, 'employees.view');

        return Response::json([
            'user_id'        => $userId,
            'dashboard_type' => $dashboardType,
            'widgets'        => $this->dashboardPrefs->getEnabledWidgets($userId, $dashboardType),
        ]);
    }

    /** PUT /api/v1/users/{user_id}/dashboard-prefs */
    public function saveDashboardPrefs(Request $request): Response
    {
        $userId        = (int) $request->param('user_id');
        $this->guard->require($request, $userId, 'employees.update');

        $data          = $request->json() ?? [];
        $dashboardType = (string) ($data['dashboard_type'] ?? 'admin');
        $widgets       = (array) ($data['widgets'] ?? []);

        $this->dashboardPrefs->saveWidgets($userId, $widgets, $dashboardType);

        return Response::json([
            'user_id'        => $userId,
            'dashboard_type' => $dashboardType,
            'widgets'        => $widgets,
        ]);
    }

    /** GET /api/v1/users/{user_id}/nav-prefs */
    public function getNavPrefs(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->guard->require($request, $userId, 'employees.view');

        return Response::json([
            'user_id' => $userId,
            'hidden'  => $this->navPrefs->getHidden($userId),
        ]);
    }

    /** PUT /api/v1/users/{user_id}/nav-prefs */
    public function saveNavPrefs(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->guard->require($request, $userId, 'employees.update');

        $data   = $request->json() ?? [];
        $hidden = (array) ($data['hidden'] ?? []);

        $this->navPrefs->saveHidden($userId, $hidden);

        return Response::json(['user_id' => $userId, 'hidden' => $hidden]);
    }
}
