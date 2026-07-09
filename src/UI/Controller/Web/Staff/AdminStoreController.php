<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\UI\Controller\Web\HasAdminAccess;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\FeatureManager;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\StoreServiceInterface;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\ViewRenderer;

final class AdminStoreController
{
    use HasAdminAccess;

    private const ROLES = ['admin' => 'Administrateur', 'manager' => 'Manager', 'staff' => 'Employé'];

    /** Association slug de feature par-store → bundle dont il dépend (absent = fonctionnalité Core, toujours proposée). */
    private const FEATURE_BUNDLE_MAP = [
        'messages'      => 'messaging',
        'daily_reports' => 'daily-report',
        'photos'        => 'store-photos',
        'timeoff'       => 'timeoff',
        'swaps'         => 'shift-swap',
        'open_shifts'   => 'shift-claim',
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly AuditLogger $auditLogger,
        private readonly StoreServiceInterface $storeService,
        private readonly StoreStatsServiceInterface $storeStatsService,
        private readonly FeatureManager $features,
    ) {}

    /**
     * Pour chaque slug de feature par-store, indique s'il est proposable :
     * true pour les fonctionnalités Core, et pour les fonctionnalités de bundle
     * dont le bundle est activé au niveau de l'instance (/admin/bundles).
     */
    private function availableFeatureSlugs(): array
    {
        $map = [];
        foreach (self::FEATURE_BUNDLE_MAP as $slug => $bundleKey) {
            $map[$slug] = $this->features->isEnabled($bundleKey);
        }
        return $map;
    }

    public function stores(Request $request): Response
    {
        $sort   = $request->query('sort') ?? 'name_asc';
        $stores = $this->storeService->getStoresForAdmin($this->managedIds($request), $sort);

        return Response::html($this->view->render('staff.stores', [
            'title'  => 'Stores',
            'stores' => $stores,
            'sort'   => $sort,
        ], 'layout.app'));
    }

    public function createStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut créer un store.');
        }

        return Response::html($this->view->render('staff.stores-form', [
            'title'                 => 'Nouveau store',
            'mode'                  => 'create',
            'store'                 => [],
            'availableFeatureSlugs' => $this->availableFeatureSlugs(),
        ], 'layout.app'));
    }

    public function storeStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut créer un store.');
        }
        $savedStore = $this->storeService->createStore([
            'code'            => strtoupper(trim($request->post('code', ''))),
            'name'            => $request->post('name', ''),
            'type'            => ($tmp = $request->post('type', '')) !== '' ? $tmp : 'retail',
            'timezone'        => ($tmp = $request->post('timezone', '')) !== '' ? $tmp : 'UTC',
            'locale'          => ($tmp = $request->post('locale', '')) !== '' ? $tmp : 'en',
            'currency'        => strtoupper(trim(($tmp = $request->post('currency', '')) !== '' ? $tmp : 'EUR')),
            'phone'           => $request->post('phone', '') ?: null,
            'email'           => $request->post('email', '') ?: null,
            'address_street'  => $request->post('address_street', '') ?: null,
            'address_city'    => $request->post('address_city', '') ?: null,
            'address_postal'  => $request->post('address_postal', '') ?: null,
            'address_country' => $request->post('address_country', '') ?: null,
        ]);

        $this->auditLogger->log($request, 'store.created', 'store', (int) ($savedStore['id'] ?? 0), [
            'name' => $request->post('name', ''),
        ]);
        return Response::redirect($this->base() . '/admin/stores?success=created');
    }

    public function editStore(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->assertStoreAccess($request, (int) $store['id']);

        $storeId = (int) $store['id'];
        $members = array_map(function ($m) {
            $name = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? ''));
            return array_merge($m['membership'], [
                'user_name'  => $name ?: ($m['email'] ?? '—'),
                'user_email' => $m['email'] ?? '',
            ]);
        }, $this->storeService->getStoreMembers($storeId));

        $memberUserIds = array_map(fn($m) => (int) $m['user_id'], $members);
        $allUsers = $this->users->findAll();
        $available = array_values(array_filter(
            $allUsers,
            fn($u) => !in_array((int) $u['id'], $memberUserIds, true) && !empty($u['is_active'])
        ));

        return Response::html($this->view->render('staff.stores-form', [
            'title'             => 'Modifier ' . htmlspecialchars($store['name'] ?? ''),
            'mode'              => 'edit',
            'store'             => $store,
            'members'           => $members,
            'available'         => $available,
            'roles'             => self::ROLES,
            'deductionSettings' => $this->storeService->getDeductionSettings($storeId),
            // Aucune ligne en base = jamais configuré → toutes les fonctionnalités actives par défaut (null)
            'enabledFeatures'   => ($_ef = $this->stores->getFeatures($storeId)) !== [] ? $_ef : null,
            'importSettings'    => $this->stores->getImportSettings($storeId),
            'availableFeatureSlugs' => $this->availableFeatureSlugs(),
        ], 'layout.app'));
    }

    public function updateStore(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $storeId = (int) $store['id'];
        $this->assertStoreAccess($request, $storeId);

        $allFeatureSlugs = ['shifts', 'timeclock', 'timeoff', 'swaps', 'open_shifts', 'messages', 'daily_reports', 'photos'];
        $enabledFeatures = [];
        foreach ($allFeatureSlugs as $slug) {
            if ($request->post('feature_' . $slug)) {
                $enabledFeatures[] = $slug;
            }
        }

        $data = [
            'code'                  => strtoupper(trim($request->post('code', $store['code'] ?? ''))),
            'name'                  => $request->post('name', $store['name'] ?? ''),
            'type'                  => ($tmp = $request->post('type', '')) !== '' ? $tmp : ($store['type'] ?? 'retail'),
            'timezone'              => ($tmp = $request->post('timezone', '')) !== '' ? $tmp : ($store['timezone'] ?? 'UTC'),
            'locale'                => ($tmp = $request->post('locale', '')) !== '' ? $tmp : ($store['locale'] ?? 'en'),
            'currency'              => strtoupper(trim(($tmp = $request->post('currency', '')) !== '' ? $tmp : ($store['currency'] ?? 'EUR'))),
            'phone'                 => $request->post('phone', '') ?: null,
            'email'                 => $request->post('email', '') ?: null,
            'address_street'        => $request->post('address_street', '') ?: null,
            'address_city'          => $request->post('address_city', '') ?: null,
            'address_postal'        => $request->post('address_postal', '') ?: null,
            'address_country'       => $request->post('address_country', '') ?: null,
            'week_start_day'        => max(1, min(7, (int) $request->post('week_start_day', 1))),
            'min_shift_minutes'     => (int) $request->post('min_shift_minutes', 120),
            'max_shift_minutes'     => (int) $request->post('max_shift_minutes', 480),
            'min_staff_per_day'     => (int) $request->post('min_staff_per_day', 0),
            'low_staff_hour_start'  => max(-1, min(23, (int) $request->post('low_staff_hour_start', -1))),
            'low_staff_hour_end'    => max(-1, min(23, (int) $request->post('low_staff_hour_end',   -1))),
            'is_active'             => $request->post('is_active') === '1' ? 1 : 0,
            '_excel_settings'       => [
                'col_start'               => (int)   $request->post('excel_col_start',             4),
                'col_end'                 => (int)   $request->post('excel_col_end',               52),
                'base_hour'               => (int)   $request->post('excel_base_hour',             6),
                'minutes_per_col'         => (int)   $request->post('excel_minutes_per_col',       30),
                'block_size'              => (int)   $request->post('excel_block_size',            9),
                'shift_rows'              => (int)   $request->post('excel_shift_rows',            7),
                'sheet_filter_pattern'    => (string) $request->post('excel_sheet_filter_pattern', '^\d{4}$'),
                'auto_pause_after_minutes' => (int)  $request->post('auto_pause_after_minutes',    0),
                'auto_pause_minutes'      => (int)   $request->post('auto_pause_minutes',          30),
            ],
            '_deduction_settings'   => [
                'enabled'                   => !empty($request->post('ded_enabled')),
                'health_insurance_rate'     => (float) ($request->post('ded_health_rate') ?? 0),
                'pension_rate'              => (float) ($request->post('ded_pension_rate') ?? 0),
                'employment_insurance_rate' => (float) ($request->post('ded_employment_rate') ?? 0),
                'income_tax_rate'           => (float) ($request->post('ded_income_tax_rate') ?? 0),
                'resident_tax_monthly'      => (float) ($request->post('ded_resident_tax') ?? 0),
            ],
            '_features'             => $enabledFeatures,
        ];

        $newStoreData = $this->storeService->updateStore($storeId, $data);

        $this->auditLogger->logUpdate($request, 'store.updated', 'store', $storeId, $store, $newStoreData, [
            'name' => $request->post('name', $store['name'] ?? ''),
        ], $storeId);
        return Response::redirect($this->base() . '/admin/stores?success=updated');
    }

    public function deleteStore(Request $request): Response
    {
        if ($this->managedIds($request) !== null) {
            throw new ForbiddenException('Seul un owner peut supprimer un store.');
        }

        $id    = (int) $request->param('id');
        $store = $this->stores->findById($id);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }

        $this->storeService->deleteStore($id);
        $this->auditLogger->log($request, 'store.deleted', 'store', $id, [
            'name' => $store['name'] ?? null,
        ]);
        return Response::redirect($this->base() . '/admin/stores?success=deleted');
    }

    public function addMember(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store   = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $userId = (int) $request->post('user_id', 0);
        $role   = $request->post('role', 'staff');

        if ($userId > 0) {
            $this->storeService->addMember($storeId, $userId, $role);
            $this->auditLogger->log($request, 'store.member_added', 'store_user', null, [
                'store_id' => $storeId,
                'user_id'  => $userId,
                'role'     => $role,
            ], $storeId);
        }

        $redirectTo = $request->post('redirect_to', '');
        $dest = $redirectTo !== '' ? $redirectTo : $this->base() . '/admin/stores/' . $storeId . '/edit?success=member_added';
        return Response::redirect($dest);
    }

    public function updateMemberRole(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $role = $request->post('role', 'staff');
        $oldRole = $membership['role'];

        $this->storeService->updateMemberRole($mid, $storeId, $role);
        $this->auditLogger->logUpdate($request, 'store.member_role_updated', 'store_user', $mid, ['role' => $oldRole], ['role' => $role], [
            'store_id' => $storeId,
            'user_id' => $membership['user_id'] ?? null,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/edit?success=role_updated');
    }

    public function removeMember(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->assertStoreAccess($request, $storeId);

        $this->storeService->removeMember($mid, $storeId);
        $this->auditLogger->log($request, 'store.member_removed', 'store_user', $mid, [
            'store_id' => $storeId,
            'user_id' => $membership['user_id'] ?? null,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/edit?success=member_removed');
    }

    public function editMemberDeductions(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Membre introuvable.');
        }

        $user = $this->users->findById((int) $membership['user_id']);
        if ($user === null) throw new NotFoundException('Utilisateur introuvable.');

        return Response::html($this->view->render('staff.member-deductions', [
            'title'              => 'Cotisations — ' . ($user['display_name'] ?? $user['email'] ?? ''),
            'store'              => $store,
            'membership'         => $membership,
            'user'               => $user,
            'deductionSettings'  => $this->storeService->getDeductionSettings($storeId),
            'deductionOverrides' => $this->storeService->getDeductionOverrides($mid),
        ], 'layout.app'));
    }

    public function saveMemberDeductions(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $mid     = (int) $request->param('mid');
        $this->assertStoreAccess($request, $storeId);

        $membership = $this->storeUsers->findById($mid);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Membre introuvable.');
        }

        $overrides    = $this->storeService->getDeductionOverrides($mid);
        $oldSubjectTo = $overrides['subject_to_deductions'] ?? false;
        $newSubjectTo = !empty($request->post('subject_to_deductions'));
        $this->storeService->saveMemberDeductions($mid, $storeId, $newSubjectTo);
        $this->auditLogger->logUpdate($request, 'store.member_deductions_updated', 'store_user', $mid, ['subject_to_deductions' => $oldSubjectTo], ['subject_to_deductions' => $newSubjectTo], [
            'store_id' => $storeId,
            'user_id'  => $membership['user_id'] ?? null,
        ], $storeId);

        $redirectTo = $request->post('_redirect_to', '');
        $fallback   = $this->base() . '/admin/stores/' . $storeId . '/edit?success=deductions_saved';
        $target     = ($redirectTo !== '' && str_starts_with($redirectTo, $this->base() . '/admin/'))
            ? $redirectTo
            : $fallback;

        return Response::redirect($target);
    }

    public function storeStats(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $period      = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $minShiftMin = (int) ($store['min_shift_minutes'] ?? 0);
        $maxShiftMin = (int) ($store['max_shift_minutes'] ?? 0);

        $stats = $this->storeStatsService->storeStats($storeId, $period, $minShiftMin, $maxShiftMin);

        return Response::html($this->view->render('analytics.store-stats', array_merge($stats, [
            'title'        => 'Statistiques — ' . ($store['name'] ?? ''),
            'store'        => $store,
            'currency'     => $store['currency'] ?? 'EUR',
            'minShiftMin'  => $minShiftMin,
            'maxShiftMin'  => $maxShiftMin,
        ]), 'layout.app'));
    }

    public function storeStatsExport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $period  = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $data    = $this->storeStatsService->storeStatsExportData($storeId, $period);
        $since   = $data['since'];
        $today   = $data['today'];
        $n       = $data['n'];
        $name    = $store['name'] ?? 'store';
        $currency = $store['currency'] ?? 'EUR';

        $buf = fopen('php://memory', 'r+');
        fprintf($buf, "\xEF\xBB\xBF");
        $put = fn(array $row) => fputcsv($buf, $row, ';');

        $put(['# Kintai — Export statistiques']);
        $put(['Magasin', $name]);
        $put(['Période', "{$since} → {$today} ({$period} jours)"]);
        $put([]);

        $put(['=== Résumé ===']);
        $put(['Shifts analysés',       $n]);
        $put(['Heures nettes',         number_format($data['totalNetHours'],   1, '.', '')]);
        $put(['Heures brutes',         number_format($data['totalGrossHours'], 1, '.', '')]);
        $put(['Durée moy. shift (h)',  number_format($data['avgDuration'],     2, '.', '')]);
        $put(['Jours actifs',          $data['activeDays']]);
        $put(['Taux modification (%)', $data['modRate']]);
        $put(['Score équité',          $data['equityScore']]);
        $put(['Score efficacité',      $data['efficiencyScore']]);
        $put(['Score stabilité',       $data['stabilityScore']]);
        $put([]);

        $put(['=== Évolution hebdomadaire ===']);
        $put(['Semaine', 'Heures']);
        foreach ($data['hoursByWeek'] as $week => $h) {
            $put([$week, number_format($h, 1, '.', '')]);
        }
        $put([]);

        $put(['=== Évolution mensuelle ===']);
        $put(['Mois', 'Heures nettes', "Coût ({$currency})"]);
        $months = array_unique(array_merge(array_keys($data['hoursByMonth']), array_keys($data['costByMonth'])));
        sort($months);
        foreach ($months as $month) {
            $put([$month,
                number_format($data['hoursByMonth'][$month] ?? 0, 1, '.', ''),
                number_format($data['costByMonth'][$month]  ?? 0, 2, '.', ''),
            ]);
        }
        $put([]);

        $put(['=== Heures par employé ===']);
        $put(['Employé', 'Heures', "Coût ({$currency})"]);
        foreach ($data['hoursByUser'] as $uid => $h) {
            $u   = $data['usersMap'][$uid] ?? [];
            $emp = trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['email'] ?? '#' . $uid);
            $put([$emp, number_format($h, 1, '.', ''), number_format($data['costByUser'][$uid] ?? 0, 2, '.', '')]);
        }
        $put([]);

        $put(['=== Heures par type de shift ===']);
        $put(['Type', 'Heures']);
        foreach ($data['hoursByType'] as $label => $h) {
            $put([$label, number_format($h, 1, '.', '')]);
        }

        rewind($buf);
        $csv = stream_get_contents($buf);
        fclose($buf);

        $this->auditLogger->log($request, 'export.store_stats', 'store', $storeId, [
            'period'  => $period,
            'format'  => 'csv',
        ], $storeId);

        $filename = 'stats-' . preg_replace('/[^a-z0-9]+/i', '-', $name) . '-' . $since . '.csv';
        return Response::csv($csv, $filename);
    }

    public function storeProfitability(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $today  = date('Y-m-d');
        $preset = (int) ($request->query('preset') ?? 90);
        $preset = in_array($preset, [7, 30, 90, 180, 365], true) ? $preset : 90;
        $from   = $request->query('from') ?: date('Y-m-d', strtotime("-{$preset} days"));
        $to     = $request->query('to')   ?: $today;
        if ($from > $to) [$from, $to] = [$to, $from];

        $data     = $this->storeStatsService->storeProfitability($storeId, $from, $to);
        $currency = currency_symbol($store['currency'] ?? 'EUR');

        return Response::html($this->view->render('analytics.store-profitability', array_merge($data, [
            'title'    => 'Rentabilité — ' . ($store['name'] ?? ''),
            'store'    => $store,
            'currency' => $currency,
            'from'     => $from,
            'to'       => $to,
            'preset'   => $preset,
        ]), 'layout.app'));
    }

    public function employeePayslip(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        [$from, $to] = self::parseDateRange($request);
        $data        = $this->storeStatsService->buildPayslipData($storeId, $userId, $from, $to);

        $this->auditLogger->log($request, 'payslip.viewed', 'user', $userId, [
            'store_id' => $storeId, 'from' => $from, 'to' => $to,
        ], $storeId);

        return Response::html($this->view->render('staff.employee-payslip', array_merge($data, [
            'store'      => $store,
            'user'       => $user,
            'membership' => $membership,
            'currency'   => $store['currency'] ?? 'EUR',
            'autoprint'  => $request->query('autoprint') === '1',
        ])));
    }

    public function employeePayslipPdf(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        [$from, $to] = self::parseDateRange($request);
        $data        = $this->storeStatsService->buildPayslipData($storeId, $userId, $from, $to);

        $html = $this->view->render('staff.employee-payslip-pdf', array_merge($data, [
            'store'      => $store,
            'user'       => $user,
            'membership' => $membership,
            'currency'   => $store['currency'] ?? 'EUR',
        ]));

        $tmpDir = storage_path('app/mpdf');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 16,
            'margin_bottom' => 16,
            'tempDir'       => $tmpDir,
        ]);
        $mpdf->SetTitle('Fiche de paie');
        $mpdf->WriteHTML($html);

        $slug     = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', trim(($user['last_name'] ?? '') . '_' . ($user['first_name'] ?? '')))) ?: 'employe';
        $filename = 'payslip_' . $slug . '_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.pdf';

        $this->auditLogger->log($request, 'export.payslip_pdf', 'user', $userId, [
            'store_id' => $storeId,
            'from'     => $from,
            'to'       => $to,
            'format'   => 'pdf',
        ], $storeId);

        $pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        return Response::pdf($pdfBytes, $filename);
    }

    public function employeeStats(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $userId  = (int) $request->param('uid');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) throw new NotFoundException('Magasin introuvable.');

        $user = $this->users->findById($userId);
        if ($user === null) throw new NotFoundException('Employé introuvable.');

        $membership = $this->storeUsers->findMembership($storeId, $userId);
        if ($membership === null) throw new ForbiddenException('Cet employé n\'est pas membre de ce magasin.');

        $period = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $data   = $this->storeStatsService->employeeStats($storeId, $userId, $period);

        $this->auditLogger->log($request, 'employee_stats.viewed', 'user', $userId, [
            'store_id' => $storeId, 'period' => $period,
        ], $storeId);

        $empName    = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '')) ?: ($user['email'] ?? '');
        $payslipUrl = $this->base() . '/admin/stores/' . $storeId . '/employee-report/' . $userId . '/payslip?period=' . $period;
        $pdfUrl     = $this->base() . '/admin/stores/' . $storeId . '/employee-report/' . $userId . '/payslip/pdf?period=' . $period;

        return Response::html($this->view->render('staff.employee-stats', array_merge($data, [
            'title'      => 'Statistiques — ' . $empName,
            'store'      => $store,
            'user'       => $user,
            'membership' => $membership,
            'currency'   => $store['currency'] ?? 'EUR',
            'payslipUrl' => $payslipUrl,
            'pdfUrl'     => $pdfUrl,
        ]), 'layout.app'));
    }

    public function employeeReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->assertStoreAccess($request, $storeId);

        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }

        $period = max(7, min(365, (int) ($request->query('period') ?? 30)));
        $data   = $this->storeStatsService->employeeReport($storeId, $period);

        return Response::html($this->view->render('staff.employee-report', array_merge($data, [
            'title'    => 'Rapport employés — ' . ($store['name'] ?? ''),
            'store'    => $store,
            'currency' => $store['currency'] ?? 'EUR',
        ]), 'layout.app'));
    }

    public static function parseDateRange(Request $request): array
    {
        $from = (string) ($request->query('from') ?? '');
        $to   = (string) ($request->query('to')   ?? '');

        $valid = fn(string $d): bool => (bool) \DateTime::createFromFormat('Y-m-d', $d);

        if (!$valid($from) || !$valid($to) || $from > $to) {
            $from = date('Y-m-01');
            $to   = date('Y-m-t');
        }

        return [$from, $to];
    }
}
