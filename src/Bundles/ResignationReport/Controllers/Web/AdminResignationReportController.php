<?php

declare(strict_types=1);

namespace kintai\Bundles\ResignationReport\Controllers\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\Controller\Web\Staff\HasStaffReportCrud;
use kintai\UI\ViewRenderer;

final class AdminResignationReportController
{
    use HasAdminAccess;
    use HasStaffReportCrud;

    private const REPORT_TYPE = [
        'entity'    => 'resignation_report',
        'slug'      => 'resignation',
        'view'      => 'resignation-report::reports-resignation',
        'not_found' => 'Rapport de démission introuvable.',
        'fields'    => [
            'employee_number'    => 'str',
            'employee_name'      => 'str',
            'resignation_date'   => 'str',
            'reason'             => 'str',
            'resignation_notice' => 'str',
            'notes'              => 'str',
            'person_in_charge'   => 'str',
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly ResignationReportRepositoryInterface $resignationReports,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionService $permissions,
    ) {}

    public function allResignationReports(Request $request): Response
    {
        [$allStores, $queryStoreIds, $filterStoreId] = $this->storesAndFilter($request);

        $filterYear = $request->query('year', '');
        $filterMonth = $request->query('month', '');
        $filterPerson = trim($request->query('person', ''));

        $filters = [];
        if ($filterStoreId > 0) {
            $filters['store_id'] = $filterStoreId;
        }
        if ($filterYear !== '') {
            $filters['year'] = $filterYear;
        }
        if ($filterMonth !== '') {
            $filters['month'] = $filterMonth;
        }
        if ($filterPerson !== '') {
            $filters['person_in_charge'] = $filterPerson;
        }

        $reports = $this->resignationReports->findAll($queryStoreIds, $filters);

        return Response::html($this->view->render('resignation-report::reports-resignation', [
            'title'          => __('resignation_reports'),
            'stores'         => $allStores,
            'filter_store_id' => $filterStoreId,
            'filter_year'    => $filterYear,
            'filter_month'   => $filterMonth,
            'filter_person'  => $filterPerson,
            'reports'        => $reports,
        ], 'layout.app'));
    }

    public function resignationReports(Request $request): Response
    {
        return $this->listReports($request, __('resignation_reports'));
    }

    // -------------------------------------------------------------------------
    // Export de la liste "tous les magasins" (item 5)
    // -------------------------------------------------------------------------

    private function exportFilters(Request $request): array
    {
        $filters = [];
        $filterYear = $request->query('year', '');
        if ($filterYear !== '') {
            $filters['year'] = $filterYear;
        }
        $filterMonth = $request->query('month', '');
        if ($filterMonth !== '') {
            $filters['month'] = $filterMonth;
        }
        $filterPerson = trim($request->query('person', ''));
        if ($filterPerson !== '') {
            $filters['person_in_charge'] = $filterPerson;
        }
        return $filters;
    }

    public function exportResignationReportsJson(Request $request): Response
    {
        [, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->resignationReports->findAll($queryStoreIds, $this->exportFilters($request));

        $this->auditLogger->log($request, 'export.resignation_reports_json', 'resignation_report', 0, [
            'count' => count($reports),
        ]);

        return Response::jsonDownload(['data' => $reports], 'resignation_reports_' . date('Ymd') . '.json');
    }

    /**
     * Aperçu HTML du PDF (route .../export/pdf) : pas de téléchargement
     * automatique — voir HasStaffReportCrud::reportPdf() pour le même
     * principe appliqué aux rapports individuels.
     */
    public function exportResignationReportsPdf(Request $request): Response
    {
        [$allStores, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->resignationReports->findAll($queryStoreIds, $this->exportFilters($request));
        $storeNames = array_column($allStores, 'name', 'id');

        $html = $this->view->render('resignation-report::reports-resignation-export-pdf', [
            'reports'      => $reports,
            'store_names'  => $storeNames,
            'generated_at' => date('Y-m-d H:i'),
            'downloadUrl'  => $this->base() . '/admin/reports/resignation/export/pdf/download' . $this->exportQueryString($request),
        ]);

        return Response::html($html);
    }

    public function exportResignationReportsPdfDownload(Request $request): Response
    {
        [$allStores, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->resignationReports->findAll($queryStoreIds, $this->exportFilters($request));
        $storeNames = array_column($allStores, 'name', 'id');

        $html = $this->view->render('resignation-report::reports-resignation-export-pdf', [
            'reports'      => $reports,
            'store_names'  => $storeNames,
            'generated_at' => date('Y-m-d H:i'),
        ]);

        $this->auditLogger->log($request, 'export.resignation_reports_pdf', 'resignation_report', 0, [
            'count' => count($reports),
        ]);

        return $this->renderPdf($html, 'resignation_reports_' . date('Ymd') . '.pdf');
    }

    private function exportQueryString(Request $request): string
    {
        $query = array_filter([
            'store_id' => (int) $request->query('store_id', 0) ?: null,
            'year'     => $request->query('year', '') ?: null,
            'month'    => $request->query('month', '') ?: null,
            'person'   => $request->query('person', '') ?: null,
        ]);
        return $query ? '?' . http_build_query($query) : '';
    }

    public function createResignationReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $authUser = $request->getAttribute('auth_user');

        // Pré-remplir avec les données d'un employé si user_id est fourni
        $preset = [];
        $userId = (int) ($request->query('user_id') ?? 0);
        $users = $this->storeMembersForReportForm($storeId, $userId);
        if ($userId > 0) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $nameParts = array_filter([$user['last_name'] ?? '', $user['first_name'] ?? '']);
                $preset = [
                    'user_id'          => $userId,
                    'employee_number'  => $user['employee_code'] ?? '',
                    'employee_name'    => implode(' ', $nameParts),
                    'resignation_date' => date('Y-m-d'),
                    'person_in_charge' => $authUser['display_name'] ?? '',
                ];
            }
        } else {
            $preset['resignation_date'] = date('Y-m-d');
        }

        $managers = $this->getManagersForReportForm($storeId, $userId);

        return Response::html($this->view->render('resignation-report::reports-resignation-form', [
            'title'   => __('new_resignation_report') . ' — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'users'   => $users,
            'mode'    => 'create',
            'report'  => $preset,
            'managers' => $managers,
        ], 'layout.app'));
    }

    public function storeResignationReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $userId = (int) $request->post('user_id', 0);

        $data = array_merge([
            'store_id' => $storeId,
            'user_id'  => $userId > 0 ? $userId : null,
        ], $this->postData($request, self::REPORT_TYPE['fields']), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->resignationReports->save($data);

        // Désactiver l'employé
        if ($userId > 0) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $user['is_active'] = 0;
                $this->users->save($user);
            }
        }

        $this->auditLogger->log($request, 'resignation_report.created', 'resignation_report', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'employee_name' => $data['employee_name'],
        ]);

        return $this->redirectToList($storeId, 'resignation', 'created');
    }

    public function showResignationReport(Request $request): Response
    {
        return $this->showReport($request);
    }

    public function editResignationReport(Request $request): Response
    {
        return $this->editReport($request);
    }

    public function updateResignationReport(Request $request): Response
    {
        return $this->updateReport($request);
    }

    /**
     * Supprimer un rapport de démission réactive automatiquement l'employé
     * concerné (storeResignationReport() le désactive à la création) — sinon
     * il resterait désactivé alors que le rapport qui l'a désactivé n'existe
     * plus.
     */
    public function deleteResignationReport(Request $request): Response
    {
        [$report] = $this->findReportOrFail($request);
        $userId = (int) ($report['user_id'] ?? 0);
        if ($userId > 0) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $user['is_active'] = 1;
                $this->users->save($user);
                $this->auditLogger->log($request, 'resignation_report.reactivated', 'resignation_report', (int) $report['id'], [
                    'store_id' => (int) $report['store_id'],
                    'user_id'  => $userId,
                    'reason'   => 'report_deleted',
                ]);
            }
        }

        return $this->deleteReport($request);
    }

    /**
     * Alternative à deleteResignationReport() : au lieu de réactiver l'employé,
     * supprime définitivement son compte en plus du rapport. Proposée via la
     * popup de confirmation du bouton "Supprimer" côté vue.
     */
    public function deleteResignationReportPermanently(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);
        $userId = (int) ($report['user_id'] ?? 0);

        $this->resignationReports->delete($reportId);
        $this->auditLogger->log($request, 'resignation_report.deleted', 'resignation_report', $reportId, [
            'store_id' => $storeId,
        ]);

        if ($userId > 0) {
            $this->users->delete($userId);
            $this->auditLogger->log($request, 'user.deleted', 'user', $userId, [
                'reason'   => 'resignation_report_deleted',
                'store_id' => $storeId,
            ]);
        }

        return $this->redirectToList($storeId, 'resignation', 'user_deleted');
    }

    public function resignationReportPdf(Request $request): Response
    {
        return $this->reportPdf($request);
    }

    public function resignationReportPdfDownload(Request $request): Response
    {
        return $this->reportPdfDownload($request);
    }

    public function reactivateUser(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        [$report, , $reportId] = $this->findReportOrFail($request);

        $userId = (int) ($report['user_id'] ?? 0);
        if ($userId > 0) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $user['is_active'] = 1;
                $this->users->save($user);
            }
        }

        $this->auditLogger->log($request, 'resignation_report.reactivated', 'resignation_report', $reportId, [
            'store_id' => $storeId,
            'user_id'  => $userId,
        ]);

        return $this->redirectToList($storeId, 'resignation', 'reactivated');
    }

    protected function reportRepo(): object
    {
        return $this->resignationReports;
    }

    protected function reportConfig(): array
    {
        return self::REPORT_TYPE;
    }

    protected function reportShowTitle(array $report): string
    {
        return __('resignation_report') . ' — ' . ($report['employee_name'] ?? '');
    }

    protected function reportEditTitle(): string
    {
        return __('edit_resignation_report');
    }

    protected function reportEditExtras(int $storeId, array $report): array
    {
        $userId = (int) ($report['user_id'] ?? 0);
        return [
            'users'    => $this->storeMembersForReportForm($storeId, $userId),
            'managers' => $this->getManagersForReportForm($storeId, $userId),
        ];
    }
}
