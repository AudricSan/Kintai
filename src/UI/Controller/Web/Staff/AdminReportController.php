<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;
use kintai\UI\Controller\Web\HasAdminAccess;

/**
 * Rapports d'embauche et de salaire — restent dans le Core pour l'instant.
 * Démission, qui partageait auparavant ce même contrôleur (CRUD générique via
 * un dispatch `repo(string $type)`), a été extraite en bundle séparé (voir
 * src/Bundles/ResignationReport/, qui réutilise la même logique via le
 * nouveau trait HasStaffReportCrud). Ce contrôleur garde volontairement son
 * dispatch interne à deux types tant que le salaire n'a pas, lui aussi, son
 * propre bundle — bascule prévue vers HasStaffReportCrud à ce moment-là,
 * comme pour hiring seul seront alors gérés de la même façon.
 */
final class AdminReportController
{
    use HasAdminAccess;

    /**
     * Configuration commune des deux types de rapports restants (embauche,
     * salaire) : entité d'audit, segment d'URL, préfixe de vue, message
     * d'introuvable et mapping des champs POST avec leur cast
     * ('str' → chaîne ou null, 'float'/'int' → numérique, 'null' → toujours null).
     */
    private const REPORT_TYPES = [
        'hiring' => [
            'entity'    => 'hiring_report',
            'slug'      => 'hiring',
            'view'      => 'staff.reports-hiring',
            'not_found' => 'Rapport d\'embauche introuvable.',
            'fields'    => [
                'employee_number'     => 'str',
                'employee_name'       => 'str',
                'furigana'            => 'null',
                'furigana_last_name'  => 'str',
                'furigana_first_name' => 'str',
                'gender'              => 'str',
                'tax_classification'  => 'str',
                'birth_date'          => 'str',
                'hire_date'           => 'str',
                'education'           => 'str',
                'postal_code'         => 'str',
                'address'             => 'str',
                'phone'               => 'str',
                'mobile_phone'        => 'str',
                'email'               => 'str',
                'guarantor_name'      => 'str',
                'guarantor_phone'     => 'str',
                'store_name'          => 'str',
                'hired_by'            => 'str',
                'notes'               => 'str',
            ],
        ],
        'salary' => [
            'entity'    => 'salary_report',
            'slug'      => 'salary',
            'view'      => 'staff.reports-salary',
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
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly HiringReportRepositoryInterface $hiringReports,
        private readonly SalaryReportRepositoryInterface $salaryReports,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly DailyReportRepositoryInterface $dailyReports,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly AuditLogger $auditLogger,
    ) {}

    // =========================================================================
    // 採用報告書 (Hiring Report)
    // =========================================================================

    public function hiringReports(Request $request): Response
    {
        return $this->listReports('hiring', $request, '採用報告書');
    }

    public function createHiringReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        return Response::html($this->view->render('staff.reports-hiring-form', [
            'title' => '新規採用報告書 — ' . ($store['name'] ?? ''),
            'store' => $store,
            'users' => $this->users->findAll(),
            'mode'  => 'create',
            'report' => [],
        ], 'layout.app'));
    }

    public function storeHiringReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $userId = (int) $request->post('user_id', 0);

        $data = array_merge([
            'store_id' => $storeId,
            'user_id'  => $userId > 0 ? $userId : null,
        ], $this->postData($request, self::REPORT_TYPES['hiring']['fields']), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->hiringReports->save($data);

        $this->auditLogger->log($request, 'hiring_report.created', 'hiring_report', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'employee_name' => $data['employee_name'],
        ]);

        return $this->redirectToList($storeId, 'hiring', 'created');
    }

    public function showHiringReport(Request $request): Response
    {
        return $this->showReport('hiring', $request);
    }

    public function editHiringReport(Request $request): Response
    {
        return $this->editReport('hiring', $request);
    }

    public function updateHiringReport(Request $request): Response
    {
        return $this->updateReport('hiring', $request);
    }

    public function deleteHiringReport(Request $request): Response
    {
        return $this->deleteReport('hiring', $request);
    }

    public function hiringReportPdf(Request $request): Response
    {
        return $this->reportPdf('hiring', $request);
    }

    // =========================================================================
    // 給与報告書 (Salary Report)
    // =========================================================================

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

        return Response::html($this->view->render('staff.reports-salary', [
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
        return $this->listReports('salary', $request, __('sr_title'));
    }

    public function createSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $authUser = $request->getAttribute('auth_user');
        $targetMonth = date('Y-m');
        $preset = $this->calculateSalaryPreset($storeId, $targetMonth, $authUser);

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

        return Response::html($this->view->render('staff.reports-salary-form', [
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
        ], $this->postData($request, self::REPORT_TYPES['salary']['fields']), [
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
        return $this->showReport('salary', $request);
    }

    public function editSalaryReport(Request $request): Response
    {
        return $this->editReport('salary', $request);
    }

    public function updateSalaryReport(Request $request): Response
    {
        return $this->updateReport('salary', $request);
    }

    public function deleteSalaryReport(Request $request): Response
    {
        return $this->deleteReport('salary', $request);
    }

    public function salaryReportPdf(Request $request): Response
    {
        return $this->reportPdf('salary', $request);
    }

    // =========================================================================
    // CRUD générique par type de rapport
    // =========================================================================

    private function repo(string $type): HiringReportRepositoryInterface|SalaryReportRepositoryInterface
    {
        return match ($type) {
            'hiring' => $this->hiringReports,
            'salary' => $this->salaryReports,
        };
    }

    /**
     * Résout le rapport depuis les paramètres de route ({id} store, {rid} rapport),
     * vérifie l'accès au store et l'appartenance du rapport.
     *
     * @return array{0: array, 1: int, 2: int} [report, storeId, reportId]
     */
    private function findReportOrFail(string $type, Request $request): array
    {
        $storeId  = (int) $request->param('id');
        $reportId = (int) $request->param('rid');
        $this->assertStoreAccess($request, $storeId);

        $report = $this->repo($type)->findById($reportId);
        if ($report === null || (int) $report['store_id'] !== $storeId) {
            throw new NotFoundException(self::REPORT_TYPES[$type]['not_found']);
        }

        return [$report, $storeId, $reportId];
    }

    /**
     * Construit les données d'un rapport depuis le POST selon le mapping de champs du type.
     */
    private function postData(Request $request, array $fields): array
    {
        $data = [];
        foreach ($fields as $field => $cast) {
            $data[$field] = match ($cast) {
                'str'   => $request->post($field, '') ?: null,
                'float' => (float) $request->post($field, 0),
                'int'   => (int) $request->post($field, 0),
                'null'  => null,
            };
        }
        return $data;
    }

    private function listReports(string $type, Request $request, string $titlePrefix): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        return Response::html($this->view->render(self::REPORT_TYPES[$type]['view'], [
            'title'   => $titlePrefix . ' — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'reports' => $this->repo($type)->findByStore($storeId),
        ], 'layout.app'));
    }

    private function showReport(string $type, Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($type, $request);
        $entity = self::REPORT_TYPES[$type]['entity'];

        $this->auditLogger->log($request, $entity . '.viewed', $entity, $reportId, [
            'store_id' => $storeId,
        ], $storeId);

        return Response::html($this->view->render(self::REPORT_TYPES[$type]['view'] . '-show', [
            'title'  => $this->showTitle($type, $report),
            'store'  => $this->stores->findById($storeId),
            'report' => $report,
        ], 'layout.app'));
    }

    private function showTitle(string $type, array $report): string
    {
        return match ($type) {
            'hiring' => '採用報告書 — ' . ($report['employee_name'] ?? ''),
            'salary' => __('sr_title') . ' — ' . ($report['target_month'] ?? ''),
        };
    }

    private function editReport(string $type, Request $request): Response
    {
        [$report, $storeId] = $this->findReportOrFail($type, $request);

        $title = match ($type) {
            'hiring' => '編集 — 採用報告書',
            'salary' => __('sr_edit'),
        };

        return Response::html($this->view->render(self::REPORT_TYPES[$type]['view'] . '-form', array_merge([
            'title'  => $title,
            'store'  => $this->stores->findById($storeId),
            'mode'   => 'edit',
            'report' => $report,
        ], $this->editExtras($type, $storeId, $report)), 'layout.app'));
    }

    /**
     * Données de formulaire propres à chaque type : liste des employés
     * (hiring) et des responsables (salary).
     */
    private function editExtras(string $type, int $storeId, array $report): array
    {
        return match ($type) {
            'hiring' => ['users' => $this->users->findAll()],
            'salary' => ['managers' => $this->getManagersForReportForm($storeId, (int) ($report['user_id'] ?? 0))],
        };
    }

    private function updateReport(string $type, Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($type, $request);
        $cfg = self::REPORT_TYPES[$type];

        $changes = $this->postData($request, $cfg['fields']);
        if ($type !== 'salary') {
            $userId = (int) $request->post('user_id', 0);
            $changes = ['user_id' => $userId > 0 ? $userId : null] + $changes;
        }
        $data = array_merge($report, $changes);

        $this->repo($type)->save($data);
        $this->auditLogger->logUpdate($request, $cfg['entity'] . '.updated', $cfg['entity'], $reportId, $report, $data, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, $cfg['slug'], 'updated');
    }

    private function deleteReport(string $type, Request $request): Response
    {
        [, $storeId, $reportId] = $this->findReportOrFail($type, $request);
        $entity = self::REPORT_TYPES[$type]['entity'];

        $this->repo($type)->delete($reportId);
        $this->auditLogger->log($request, $entity . '.deleted', $entity, $reportId, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, self::REPORT_TYPES[$type]['slug'], 'deleted');
    }

    private function reportPdf(string $type, Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($type, $request);
        $cfg = self::REPORT_TYPES[$type];

        $html = $this->view->render($cfg['view'] . '-pdf', [
            'report' => $report,
            'store'  => $this->stores->findById($storeId),
        ]);

        return $this->renderPdf($html, $cfg['entity'] . '_' . $reportId . '.pdf');
    }

    private function redirectToList(int $storeId, string $slug, string $flag): Response
    {
        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/reports/' . $slug . '?success=' . $flag);
    }

    /**
     * Liste des stores visibles et filtre store_id pour les vues « tous les rapports ».
     *
     * @return array{0: array, 1: array, 2: int} [allStores, queryStoreIds, filterStoreId]
     */
    private function storesAndFilter(Request $request): array
    {
        $authUser = $request->getAttribute('auth_user');
        $managedIds = $request->getAttribute('managed_store_ids');

        if (!empty($authUser['is_admin'])) {
            $allStores = $this->stores->findAll();
            $storeIds = [];
        } else {
            $allStores = array_values(array_filter(
                array_map(fn($id) => $this->stores->findById($id), $managedIds ?? []),
            ));
            $storeIds = $managedIds ?? [];
        }

        $filterStoreId = (int) $request->query('store_id', '0');
        $queryStoreIds = $storeIds;
        if ($filterStoreId > 0) {
            if (empty($authUser['is_admin']) && !in_array($filterStoreId, $storeIds, true)) {
                throw new ForbiddenException('Accès refusé à ce magasin.');
            }
            $queryStoreIds = [$filterStoreId];
        }

        return [$allStores, $queryStoreIds, $filterStoreId];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function findStoreOrFail(int $storeId): array
    {
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }
        return $store;
    }

    private function renderPdf(string $html, string $filename): Response
    {
        $tmpDir = storage_path('app/mpdf');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $config = [
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 16,
            'margin_bottom' => 16,
            'tempDir'       => $tmpDir,
        ];

        // Polices CJK (japonais, chinois) pour compatibilité multilingue
        $fontConfig = $this->findCjkFont();
        if ($fontConfig !== null) {
            $config['fontDir']  = $fontConfig['dir'];
            $config['fontdata'] = $fontConfig['data'];
            $config['default_font'] = $fontConfig['default'];
        }

        $mpdf = new \Mpdf\Mpdf($config);
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);

        return Response::pdf($mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), $filename);
    }

    private function findCjkFont(): ?array
    {
        $customDir  = storage_path('fonts');
        $customFile = $customDir . '/NotoSansJP-Regular.ttf';
        if (file_exists($customFile)) {
            return [
                'dir'     => [$customDir],
                'data'    => ['notosansjp' => ['R' => 'NotoSansJP-Regular.ttf']],
                'default' => 'notosansjp',
            ];
        }

        $sysRoot  = rtrim((string) (getenv('SystemRoot') ?: 'C:/Windows'), '/\\');
        $winFonts = $sysRoot . '/Fonts';

        $candidates = [
            'YuGothR.ttc'  => ['fontName' => 'yugothic', 'ttcId' => 1],
            'meiryo.ttc'   => ['fontName' => 'meiryo',   'ttcId' => 1],
            'msgothic.ttc' => ['fontName' => 'msgothic', 'ttcId' => 1],
        ];

        foreach ($candidates as $file => $meta) {
            if (file_exists($winFonts . '/' . $file)) {
                return [
                    'dir'     => [$winFonts],
                    'data'    => [
                        $meta['fontName'] => [
                            'R'         => $file,
                            'TTCfontID' => ['R' => $meta['ttcId']],
                        ],
                    ],
                    'default' => $meta['fontName'],
                ];
            }
        }

        return null;
    }

    /**
     * Calcule les valeurs pré-remplies pour un rapport de salaire à partir
     * des rapports journaliers et des shifts existants.
     */
    private function calculateSalaryPreset(int $storeId, string $targetMonth, array $authUser): array
    {
        $preset = [
            'target_month'      => $targetMonth,
            'person_in_charge'  => $authUser['display_name'] ?? '',
        ];

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        // Total des ventes depuis les rapports journaliers validés/soumis
        $dailyReports = $this->dailyReports->findByStoreAndDateRange($storeId, $from, $to);
        $totalPayment = 0;
        foreach ($dailyReports as $r) {
            if (in_array($r['status'] ?? '', ['validated', 'submitted'], true)) {
                $totalPayment += (float) ($r['sales_total'] ?? 0);
            }
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

    /**
     * Retourne la liste des responsables (admin/manager) pour le store courant
     * ou pour tous les stores d'un employé donné.
     */
    private function getManagersForReportForm(int $storeId, int $userId = 0): array
    {
        $storeIds = [$storeId];
        if ($userId > 0) {
            $memberships = $this->storeUsers->findByUser($userId);
            $userStoreIds = array_map(fn($m) => (int) $m['store_id'], $memberships);
            if (!empty($userStoreIds)) {
                $storeIds = $userStoreIds;
            }
        }

        $seenIds = [];
        $managers = [];
        foreach ($storeIds as $sid) {
            $members = $this->storeUsers->findByStore($sid);
            foreach ($members as $m) {
                if (in_array($m['role'] ?? '', ['admin', 'manager'], true)) {
                    $uid = (int) $m['user_id'];
                    if (!in_array($uid, $seenIds, true)) {
                        $seenIds[] = $uid;
                        $user = $this->users->findById($uid);
                        if ($user !== null) {
                            $managers[] = $user;
                        }
                    }
                }
            }
        }
        return $managers;
    }
}
