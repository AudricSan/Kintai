<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;
use kintai\UI\Controller\Web\HasAdminAccess;

/**
 * Rapports d'embauche et de démission — restent dans le Core pour l'instant.
 * Salaire, qui partageait auparavant ce même contrôleur (CRUD générique via
 * un dispatch `repo(string $type)`), a été extrait en bundle séparé (voir
 * src/Bundles/SalaryReport/, qui réutilise la même logique via le trait
 * HasStaffReportCrud). Ce contrôleur garde volontairement son dispatch
 * interne à deux types tant que la démission n'a pas, elle aussi, son propre
 * bundle — bascule prévue vers HasStaffReportCrud à ce moment-là, hiring
 * seul sera alors géré de la même façon.
 */
final class AdminReportController
{
    use HasAdminAccess;

    /**
     * Configuration commune des deux types de rapports restants (embauche,
     * démission) : entité d'audit, segment d'URL, préfixe de vue, message
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
        'resignation' => [
            'entity'    => 'resignation_report',
            'slug'      => 'resignation',
            'view'      => 'staff.reports-resignation',
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
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly HiringReportRepositoryInterface $hiringReports,
        private readonly ResignationReportRepositoryInterface $resignationReports,
        private readonly StoreUserRepositoryInterface $storeUsers,
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
    // 退職報告書 (Resignation Report)
    // =========================================================================

    public function allResignationReports(Request $request): Response
    {
        [$allStores, $queryStoreIds, $filterStoreId] = $this->storesAndFilter($request);

        $reports = $this->resignationReports->findAll($queryStoreIds);

        return Response::html($this->view->render('staff.reports-resignation', [
            'title'   => __('resignation_reports'),
            'stores'  => $allStores,
            'filter_store_id' => $filterStoreId,
            'reports' => $reports,
        ], 'layout.app'));
    }

    public function resignationReports(Request $request): Response
    {
        return $this->listReports('resignation', $request, __('resignation_reports'));
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

        return Response::html($this->view->render('staff.reports-resignation-form', [
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
        ], $this->postData($request, self::REPORT_TYPES['resignation']['fields']), [
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
        return $this->showReport('resignation', $request);
    }

    public function editResignationReport(Request $request): Response
    {
        return $this->editReport('resignation', $request);
    }

    public function updateResignationReport(Request $request): Response
    {
        return $this->updateReport('resignation', $request);
    }

    public function deleteResignationReport(Request $request): Response
    {
        return $this->deleteReport('resignation', $request);
    }

    public function resignationReportPdf(Request $request): Response
    {
        return $this->reportPdf('resignation', $request);
    }

    public function reactivateUser(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        [$report, , $reportId] = $this->findReportOrFail('resignation', $request);

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

    // =========================================================================
    // CRUD générique par type de rapport
    // =========================================================================

    private function repo(string $type): HiringReportRepositoryInterface|ResignationReportRepositoryInterface
    {
        return match ($type) {
            'hiring'      => $this->hiringReports,
            'resignation' => $this->resignationReports,
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
            'hiring'      => '採用報告書 — ' . ($report['employee_name'] ?? ''),
            'resignation' => __('resignation_report') . ' — ' . ($report['employee_name'] ?? ''),
        };
    }

    private function editReport(string $type, Request $request): Response
    {
        [$report, $storeId] = $this->findReportOrFail($type, $request);

        $title = match ($type) {
            'hiring'      => '編集 — 採用報告書',
            'resignation' => __('edit_resignation_report'),
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
     * (hiring/resignation) et des responsables (resignation).
     */
    private function editExtras(string $type, int $storeId, array $report): array
    {
        $managers = fn(): array => $this->getManagersForReportForm($storeId, (int) ($report['user_id'] ?? 0));

        return match ($type) {
            'hiring'      => ['users' => $this->users->findAll()],
            'resignation' => ['users' => $this->users->findAll(), 'managers' => $managers()],
        };
    }

    private function updateReport(string $type, Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($type, $request);
        $cfg = self::REPORT_TYPES[$type];

        $changes = $this->postData($request, $cfg['fields']);
        $userId = (int) $request->post('user_id', 0);
        $changes = ['user_id' => $userId > 0 ? $userId : null] + $changes;
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
