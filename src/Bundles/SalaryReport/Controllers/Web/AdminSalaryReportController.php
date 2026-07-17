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
use kintai\Core\Services\StoreStatsServiceInterface;
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
            'employee_name'         => 'str',
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
            'bank_transfer_salary'  => 'float',
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
        private readonly StoreStatsServiceInterface $storeStatsService,
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

        $membersByStore = [];
        foreach ($allStores as $s) {
            $sid = (int) $s['id'];
            $members = $this->storeMembersForReportForm($sid);
            if ($members !== []) {
                $membersByStore[$sid] = $members;
            }
        }

        return Response::html($this->view->render('salary-report::reports-salary', [
            'title'       => __('sr_title'),
            'stores'      => $allStores,
            'store_members_by_store' => $membersByStore,
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

    public function exportSalaryReportsJson(Request $request): Response
    {
        [, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->salaryReports->findAll($queryStoreIds, $this->exportFilters($request));

        $this->auditLogger->log($request, 'export.salary_reports_json', 'salary_report', 0, [
            'count' => count($reports),
        ]);

        return Response::jsonDownload(['data' => $reports], 'salary_reports_' . date('Ymd') . '.json');
    }

    public function exportSalaryReportsPdf(Request $request): Response
    {
        [$allStores, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->salaryReports->findAll($queryStoreIds, $this->exportFilters($request));
        $storeNames = array_column($allStores, 'name', 'id');

        $html = $this->view->render('salary-report::reports-salary-export-pdf', [
            'reports'      => $reports,
            'store_names'  => $storeNames,
            'generated_at' => date('Y-m-d H:i'),
        ]);

        $this->auditLogger->log($request, 'export.salary_reports_pdf', 'salary_report', 0, [
            'count' => count($reports),
        ]);

        return $this->renderPdf($html, 'salary_reports_' . date('Ymd') . '.pdf');
    }

    public function createSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $authUser = $request->getAttribute('auth_user');
        $targetMonth = date('Y-m');

        $userId = (int) ($request->query('user_id') ?? 0);
        $employee = $userId > 0 ? $this->users->findById($userId) : null;
        if ($employee === null) {
            $userId = 0;
        }

        $preset = $this->calculateSalaryPreset($store, $targetMonth, $authUser, $userId ?: null);
        if ($employee !== null) {
            $preset['user_id'] = $userId;
            $preset['employee_name'] = trim(($employee['last_name'] ?? '') . ' ' . ($employee['first_name'] ?? ''))
                ?: ($employee['display_name'] ?? '');
        }

        $managers = $this->getManagersForReportForm($storeId, $userId);
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
        $userId = (int) $request->post('user_id', 0);

        $existing = $this->salaryReports->findByStoreAndMonth($storeId, $targetMonth, $userId ?: null);
        if ($existing !== null) {
            return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/reports/salary/' . $existing['id'] . '/edit?error=already_exists');
        }

        $data = array_merge([
            'store_id'     => $storeId,
            'user_id'      => $userId ?: null,
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

    /** Employés du store, pour le menu "créer un rapport pour cet employé" de la liste. */
    protected function reportListExtras(int $storeId): array
    {
        return [
            'store_members' => $this->storeMembersForReportForm($storeId),
        ];
    }

    /**
     * Pour un rapport lié à un employé, ajoute le détail jour par jour des shifts
     * et des retenues — exactement les données que produisait l'ancienne fiche de
     * paie autonome (StoreStatsService::buildPayslipData), maintenant affichées
     * directement dans le rapport de salaire (show + PDF) au lieu d'une page séparée.
     */
    protected function reportShowExtras(int $storeId, array $report): array
    {
        $userId = (int) ($report['user_id'] ?? 0);
        $targetMonth = (string) ($report['target_month'] ?? '');
        if ($userId <= 0 || $targetMonth === '') {
            return [];
        }

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        return $this->storeStatsService->buildPayslipData($storeId, $userId, $from, $to);
    }

    /**
     * Calcule les valeurs pré-remplies pour un rapport de salaire à partir
     * des rapports journaliers et des shifts existants.
     */
    /**
     * @param int|null $userId Rapport pour un seul employé (item 7) au lieu du magasin entier :
     *                         les heures/salaire ne portent que sur ses propres shifts, et le
     *                         total des ventes du magasin (sans rapport avec un seul employé)
     *                         n'est pas pré-rempli.
     */
    private function calculateSalaryPreset(array $store, string $targetMonth, array $authUser, ?int $userId = null): array
    {
        $storeId = (int) $store['id'];
        $preset = [
            'target_month'      => $targetMonth,
            'person_in_charge'  => $authUser['display_name'] ?? '',
        ];

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        $totalPayment = 0;
        if ($userId === null) {
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

            foreach ($dailyReports as $r) {
                $totalPayment += (float) ($r['sales_total'] ?? 0);
            }
        }

        // Heures et salaires depuis les shifts (du seul employé si $userId est fourni)
        $allShifts = $this->shifts->findByStore($storeId);
        $monthShifts = array_filter($allShifts, fn($s) =>
            ($s['shift_date'] ?? '') >= $from && ($s['shift_date'] ?? '') <= $to
            && ($userId === null || (int) ($s['user_id'] ?? 0) === $userId)
        );

        $totalMinutes = 0;
        $totalShiftSalary = 0;
        $employeeMinutes = [];
        $employeeCost = [];
        foreach ($monthShifts as $s) {
            $minutes = (int) ($s['duration_minutes'] ?? 0);
            $cost = (float) ($s['estimated_salary'] ?? 0);
            $totalMinutes += $minutes;
            $totalShiftSalary += $cost;
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid > 0) {
                $employeeMinutes[$uid] = ($employeeMinutes[$uid] ?? 0) + $minutes;
                $employeeCost[$uid] = ($employeeCost[$uid] ?? 0) + $cost;
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

        $preset = array_merge($preset, $this->calculateDeductionsPreset(
            $storeId,
            $totalShiftSalary,
            $userId,
            $employeeCost,
        ));

        return $preset;
    }

    /**
     * Pré-remplit les champs de retenues (assurance santé, pension, chômage,
     * impôt à la source, taxe de résidence) à partir des paramètres de
     * retenues du store — même logique que StoreStatsService::buildPayslipData(),
     * appliquée soit à un seul employé, soit sommée sur tous les employés ayant
     * eu des shifts ce mois-ci (rapport magasin entier).
     *
     * @param array<int, float> $employeeCost Coût brut par employé (user_id => somme estimated_salary), pour le mode magasin entier.
     */
    private function calculateDeductionsPreset(int $storeId, float $totalShiftSalary, ?int $userId, array $employeeCost): array
    {
        $deductionSettings = $this->stores->getDeductionSettings($storeId);
        if (empty($deductionSettings['enabled'])) {
            return [];
        }

        $costsByEmployee = $userId !== null ? [$userId => $totalShiftSalary] : $employeeCost;

        $health = 0.0;
        $pension = 0.0;
        $employment = 0.0;
        $incomeTax = 0.0;
        $residentTax = 0.0;

        foreach ($costsByEmployee as $uid => $cost) {
            if ($cost <= 0) {
                continue;
            }
            $membership = $this->storeUsers->findMembership($storeId, $uid);
            $membershipId = (int) ($membership['id'] ?? 0);
            if ($membershipId <= 0 || !$this->storeUsers->getSubjectToDeductions($membershipId)) {
                continue;
            }
            $health      += round($cost * (float) ($deductionSettings['health_insurance_rate'] ?? 0) / 100, 2);
            $pension     += round($cost * (float) ($deductionSettings['pension_rate'] ?? 0) / 100, 2);
            $employment  += round($cost * (float) ($deductionSettings['employment_insurance_rate'] ?? 0) / 100, 2);
            $incomeTax   += round($cost * (float) ($deductionSettings['income_tax_rate'] ?? 0) / 100, 2);
            $residentTax += (float) ($deductionSettings['resident_tax_monthly'] ?? 0);
        }

        $otherDeductions = round($health + $pension + $employment, 2);
        $withholdingTax = round($incomeTax, 2);
        $residenceTax = round($residentTax, 2);
        $totalDeductions = round($otherDeductions + $withholdingTax + $residenceTax, 2);

        if ($totalDeductions <= 0) {
            return [];
        }

        $netPayment = round($totalShiftSalary - $totalDeductions, 2);

        return [
            'income_tax_base'      => round($totalShiftSalary, 2),
            'other_deductions'     => $otherDeductions,
            'withholding_tax'      => $withholdingTax,
            'residence_tax'        => $residenceTax,
            'total_deductions'     => $totalDeductions,
            'net_payment'          => $netPayment,
            // Par défaut le net est versé par virement ; "salaire en main propre"
            // reste à 0 et n'est renseigné que si une partie est réellement payée en espèces.
            'bank_transfer_salary' => $netPayment,
        ];
    }
}
