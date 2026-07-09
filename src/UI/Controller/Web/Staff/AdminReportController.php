<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;
use kintai\UI\Controller\Web\HasAdminAccess;

/**
 * Rapports d'embauche — reste dans le Core pour l'instant. Démission et
 * salaire, qui partageaient auparavant ce même contrôleur (CRUD générique via
 * un dispatch `repo(string $type)`), ont été extraits en bundles séparés
 * (voir src/Bundles/ResignationReport/ et src/Bundles/SalaryReport/, qui
 * réutilisent la même logique via le trait HasStaffReportCrud). Embauche
 * étant désormais seul, ce contrôleur n'a plus besoin de dispatch par type.
 */
final class AdminReportController
{
    use HasAdminAccess;

    private const NOT_FOUND = 'Rapport d\'embauche introuvable.';

    private const FIELDS = [
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
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly HiringReportRepositoryInterface $hiringReports,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function hiringReports(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        return Response::html($this->view->render('staff.reports-hiring', [
            'title'   => '採用報告書 — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'reports' => $this->hiringReports->findByStore($storeId),
        ], 'layout.app'));
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
        ], $this->postData($request), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->hiringReports->save($data);

        $this->auditLogger->log($request, 'hiring_report.created', 'hiring_report', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'employee_name' => $data['employee_name'],
        ]);

        return $this->redirectToList($storeId, 'created');
    }

    public function showHiringReport(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);

        $this->auditLogger->log($request, 'hiring_report.viewed', 'hiring_report', $reportId, [
            'store_id' => $storeId,
        ], $storeId);

        return Response::html($this->view->render('staff.reports-hiring-show', [
            'title'  => '採用報告書 — ' . ($report['employee_name'] ?? ''),
            'store'  => $this->stores->findById($storeId),
            'report' => $report,
        ], 'layout.app'));
    }

    public function editHiringReport(Request $request): Response
    {
        [$report, $storeId] = $this->findReportOrFail($request);

        return Response::html($this->view->render('staff.reports-hiring-form', [
            'title'  => '編集 — 採用報告書',
            'store'  => $this->stores->findById($storeId),
            'mode'   => 'edit',
            'report' => $report,
            'users'  => $this->users->findAll(),
        ], 'layout.app'));
    }

    public function updateHiringReport(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);

        $userId = (int) $request->post('user_id', 0);
        $changes = ['user_id' => $userId > 0 ? $userId : null] + $this->postData($request);
        $data = array_merge($report, $changes);

        $this->hiringReports->save($data);
        $this->auditLogger->logUpdate($request, 'hiring_report.updated', 'hiring_report', $reportId, $report, $data, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, 'updated');
    }

    public function deleteHiringReport(Request $request): Response
    {
        [, $storeId, $reportId] = $this->findReportOrFail($request);

        $this->hiringReports->delete($reportId);
        $this->auditLogger->log($request, 'hiring_report.deleted', 'hiring_report', $reportId, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, 'deleted');
    }

    public function hiringReportPdf(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);

        $html = $this->view->render('staff.reports-hiring-pdf', [
            'report' => $report,
            'store'  => $this->stores->findById($storeId),
        ]);

        return $this->renderPdf($html, 'hiring_report_' . $reportId . '.pdf');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Résout le rapport depuis les paramètres de route ({id} store, {rid} rapport),
     * vérifie l'accès au store et l'appartenance du rapport.
     *
     * @return array{0: array, 1: int, 2: int} [report, storeId, reportId]
     */
    private function findReportOrFail(Request $request): array
    {
        $storeId  = (int) $request->param('id');
        $reportId = (int) $request->param('rid');
        $this->assertStoreAccess($request, $storeId);

        $report = $this->hiringReports->findById($reportId);
        if ($report === null || (int) $report['store_id'] !== $storeId) {
            throw new NotFoundException(self::NOT_FOUND);
        }

        return [$report, $storeId, $reportId];
    }

    /**
     * Construit les données du rapport depuis le POST selon le mapping des champs.
     */
    private function postData(Request $request): array
    {
        $data = [];
        foreach (self::FIELDS as $field => $cast) {
            $data[$field] = match ($cast) {
                'str'   => $request->post($field, '') ?: null,
                'float' => (float) $request->post($field, 0),
                'int'   => (int) $request->post($field, 0),
                'null'  => null,
            };
        }
        return $data;
    }

    private function redirectToList(int $storeId, string $flag): Response
    {
        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/reports/hiring?success=' . $flag);
    }

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
}
