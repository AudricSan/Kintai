<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Staff;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\PdfCjkFontResolver;
use kintai\Core\Services\RoleAssignmentSyncService;
use kintai\UI\ViewRenderer;
use kintai\UI\Controller\Web\HasAdminAccess;

final class AdminUserController
{
    use HasAdminAccess;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly HiringReportRepositoryInterface $hiringReports,
        private readonly AuditLogger $auditLogger,
        private readonly RoleAssignmentSyncService $roleSync,
    ) {}

    // -------------------------------------------------------------------------
    // Users — CRUD
    // -------------------------------------------------------------------------

    public function users(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        if ($managedIds !== null) {
            $memberIds = $this->memberUserIds($managedIds);
            $users = array_values(array_filter(
                $this->users->findAll(),
                fn($u) => in_array((int) $u['id'], $memberIds, true)
            ));
        } else {
            $users = $this->users->findAll();
        }

        // Statistiques du mois en cours
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        // Nombre de semaines ISO distinctes dans le mois
        $weekSet = [];
        $cur = new \DateTime($monthStart);
        $endDt = new \DateTime($monthEnd);
        while ($cur <= $endDt) {
            $weekSet[$cur->format('o-W')] = true;
            $cur->modify('+1 day');
        }
        $numWeeks = max(1, count($weekSet));

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        $userStats = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];

            $personalRates = [];
            foreach ($this->userRates->findByUser($uid) as $r) {
                $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
            }

            $totalMinutes = 0;
            $estimatedPay = 0.0;
            foreach ($this->shifts->findByUser($uid) as $s) {
                $d = $s['shift_date'] ?? '';
                if ($d < $monthStart || $d > $monthEnd) {
                    continue;
                }
                [$sh, $sm] = explode(':', substr($s['start_time'] ?? '00:00', 0, 5));
                [$eh, $em] = explode(':', substr($s['end_time'] ?? '00:00', 0, 5));
                $startMin = (int) $sh * 60 + (int) $sm;
                $endMin   = (int) $eh * 60 + (int) $em;
                if (!empty($s['cross_midnight']) || $endMin <= $startMin) {
                    $endMin += 24 * 60;
                }
                $minutes       = max(0, $endMin - $startMin);
                $totalMinutes += $minutes;

                $tid  = (int) ($s['shift_type_id'] ?? 0);
                $rate = $personalRates[$tid] ?? (float) ($typesMap[$tid]['hourly_rate'] ?? 0);
                $estimatedPay += ($minutes / 60) * $rate;
            }

            $userStats[$uid] = [
                'hours_month'   => $totalMinutes / 60,
                'hours_week'    => ($totalMinutes / 60) / $numWeeks,
                'estimated_pay' => $estimatedPay,
            ];
        }

        $availableStores = $this->availableStores($managedIds);
        $storeCurrency   = strtoupper(trim($availableStores[0]['currency'] ?? 'JPY'));
        $storeCurrencyStyle = store_currency_style($availableStores[0] ?? null);

        // Map store id → name
        $storeNames = [];
        $availableStoreIds = [];
        foreach ($availableStores as $s) {
            $sid = (int) $s['id'];
            $storeNames[$sid] = $s['name'];
            $availableStoreIds[] = $sid;
        }

        // Pour chaque user : liste des storeIds et premier store (liens stats/paie)
        $userStoreIds = [];
        $userStoreMap = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];
            $ids = [];
            foreach ($this->storeUsers->findByUser($uid) as $m) {
                $sid = (int) $m['store_id'];
                if (in_array($sid, $availableStoreIds, true)) {
                    $ids[] = $sid;
                    $userStoreMap[$uid] ??= $sid;
                }
            }
            $userStoreIds[$uid] = $ids;
        }

        // Filtre par magasin
        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        if ($filterStoreId > 0) {
            $users = array_values(array_filter(
                $users,
                fn($u) => in_array($filterStoreId, $userStoreIds[(int) $u['id']] ?? [], true)
            ));
        } elseif ($filterStoreId === -1) {
            $users = array_values(array_filter(
                $users,
                fn($u) => empty($userStoreIds[(int) $u['id']])
            ));
        }

        // Recherche texte (nom, code employé, email) — appliquée entièrement
        // côté client (voir kana-search.js/users-list-search.js) pour un
        // filtrage instantané, tolérant à la langue (romaji/hiragana/kana
        // vers un nom stocké en kanji, via les furigana_*) et sans
        // rechargement de page à chaque frappe. `$search` ne sert ici qu'à
        // pré-remplir le champ pour un lien direct (`?search=...`).
        $search = trim((string) ($request->query('search') ?? ''));

        // Tri
        $sort = $request->query('sort') ?? 'name_asc';
        usort($users, function ($a, $b) use ($sort, $userStats) {
            $nameA   = strtolower($a['display_name'] ?? $a['email'] ?? '');
            $nameB   = strtolower($b['display_name'] ?? $b['email'] ?? '');
            $emailA  = strtolower($a['email'] ?? '');
            $emailB  = strtolower($b['email'] ?? '');
            $roleA   = (int) ($a['is_admin'] ?? 0);
            $roleB   = (int) ($b['is_admin'] ?? 0);
            $statA   = (int) (!empty($a['is_active']) && empty($a['deleted_at']));
            $statB   = (int) (!empty($b['is_active']) && empty($b['deleted_at']));
            $hA      = $userStats[(int)($a['id'] ?? 0)]['hours_month'] ?? 0;
            $hB      = $userStats[(int)($b['id'] ?? 0)]['hours_month'] ?? 0;
            return match ($sort) {
                'name_desc'   => strcmp($nameB, $nameA),
                'email_asc'   => strcmp($emailA, $emailB) ?: strcmp($nameA, $nameB),
                'email_desc'  => strcmp($emailB, $emailA) ?: strcmp($nameA, $nameB),
                'role_asc'    => $roleA <=> $roleB ?: strcmp($nameA, $nameB),
                'role_desc'   => $roleB <=> $roleA ?: strcmp($nameA, $nameB),
                'status_asc'  => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc' => $statB <=> $statA ?: strcmp($nameA, $nameB),
                'hours_asc'   => $hA <=> $hB ?: strcmp($nameA, $nameB),
                'hours_desc'  => $hB <=> $hA ?: strcmp($nameA, $nameB),
                default       => strcmp($nameA, $nameB), // name_asc
            };
        });

        return Response::html($this->view->render('staff.users', [
            'title'            => 'Personnel',
            'users'            => $users,
            'user_stats'       => $userStats,
            'month_label'      => date('F Y'),
            'store_currency'   => $storeCurrency,
            'store_currency_symbol_style' => $storeCurrencyStyle,
            'sort'             => $sort,
            'available_stores' => $availableStores,
            'store_names'      => $storeNames,
            'user_store_ids'   => $userStoreIds,
            'user_store_map'   => $userStoreMap,
            'filter_store_id'  => $filterStoreId,
            'filter_search'    => $search,
        ], 'layout.app'));
    }

    // -------------------------------------------------------------------------
    // Users — export (item 3)
    // -------------------------------------------------------------------------

    public function exportUsersJson(Request $request): Response
    {
        $rows = $this->usersForExport($request);

        $this->auditLogger->log($request, 'export.employees_json', 'user', 0, ['count' => count($rows)]);

        return Response::jsonDownload(['data' => $rows], 'employees_' . date('Ymd') . '.json');
    }

    /**
     * Aperçu HTML du PDF (route .../export/pdf) : pas de téléchargement
     * automatique, la même vue users-export-pdf.php est rendue directement
     * dans le navigateur avec une barre d'outils (impression / téléchargement
     * réel / fermer), comme les rapports RH. Le fichier PDF n'est généré
     * (via mPDF) que si l'utilisateur clique "Télécharger" (exportUsersPdfDownload()).
     */
    public function exportUsersPdf(Request $request): Response
    {
        $rows = $this->usersForExport($request);

        $storeId = (int) ($request->query('store_id') ?? 0);
        $downloadUrl = $this->base() . '/admin/users/export/pdf/download' . ($storeId !== 0 ? '?store_id=' . $storeId : '');

        $html = $this->view->render('staff.users-export-pdf', [
            'rows'         => $rows,
            'generated_at' => date('Y-m-d H:i'),
            'downloadUrl'  => $downloadUrl,
        ]);

        return Response::html($html);
    }

    public function exportUsersPdfDownload(Request $request): Response
    {
        $rows = $this->usersForExport($request);

        $html = $this->view->render('staff.users-export-pdf', [
            'rows'         => $rows,
            'generated_at' => date('Y-m-d H:i'),
        ]);

        $tmpDir = storage_path('app/mpdf');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $mpdf = new \Mpdf\Mpdf(PdfCjkFontResolver::applyTo([
            'mode'          => 'utf-8',
            'format'        => 'A4-L',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'tempDir'       => $tmpDir,
        ]));
        $mpdf->SetTitle('Employés');
        $mpdf->WriteHTML($html);

        $this->auditLogger->log($request, 'export.employees_pdf', 'user', 0, ['count' => count($rows)]);

        return Response::pdf(
            $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN),
            'employees_' . date('Ymd') . '.pdf',
        );
    }

    /**
     * Liste des employés visibles (mêmes filtres store_id que la page), avec
     * coordonnées et taux horaires — pour les exports PDF/JSON.
     */
    private function usersForExport(Request $request): array
    {
        $managedIds = $this->managedIds($request);

        if ($managedIds !== null) {
            $memberIds = $this->memberUserIds($managedIds);
            $users = array_values(array_filter(
                $this->users->findAll(),
                fn($u) => in_array((int) $u['id'], $memberIds, true)
            ));
        } else {
            $users = $this->users->findAll();
        }

        $availableStores = $this->availableStores($managedIds);
        $availableStoreIds = array_map(fn($s) => (int) $s['id'], $availableStores);
        $storeNames = [];
        foreach ($availableStores as $s) {
            $storeNames[(int) $s['id']] = $s['name'] ?? '';
        }

        $userStoreIds = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];
            $ids = [];
            foreach ($this->storeUsers->findByUser($uid) as $m) {
                $sid = (int) $m['store_id'];
                if (in_array($sid, $availableStoreIds, true)) {
                    $ids[] = $sid;
                }
            }
            $userStoreIds[$uid] = $ids;
        }

        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        if ($filterStoreId > 0) {
            $users = array_values(array_filter(
                $users,
                fn($u) => in_array($filterStoreId, $userStoreIds[(int) $u['id']] ?? [], true)
            ));
        } elseif ($filterStoreId === -1) {
            $users = array_values(array_filter(
                $users,
                fn($u) => empty($userStoreIds[(int) $u['id']])
            ));
        }

        usort($users, fn($a, $b) => strcmp(
            strtolower($a['display_name'] ?? $a['email'] ?? ''),
            strtolower($b['display_name'] ?? $b['email'] ?? ''),
        ));

        $shiftTypeNames = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $shiftTypeNames[(int) $t['id']] = $t['name'] ?? ('#' . $t['id']);
        }

        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];

            $rates = [];
            foreach ($this->userRates->findByUser($uid) as $r) {
                $tid = (int) $r['shift_type_id'];
                $rates[] = [
                    'shift_type'  => $shiftTypeNames[$tid] ?? ('#' . $tid),
                    'hourly_rate' => (float) $r['hourly_rate'],
                ];
            }

            $storeLabels = array_values(array_filter(array_map(
                fn($sid) => $storeNames[$sid] ?? null,
                $userStoreIds[$uid] ?? [],
            )));

            $rows[] = [
                'id'            => $uid,
                'employee_code' => $u['employee_code'] ?? null,
                'last_name'     => $u['last_name'] ?? '',
                'first_name'    => $u['first_name'] ?? '',
                'display_name'  => $u['display_name'] ?? '',
                'email'         => $u['email'] ?? '',
                'phone'         => $u['phone'] ?? null,
                'mobile_phone'  => $u['mobile_phone'] ?? null,
                'postal_code'   => $u['postal_code'] ?? null,
                'address'       => $u['address'] ?? null,
                'stores'        => $storeLabels,
                'is_admin'      => !empty($u['is_admin']),
                'is_active'     => !empty($u['is_active']) && empty($u['deleted_at']),
                'hourly_rates'  => $rates,
            ];
        }

        return $rows;
    }

    public function createUser(Request $request): Response
    {
        return Response::html($this->view->render('staff.users-form', [
            'title'                 => 'Nouvel utilisateur',
            'mode'                  => 'create',
            'user'                  => [],
            'all_stores'            => $this->availableStores($this->managedIds($request)),
            'assignable_roles'      => $this->roleSync->assignableStoreRoles(),
            'default_store_role_id' => (int) ($this->roleSync->defaultStoreRole()['id'] ?? 0),
        ], 'layout.app'));
    }

    public function storeUser(Request $request): Response
    {
        $email = trim($request->post('email', ''));
        if ($email !== '' && $this->users->findByEmail($email) !== null) {
            return Response::redirect($this->base() . '/admin/users/create?error=email_taken');
        }

        if (trim($request->post('last_name', '')) === '' || trim($request->post('first_name', '')) === '') {
            return Response::redirect($this->base() . '/admin/users/create?error=name_required');
        }

        if (trim($request->post('furigana_last_name', '')) === '' || trim($request->post('furigana_first_name', '')) === '') {
            return Response::redirect($this->base() . '/admin/users/create?error=furigana_required');
        }

        $password = $request->post('password', '');
        $empCode  = strtoupper(trim($request->post('employee_code', ''))) ?: null;
        if ($password === '') {
            $fn  = trim($request->post('first_name', ''));
            $ln  = trim($request->post('last_name', ''));
            $suf = $empCode ?? (string) rand(1, 9999);
            $pw  = strtolower($ln . $fn . $suf);
            $pw  = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $pw));
            $password = $pw ?: ('User' . $suf);
        }
        $saved    = $this->users->save([
            'display_name'       => $request->post('display_name', ''),
            'first_name'         => $request->post('first_name', ''),
            'last_name'          => $request->post('last_name', ''),
            'email'              => $request->post('email', ''),
            'phone'              => $request->post('phone', '') ?: null,
            'mobile_phone'       => $request->post('mobile_phone', '') ?: null,
            'furigana'           => $request->post('furigana', '') ?: null,
            'furigana_last_name'  => $request->post('furigana_last_name', '') ?: null,
            'furigana_first_name' => $request->post('furigana_first_name', '') ?: null,
            'gender'             => $request->post('gender', '') ?: null,
            'tax_classification' => $request->post('tax_classification', '') ?: null,
            'birth_date'         => $request->post('birth_date', '') ?: null,
            'education'          => $request->post('education', '') ?: null,
            'postal_code'        => $request->post('postal_code', '') ?: null,
            'address'            => $request->post('address', '') ?: null,
            'guarantor_name'     => $request->post('guarantor_name', '') ?: null,
            'guarantor_phone'    => $request->post('guarantor_phone', '') ?: null,
            'color'              => $request->post('color', '#3B82F6'),
            'employee_code'      => $empCode,
            'password_hash'      => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'is_admin'           => $request->post('is_admin') === '1' ? 1 : 0,
            'is_active'          => 1,
        ]);
        $this->roleSync->syncOwnerRole((int) ($saved['id'] ?? 0), $request->post('is_admin') === '1');

        // Affecter au magasin si sélectionné
        $storeId = (int) $request->post('store_id', 0);
        if ($storeId > 0 && !empty($saved['id'])) {
            $this->assertStoreAccess($request, $storeId);
            $role = $this->roleSync->findAssignableRole((int) $request->post('store_role_id', 0))
                ?? $this->roleSync->defaultStoreRole();
            $membership = $this->storeUsers->save([
                'store_id' => $storeId,
                'user_id'  => (int) $saved['id'],
                'role'     => $role !== null ? $this->roleSync->legacyRoleFor((int) $role['id']) : 'staff',
            ]);
            if ($role !== null) {
                $this->roleSync->syncStoreRoleById((int) $saved['id'], $storeId, (int) $role['id']);
            }

            // Générer automatiquement le rapport d'embauche
            $this->hiringReports->save([
                'store_id'           => $storeId,
                'user_id'            => (int) $saved['id'],
                'employee_number'    => $empCode,
                'employee_name'      => $request->post('display_name', ''),
            'furigana'            => null,
            'furigana_last_name'  => $request->post('furigana_last_name', '') ?: null,
            'furigana_first_name' => $request->post('furigana_first_name', '') ?: null,
                'gender'             => $request->post('gender', '') ?: null,
                'tax_classification' => $request->post('tax_classification', '') ?: null,
                'birth_date'         => $request->post('birth_date', '') ?: null,
                'hire_date'          => date('Y-m-d'),
                'education'          => $request->post('education', '') ?: null,
                'postal_code'        => $request->post('postal_code', '') ?: null,
                'address'            => $request->post('address', '') ?: null,
                'phone'              => $request->post('phone', '') ?: null,
                'mobile_phone'       => $request->post('mobile_phone', '') ?: null,
                'email'              => $request->post('email', '') ?: null,
                'guarantor_name'     => $request->post('guarantor_name', '') ?: null,
                'guarantor_phone'    => $request->post('guarantor_phone', '') ?: null,
                'store_name'         => null,
                'hired_by'           => $request->post('display_name', ''),
                'created_by'         => $request->getAttribute('auth_user')['id'] ?? 0,
            ]);
        }

        $this->auditLogger->log($request, 'user.created', 'user', (int) ($saved['id'] ?? 0), [
            'email' => $request->post('email', ''),
        ]);
        return Response::redirect($this->base() . '/admin/users?success=created');
    }

    /**
     * Création rapide d'un utilisateur depuis la prévisualisation d'import Excel.
     * Retourne JSON {success, user: {id, name}} ou {success: false, error}.
     */
    public function quickCreateUser(Request $request): Response
    {
        $displayName = trim($request->post('display_name', ''));
        $firstName   = trim($request->post('first_name', ''));
        $lastName    = trim($request->post('last_name', ''));
        $email       = trim($request->post('email', ''));
        $phone       = trim($request->post('phone', '')) ?: null;
        $empCode     = strtoupper(trim($request->post('employee_code', ''))) ?: null;
        $password    = $request->post('password', '');
        $color       = $request->post('color', '#3B82F6');
        $isAdmin     = $request->post('is_admin') === '1' ? 1 : 0;
        $storeId     = (int) $request->post('store_id', 0);
        $storeRoleId = (int) $request->post('store_role_id', 0);

        if ($lastName === '' || $firstName === '') {
            return Response::json(['success' => false, 'error' => 'name_required'], 422);
        }

        $furiganaLastName  = trim($request->post('furigana_last_name', ''));
        $furiganaFirstName = trim($request->post('furigana_first_name', ''));
        if ($furiganaLastName === '' || $furiganaFirstName === '') {
            return Response::json(['success' => false, 'error' => 'furigana_required'], 422);
        }

        if ($displayName === '') {
            $displayName = trim($firstName . ' ' . $lastName);
        }

        if ($email === '') {
            // Générer un email unique @kintai.local
            $base = mb_strtolower($firstName . '.' . $lastName);
            $base = preg_replace('/[^a-z0-9.]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base));
            $base = trim($base, '.');
            if ($base === '') $base = 'user';
            $email   = $base . '@kintai.local';
            $attempt = 0;
            while ($this->users->findByEmail($email) !== null) {
                $attempt++;
                $email = $base . $attempt . '@kintai.local';
                if ($attempt > 99) break;
            }
        } elseif ($this->users->findByEmail($email) !== null) {
            // Email fourni explicitement (pas auto-généré) : évite un crash SQL
            // (contrainte unique) si l'employé existe déjà sous un autre nom Excel
            // non reconnu par le matching de l'import.
            return Response::json(['success' => false, 'error' => 'email_taken'], 409);
        }

        // Vérifier unicité du code employé si fourni
        if ($empCode !== null && $this->users->findByEmployeeCode($empCode) !== null) {
            return Response::json(['success' => false, 'error' => 'employee_code_taken'], 409);
        }

        if ($password === '') {
            $suf = $empCode ?? (string) rand(1, 9999);
            $pw  = strtolower($lastName . $firstName . $suf);
            $pw  = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $pw));
            $password = $pw ?: ('User' . $suf);
        }

        $saved = $this->users->save([
            'display_name'  => $displayName,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $email,
            'phone'         => $phone,
            'mobile_phone'  => $request->post('mobile_phone', '') ?: null,
            'furigana'            => null,
            'furigana_last_name'  => $furiganaLastName,
            'furigana_first_name' => $furiganaFirstName,
            'gender'        => $request->post('gender', '') ?: null,
            'birth_date'    => $request->post('birth_date', '') ?: null,
            'education'     => $request->post('education', '') ?: null,
            'postal_code'   => $request->post('postal_code', '') ?: null,
            'address'       => $request->post('address', '') ?: null,
            'employee_code' => $empCode,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'is_admin'      => $isAdmin,
            'is_active'     => 1,
            'color'         => $color,
        ]);

        if (empty($saved['id'])) {
            return Response::json(['success' => false, 'error' => 'Création échouée.'], 500);
        }
        $this->roleSync->syncOwnerRole((int) $saved['id'], (bool) $isAdmin);

        if ($storeId > 0) {
            $this->assertStoreAccess($request, $storeId);
            $role = $this->roleSync->findAssignableRole($storeRoleId) ?? $this->roleSync->defaultStoreRole();
            $this->storeUsers->save([
                'store_id' => $storeId,
                'user_id'  => (int) $saved['id'],
                'role'     => $role !== null ? $this->roleSync->legacyRoleFor((int) $role['id']) : 'staff',
            ]);
            if ($role !== null) {
                $this->roleSync->syncStoreRoleById((int) $saved['id'], $storeId, (int) $role['id']);
            }

            // Générer automatiquement le rapport d'embauche
            $this->hiringReports->save([
                'store_id'        => $storeId,
                'user_id'         => (int) $saved['id'],
                'employee_number' => $empCode,
                'employee_name'   => $displayName,
                'created_by'      => $request->getAttribute('auth_user')['id'] ?? 0,
            ]);
        }

        $this->auditLogger->log($request, 'user.created', 'user', (int) $saved['id'], [
            'email'  => $email,
            'source' => 'excel_import',
        ]);

        return Response::json([
            'success' => true,
            'user'    => ['id' => (int) $saved['id'], 'name' => $displayName],
        ]);
    }

    /**
     * Vérifie en temps réel si un code employé est disponible. Retourne JSON {available: bool}.
     * `exclude_id` (édition) : ignore la correspondance si c'est le propre code de l'utilisateur en cours d'édition.
     */
    public function checkEmployeeCode(Request $request): Response
    {
        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') {
            return Response::json(['available' => true]);
        }
        $existing  = $this->users->findByEmployeeCode($code);
        $excludeId = (int) $request->query('exclude_id', 0);
        $taken     = $existing !== null && (int) $existing['id'] !== $excludeId;
        return Response::json(['available' => !$taken]);
    }

    /**
     * Vérifie en temps réel si une adresse email est disponible. Retourne JSON {available: bool}.
     * `exclude_id` (édition) : ignore la correspondance si c'est le propre email de l'utilisateur en cours d'édition.
     */
    public function checkEmail(Request $request): Response
    {
        $email = trim($request->query('email', ''));
        if ($email === '') {
            return Response::json(['available' => true]);
        }
        $existing  = $this->users->findByEmail($email);
        $excludeId = (int) $request->query('exclude_id', 0);
        $taken     = $existing !== null && (int) $existing['id'] !== $excludeId;
        return Response::json(['available' => !$taken]);
    }

    public function editUser(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $userId = (int) $user['id'];

        // Shift types des stores où l'utilisateur est membre
        $memberships   = $this->storeUsers->findByUser($userId);
        $userStoreIds  = array_map(fn($m) => (int) $m['store_id'], $memberships);
        $userStoreIds  = array_unique($userStoreIds);

        $userShiftTypes = empty($userStoreIds)
            ? []
            : $this->filterByStore($this->shiftTypes->findAll(), $userStoreIds);

        // Taux actuels indexés par shift_type_id
        $userRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $userRates[(int) $r['shift_type_id']] = $r;
        }

        // Map store_id → store name pour l'affichage
        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
        }

        // Appartenance aux stores (enrichie avec nom du store, rôle, cotisations)
        $roleMap = $this->roleSync->storeRoleMapForUser($userId);
        $userMemberships = [];
        foreach ($memberships as $m) {
            $sid = (int) $m['store_id'];
            $mid = (int) ($m['id'] ?? 0);
            $userMemberships[] = array_merge($m, [
                'store_name'         => $storesMap[$sid] ?? '#' . $sid,
                'role_name'          => $roleMap[$sid]['name'] ?? ($m['role'] ?? '—'),
                'role_is_managing'   => !empty($roleMap[$sid]['is_managing']),
                'store_ded_settings' => $this->stores->getDeductionSettings($sid),
                'ded_overrides'      => ['subject_to_deductions' => $mid > 0 ? $this->storeUsers->getSubjectToDeductions($mid) : false],
            ]);
        }

        // Stores disponibles pour ajouter l'utilisateur (ceux où il n'est pas encore)
        $availableStores = array_values(array_filter(
            $this->availableStores($this->managedIds($request)),
            fn($s) => !in_array((int) $s['id'], $userStoreIds, true)
        ));

        $this->auditLogger->log($request, 'user.viewed', 'user', $userId, [], $userStoreIds[0] ?? null);

        return Response::html($this->view->render('staff.users-form', [
            'title'            => 'Modifier ' . htmlspecialchars($user['display_name'] ?? ''),
            'mode'             => 'edit',
            'user'             => $user,
            'user_shift_types' => $userShiftTypes,
            'user_rates'       => $userRates,
            'stores_map'       => $storesMap,
            'user_memberships' => $userMemberships,
            'available_stores' => $availableStores,
            'all_stores'       => $this->availableStores($this->managedIds($request)),
            'assignable_roles'      => $this->roleSync->assignableStoreRoles(),
            'default_store_role_id' => (int) ($this->roleSync->defaultStoreRole()['id'] ?? 0),
        ], 'layout.app'));
    }

    public function updateUser(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $email = trim($request->post('email', $user['email'] ?? ''));
        if ($email !== ($user['email'] ?? '')) {
            $existing = $this->users->findByEmail($email);
            if ($existing !== null && (int) $existing['id'] !== (int) $user['id']) {
                return Response::redirect($this->base() . '/admin/users/' . $user['id'] . '/edit?error=email_taken');
            }
        }

        $lastName  = trim($request->post('last_name', $user['last_name'] ?? ''));
        $firstName = trim($request->post('first_name', $user['first_name'] ?? ''));
        if ($lastName === '' || $firstName === '') {
            return Response::redirect($this->base() . '/admin/users/' . $user['id'] . '/edit?error=name_required');
        }

        $furiganaLastName  = trim($request->post('furigana_last_name', $user['furigana_last_name'] ?? ''));
        $furiganaFirstName = trim($request->post('furigana_first_name', $user['furigana_first_name'] ?? ''));
        if ($furiganaLastName === '' || $furiganaFirstName === '') {
            return Response::redirect($this->base() . '/admin/users/' . $user['id'] . '/edit?error=furigana_required');
        }

        $empCode = strtoupper(trim($request->post('employee_code', ''))) ?: null;
        $data = array_merge($user, [
            'display_name'       => $request->post('display_name', $user['display_name'] ?? ''),
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'email'              => $email,
            'phone'              => $request->post('phone', '') ?: null,
            'mobile_phone'       => $request->post('mobile_phone', '') ?: null,
            'furigana'            => null,
            'furigana_last_name'  => $furiganaLastName,
            'furigana_first_name' => $furiganaFirstName,
            'gender'             => $request->post('gender', '') ?: null,
            'tax_classification' => $request->post('tax_classification', '') ?: null,
            'birth_date'         => $request->post('birth_date', '') ?: null,
            'education'          => $request->post('education', '') ?: null,
            'postal_code'        => $request->post('postal_code', '') ?: null,
            'address'            => $request->post('address', '') ?: null,
            'guarantor_name'     => $request->post('guarantor_name', '') ?: null,
            'guarantor_phone'    => $request->post('guarantor_phone', '') ?: null,
            'color'              => $request->post('color', $user['color'] ?? '#3B82F6'),
            'employee_code'      => $empCode,
            'is_admin'           => $request->post('is_admin') === '1' ? 1 : 0,
            'is_active'          => $request->post('is_active') === '1' ? 1 : 0,
        ]);

        // Mettre à jour le mot de passe uniquement si fourni
        $newPassword = $request->post('password', '');
        if ($newPassword !== '') {
            $data['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->users->save($data);
        $this->roleSync->syncOwnerRole((int) $user['id'], $request->post('is_admin') === '1');
        $this->auditLogger->logUpdate($request, 'user.updated', 'user', (int) $user['id'], $user, $data, [], null, null);
        return Response::redirect($this->base() . '/admin/users?success=updated');
    }

    public function deleteUser(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->users->delete($id);
        $this->auditLogger->log($request, 'user.deleted', 'user', $id, []);
        return Response::redirect($this->base() . '/admin/users?success=deleted');
    }

    public function resetPassword(Request $request): Response
    {
        $user   = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $oldUser = $user;
        $user['password_hash'] = password_hash('0000', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->users->save($user);
        $this->auditLogger->logUpdate($request, 'user.password_reset', 'user', (int) $user['id'], $oldUser, $user, [
            'email' => $user['email'] ?? null,
        ], null, null);

        return Response::redirect($this->base() . '/admin/users/' . $user['id'] . '/edit?success=password_reset');
    }

    // -------------------------------------------------------------------------
    // Users — taux horaires par type de shift
    // -------------------------------------------------------------------------

    public function setUserRate(Request $request): Response
    {
        $userId = (int) $request->param('id');
        $user   = $this->users->findById($userId);
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }

        $shiftTypeId = (int) $request->post('shift_type_id', 0);
        $rateRaw     = $request->post('hourly_rate', '');

        // Vérifier que le type de shift est dans un store accessible
        $type = $this->shiftTypes->findById($shiftTypeId);
        if ($type !== null) {
            $this->assertStoreAccess($request, (int) $type['store_id']);
        }

        $existing = $this->userRates->findRate($userId, $shiftTypeId);

        if ($rateRaw === '') {
            if ($existing !== null) {
                $this->userRates->delete((int) $existing['id']);
                $this->auditLogger->log($request, 'user_rate.deleted', 'user_rate', (int) $existing['id'], [
                    'user_id'       => $userId,
                    'shift_type_id' => $shiftTypeId,
                ]);
            }
        } else {
            $data = [
                'user_id'       => $userId,
                'shift_type_id' => $shiftTypeId,
                'hourly_rate'   => (float) $rateRaw,
            ];
            if ($existing !== null) {
                $data = array_merge($existing, $data);
            }
            $saved = $this->userRates->save($data);
            if ($existing !== null) {
                $this->auditLogger->logUpdate($request, 'user_rate.saved', 'user_rate', (int) ($saved['id'] ?? 0), $existing, $saved, [
                    'user_id'       => $userId,
                    'shift_type_id' => $shiftTypeId,
                ], null, null);
            } else {
                $this->auditLogger->log($request, 'user_rate.saved', 'user_rate', (int) ($saved['id'] ?? 0), [
                    'user_id'       => $userId,
                    'shift_type_id' => $shiftTypeId,
                    'hourly_rate'   => (float) $rateRaw,
                ]);
            }
        }

        return Response::redirect($this->base() . '/admin/users/' . $userId . '/edit?success=rate_updated');
    }

    public function deleteUserRate(Request $request): Response
    {
        $userId = (int) $request->param('id');
        $rid    = (int) $request->param('rid');

        $rate = $this->userRates->findById($rid);
        if ($rate !== null && (int) $rate['user_id'] === $userId) {
            $type = $this->shiftTypes->findById((int) $rate['shift_type_id']);
            if ($type !== null) {
                $this->assertStoreAccess($request, (int) $type['store_id']);
            }
            $this->userRates->delete($rid);
            $this->auditLogger->log($request, 'user_rate.deleted', 'user_rate', $rid, [
                'user_id'       => $userId,
                'shift_type_id' => $rate['shift_type_id'] ?? null,
            ]);
        }

        return Response::redirect($this->base() . '/admin/users/' . $userId . '/edit?success=rate_deleted');
    }

}
