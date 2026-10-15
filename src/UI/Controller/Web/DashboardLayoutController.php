<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Contract\Extension\DashboardWidget;
use kintai\Core\Extension\DashboardLayout;
use kintai\Core\Extension\DashboardWidgets;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Enregistrement de la disposition des tableaux de bord (ordre et largeur des widgets), appelé en arrière-plan par
 * dashboard-layout.js à chaque déplacement en mode édition. Corps JSON : {order: [...], widths: {clé: half|full}}
 * pour les widgets affichés, ou {reset: true} pour revenir à la disposition par défaut.
 */
final class DashboardLayoutController
{
    public function __construct(private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs) {}

    /** POST /admin/dashboard/layout */
    public function saveManager(Request $request): Response
    {
        return $this->save($request, 'admin', DashboardWidget::MANAGER, HomeController::ADMIN_WIDGETS);
    }

    /** POST /employee/dashboard/layout */
    public function saveEmployee(Request $request): Response
    {
        return $this->save($request, 'employee', DashboardWidget::EMPLOYEE, EmployeeController::EMPLOYEE_WIDGETS);
    }

    /** @param list<string> $coreKeys */
    private function save(Request $request, string $dashboardType, string $audience, array $coreKeys): Response
    {
        $userId = (int) ($request->getAttribute('auth_user')['id'] ?? 0);
        $body   = $request->json() ?? [];

        if (($body['reset'] ?? false) === true) {
            $this->dashboardPrefs->saveLayout($userId, $dashboardType, null);
            return Response::json(['layout' => null]);
        }

        $layout = DashboardLayout::update(
            DashboardWidgets::current()->keys($audience, $coreKeys),
            $this->dashboardPrefs->getLayout($userId, $dashboardType),
            is_array($body['order'] ?? null) ? array_values($body['order']) : [],
            is_array($body['widths'] ?? null) ? $body['widths'] : [],
        );
        $this->dashboardPrefs->saveLayout($userId, $dashboardType, $layout);

        return Response::json(['layout' => $layout]);
    }
}
