<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Contract\Extension\DashboardWidget;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Extension\DashboardLayout;
use kintai\Core\Extension\DashboardWidgets;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

/**
 * Personnalisation des tableaux de bord, appelée en arrière-plan par dashboard-layout.js en mode édition :
 * - disposition (ordre et largeur des widgets), à chaque déplacement. Corps JSON : {order: [...], widths: {clé:
 *   half|full}} pour les widgets affichés, ou {reset: true} pour revenir à la disposition par défaut ;
 * - widgets affichés : {add: clé} ajoute un widget en fin de tableau de bord, {remove: clé} le retire. Les autres
 *   widgets gardent leur place et leur largeur.
 */
final class DashboardLayoutController
{
    public function __construct(
        private readonly UserDashboardPrefsRepositoryInterface $dashboardPrefs,
        private readonly AuditLogger $auditLogger,
    ) {}

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

    /** POST /admin/dashboard/widgets */
    public function widgetsManager(Request $request): Response
    {
        return $this->toggle($request, 'admin', DashboardWidget::MANAGER, HomeController::ADMIN_WIDGETS);
    }

    /** POST /employee/dashboard/widgets */
    public function widgetsEmployee(Request $request): Response
    {
        return $this->toggle($request, 'employee', DashboardWidget::EMPLOYEE, EmployeeController::EMPLOYEE_WIDGETS);
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

    /** @param list<string> $coreKeys */
    private function toggle(Request $request, string $dashboardType, string $audience, array $coreKeys): Response
    {
        $userId = (int) ($request->getAttribute('auth_user')['id'] ?? 0);
        $body   = $request->json() ?? [];
        $add    = is_string($body['add'] ?? null);
        $key    = $add ? $body['add'] : ($body['remove'] ?? null);

        $offered = DashboardWidgets::current()->keys($audience, $coreKeys);
        if (!is_string($key) || !in_array($key, $offered, true)) {
            throw new ValidationException(['widget' => __('dashboard_widget_unknown')]);
        }

        $previous = $this->dashboardPrefs->getEnabledWidgets($userId, $dashboardType);
        $widgets  = DashboardWidgets::toggle($offered, $previous, $key, $add);
        $this->dashboardPrefs->saveWidgets($userId, $widgets, $dashboardType);

        // Un widget ajouté arrive en fin de tableau de bord, sans toucher à la place des autres.
        if ($add) {
            $this->dashboardPrefs->saveLayout(
                $userId,
                $dashboardType,
                DashboardLayout::moveToEnd($offered, $this->dashboardPrefs->getLayout($userId, $dashboardType), $key),
            );
        }

        $this->auditLogger->logUpdate($request, 'dashboard_prefs.updated', 'user_dashboard_prefs', $userId, $previous, $widgets, ['dashboard' => $dashboardType]);

        return Response::json(['widgets' => $widgets]);
    }
}
