<?php

declare(strict_types=1);

namespace kintai\Bundles\SalaryReport\Controllers\Web;

use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportDataNormalizer;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\Controller\Web\Staff\HasStaffReportCrud;
use kintai\UI\ViewRenderer;

final class AdminSalaryReportController
{
    use HasAdminAccess;
    use HasStaffReportCrud;

    private const REPORT_TYPE = [
        'entity'    => 'salary_report',
        'slug'      => 'salary',
        'view'      => 'salary-report::reports-salary',
        'not_found' => 'Rapport de salaire introuvable.',
        'fields'    => [
            'store_name'            => 'str',
            'person_in_charge'      => 'str',
            'total_payment'         => 'float',
            'total_deductions'      => 'float',
            'income_tax_base'       => 'float',
            'withholding_tax'       => 'float',
            'residence_tax'         => 'float',
            'other_deductions'      => 'float',
            'net_payment'           => 'float',
            'active_employees'      => 'int',
            'hand_delivered_salary' => 'float',
            'staff_man_hours'       => 'float',
            'staff_total_payment'   => 'float',
            'staff_avg_hourly_wage' => 'float',
            'employee_work_hours'   => 'str',
            'new_hires'             => 'int',
            'resigned_staff'        => 'int',
            'hire_registrations'    => 'str',
            'remarks'               => 'str',
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly SalaryReportRepositoryInterface $salaryReports,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly DailyReportRepositoryInterface $dailyReports,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function allSalaryReports(Request $request): Response
    {
        [$allStores, $queryStoreIds, $filterStoreId] = $this->storesAndFilter($request);

        $filters = [];
        if ($filterStoreId > 0) {
            $filters['store_id'] = $filterStoreId;
        }
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

        $reports = $this->salaryReports->findAll($queryStoreIds, $filters);

        return Response::html($this->view->render('salary-report::reports-salary', [
            'title'       => __('sr_title'),
            'stores'      => $allStores,
            'filter_store_id' => $filterStoreId,
            'filter_year' => $filterYear,
            'filter_month' => $filterMonth,
            'filter_person' => $filterPerson,
            'reports'     => $reports,
        ], 'layout.app'));
    }

    public function salaryReports(Request $request): Response
    {
        return $this->listReports($request, __('sr_title'));
    }

    public function createSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $authUser = $request->getAttribute('auth_user');
        $targetMonth = date('Y-m');
        $preset = $this->calculateSalaryPreset($store, $targetMonth, $authUser);

        $userId = (int) ($request->query('user_id') ?? 0);
        if ($userId > 0) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $preset['employee_name'] = $user['display_name'] ?? '';
            }
        }

        $managers = $this->getManagersForReportForm($storeId);
        $authName = trim(($authUser['last_name'] ?? '') . ' ' . ($authUser['first_name'] ?? '')) ?: ($authUser['display_name'] ?? '');
        $isManager = !empty(array_filter($managers, fn($m) => (int) $m['id'] === (int) ($authUser['id'] ?? 0)));
        if ($isManager && empty($preset['person_in_charge'])) {
            $preset['person_in_charge'] = $authName;
        }

        return Response::html($this->view->render('salary-report::reports-salary-form', [
            'title'   => __('sr_new') . ' — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'mode'    => 'create',
            'report'  => $preset,
            'managers' => $managers,
        ], 'layout.app'));
    }

    public function storeSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $targetMonth = $request->post('target_month', '');

        $existing = $this->salaryReports->findByStoreAndMonth($storeId, $targetMonth);
        if ($existing !== null) {
            return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/reports/salary/' . $existing['id'] . '/edit?error=already_exists');
        }

        $data = array_merge([
            'store_id'     => $storeId,
            'target_month' => $targetMonth,
        ], $this->postData($request, self::REPORT_TYPE['fields']), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->salaryReports->save($data);

        $this->auditLogger->log($request, 'salary_report.created', 'salary_report', (int) ($saved['id'] ?? 0), [
            'store_id'     => $storeId,
            'target_month' => $targetMonth,
        ]);

        return $this->redirectToList($storeId, 'salary', 'created');
    }

    public function showSalaryReport(Request $request): Response
    {
        return $this->showReport($request);
    }

    public function editSalaryReport(Request $request): Response
    {
        return $this->editReport($request);
    }

    public function updateSalaryReport(Request $request): Response
    {
        return $this->updateReport($request);
    }

    public function deleteSalaryReport(Request $request): Response
    {
        return $this->deleteReport($request);
    }

    public function salaryReportPdf(Request $request): Response
    {
        return $this->reportPdf($request);
    }

    protected function reportRepo(): object
    {
        return $this->salaryReports;
    }

    protected function reportConfig(): array
    {
        return self::REPORT_TYPE;
    }

    protected function reportShowTitle(array $report): string
    {
        return __('sr_title') . ' — ' . ($report['target_month'] ?? '');
    }

    protected function reportEditTitle(): string
    {
        return __('sr_edit');
    }

    protected function reportEditExtras(int $storeId, array $report): array
    {
        return [
            'managers' => $this->getManagersForReportForm($storeId, (int) ($report['user_id'] ?? 0)),
        ];
    }

    /**
     * Calcule les valeurs pré-remplies pour un rapport de salaire à partir
     * des rapports journaliers et des shifts existants.
     */
    private function calculateSalaryPreset(array $store, string $targetMonth, array $authUser): array
    {
        $storeId = (int) $store['id'];
        $preset = [
            'target_month'      => $targetMonth,
            'person_in_charge'  => $authUser['display_name'] ?? '',
        ];

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        // Total des ventes depuis les rapports journaliers validés/soumis. En mode
        // de saisie "cumulatif" (daily_report_settings.cumulative_mode), sales_total
        // contient déjà le cumul depuis le début de la période : le ramener à des
        // deltas journaliers avant de sommer, sans quoi chaque jour compterait
        // plusieurs fois (voir DailyReportDataNormalizer).
        $dailyReports = array_values(array_filter(
            $this->dailyReports->findByStoreAndDateRange($storeId, $from, $to),
            fn($r) => in_array($r['status'] ?? '', ['validated', 'submitted'], true)
        ));
        usort($dailyReports, fn($a, $b) => strcmp($a['report_date'], $b['report_date']));
        $dailyReports = DailyReportDataNormalizer::toDailyDeltas(
            $dailyReports,
            DailyReportDataNormalizer::cumulativeModeOf($store),
        );

        $totalPayment = 0;
        foreach ($dailyReports as $r) {
            $totalPayment += (float) ($r['sales_total'] ?? 0);
        }

        // Heures et salaires depuis les shifts
        $allShifts = $this->shifts->findByStore($storeId);
        $monthShifts = array_filter($allShifts, fn($s) =>
            ($s['shift_date'] ?? '') >= $from && ($s['shift_date'] ?? '') <= $to
        );

        $totalMinutes = 0;
        $totalShiftSalary = 0;
        $employeeMinutes = [];
        foreach ($monthShifts as $s) {
            $minutes = (int) ($s['duration_minutes'] ?? 0);
            $totalMinutes += $minutes;
            $totalShiftSalary += (float) ($s['estimated_salary'] ?? 0);
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid > 0) {
                $employeeMinutes[$uid] = ($employeeMinutes[$uid] ?? 0) + $minutes;
            }
        }

        $staffManHours = $totalMinutes > 0 ? round($totalMinutes / 60, 2) : 0;
        $activeEmployees = count($employeeMinutes);
        $avgHourlyWage = $staffManHours > 0 ? round($totalShiftSalary / $staffManHours, 2) : 0;

        // Texte récapitulatif des heures par employé
        $lines = [];
        ksort($employeeMinutes);
        foreach ($employeeMinutes as $uid => $minutes) {
            $user = $this->users->findById($uid);
            if ($user !== null) {
                $name = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '')) ?: ($user['display_name'] ?? '#' . $uid);
                $lines[] = $name . ': ' . round($minutes / 60, 1) . 'h';
            }
        }

        $preset['total_payment'] = round($totalPayment, 2);
        $preset['staff_man_hours'] = $staffManHours;
        $preset['staff_total_payment'] = round($totalShiftSalary, 2);
        $preset['staff_avg_hourly_wage'] = $avgHourlyWage;
        $preset['active_employees'] = $activeEmployees;
        $preset['employee_work_hours'] = implode("\n", $lines);

        return $preset;
    }
}
