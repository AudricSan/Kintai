<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * CRUD générique pour les rapports RH par store (embauche, démission, salaire) :
 * ces trois types partageaient auparavant un seul AdminReportController avec un
 * dispatch `repo(string $type)`. Depuis leur extraction en bundles séparés,
 * chaque contrôleur ne gère plus qu'un seul type et fournit sa propre
 * config/repository via les méthodes abstraites ci-dessous ; ce trait porte
 * la logique jusqu'ici commune aux trois.
 *
 * Nécessite sur la classe utilisatrice : $this->view (ViewRenderer),
 * $this->stores (StoreRepositoryInterface), $this->auditLogger (AuditLogger),
 * et le trait HasAdminAccess (base(), assertStoreAccess()).
 */
trait HasStaffReportCrud
{
    /** Le repository du rapport (findById/findByStore/findAll/save/delete). */
    abstract protected function reportRepo(): object;

    /**
     * @return array{entity: string, slug: string, view: string, not_found: string, fields: array<string, string>}
     */
    abstract protected function reportConfig(): array;

    abstract protected function reportShowTitle(array $report): string;

    abstract protected function reportEditTitle(): string;

    /** Données de formulaire propres au type (ex : liste des employés, des responsables). */
    abstract protected function reportEditExtras(int $storeId, array $report): array;

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

        $report = $this->reportRepo()->findById($reportId);
        if ($report === null || (int) $report['store_id'] !== $storeId) {
            throw new NotFoundException($this->reportConfig()['not_found']);
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

    private function listReports(Request $request, string $titlePrefix): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        return Response::html($this->view->render($this->reportConfig()['view'], [
            'title'   => $titlePrefix . ' — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'reports' => $this->reportRepo()->findByStore($storeId),
        ], 'layout.app'));
    }

    private function showReport(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);
        $entity = $this->reportConfig()['entity'];

        $this->auditLogger->log($request, $entity . '.viewed', $entity, $reportId, [
            'store_id' => $storeId,
        ], $storeId);

        return Response::html($this->view->render($this->reportConfig()['view'] . '-show', [
            'title'  => $this->reportShowTitle($report),
            'store'  => $this->stores->findById($storeId),
            'report' => $report,
        ], 'layout.app'));
    }

    private function editReport(Request $request): Response
    {
        [$report, $storeId] = $this->findReportOrFail($request);

        return Response::html($this->view->render($this->reportConfig()['view'] . '-form', array_merge([
            'title'  => $this->reportEditTitle(),
            'store'  => $this->stores->findById($storeId),
            'mode'   => 'edit',
            'report' => $report,
        ], $this->reportEditExtras($storeId, $report)), 'layout.app'));
    }

    private function updateReport(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);
        $cfg = $this->reportConfig();

        $changes = $this->postData($request, $cfg['fields']);
        $userId = (int) $request->post('user_id', 0);
        $changes = ['user_id' => $userId > 0 ? $userId : null] + $changes;
        $data = array_merge($report, $changes);

        $this->reportRepo()->save($data);
        $this->auditLogger->logUpdate($request, $cfg['entity'] . '.updated', $cfg['entity'], $reportId, $report, $data, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, $cfg['slug'], 'updated');
    }

    private function deleteReport(Request $request): Response
    {
        [, $storeId, $reportId] = $this->findReportOrFail($request);
        $entity = $this->reportConfig()['entity'];

        $this->reportRepo()->delete($reportId);
        $this->auditLogger->log($request, $entity . '.deleted', $entity, $reportId, [
            'store_id' => $storeId,
        ]);

        return $this->redirectToList($storeId, $this->reportConfig()['slug'], 'deleted');
    }

    private function reportPdf(Request $request): Response
    {
        [$report, $storeId, $reportId] = $this->findReportOrFail($request);
        $cfg = $this->reportConfig();

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
                throw new \kintai\Core\Exceptions\ForbiddenException('Accès refusé à ce magasin.');
            }
            $queryStoreIds = [$filterStoreId];
        }

        return [$allStores, $queryStoreIds, $filterStoreId];
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

    /**
     * Retourne la liste des responsables (admin/manager) pour le store courant
     * ou pour tous les stores d'un employé donné. Utilisée par démission et salaire.
     * Nécessite $this->storeUsers (StoreUserRepositoryInterface) et $this->users
     * (UserRepositoryInterface) sur la classe utilisatrice.
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
