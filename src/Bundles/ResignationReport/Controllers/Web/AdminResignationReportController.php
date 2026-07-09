<?php

declare(strict_types=1);

namespace kintai\Bundles\ResignationReport\Controllers\Web;

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
    ) {}

    public function allResignationReports(Request $request): Response
    {
        [$allStores, $queryStoreIds, $filterStoreId] = $this->storesAndFilter($request);

        $reports = $this->resignationReports->findAll($queryStoreIds);

        return Response::html($this->view->render('resignation-report::reports-resignation', [
            'title'   => __('resignation_reports'),
            'stores'  => $allStores,
            'filter_store_id' => $filterStoreId,
            'reports' => $reports,
        ], 'layout.app'));
    }

    public function resignationReports(Request $request): Response
    {
        return $this->listReports($request, __('resignation_reports'));
    }

    public function createResignationReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $users = $this->users->findAll();
        $authUser = $request->getAttribute('auth_user');

        // Pré-remplir avec les données d'un employé si user_id est fourni
        $preset = [];
        $userId = (int) ($request->query('user_id') ?? 0);
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

    public function deleteResignationReport(Request $request): Response
    {
        return $this->deleteReport($request);
    }

    public function resignationReportPdf(Request $request): Response
    {
        return $this->reportPdf($request);
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
        return [
            'users'    => $this->users->findAll(),
            'managers' => $this->getManagersForReportForm($storeId, (int) ($report['user_id'] ?? 0)),
        ];
    }
}
