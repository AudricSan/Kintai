<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\AvatarImageOptimizer;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

final class AuthController
{
    use HasBaseUrl;
    private const NAV_OWNER_SECTIONS   = ['planning', 'hr', 'requests', 'statistics', 'system'];
    private const NAV_MANAGER_SECTIONS = ['planning', 'hr', 'requests', 'statistics'];
    private const NAV_OWNER_KEYS = [
        'shifts', 'calendar', 'shift_types', 'timeclocks',
        'users', 'stores',
        'timeoff', 'swaps', 'open_shifts', 'messages',
        'daily_reports', 'audit_log',
    ];
    private const NAV_MANAGER_KEYS = [
        'shifts', 'calendar', 'shift_types', 'timeclocks',
        'timeoff', 'swaps', 'open_shifts', 'messages',
        'employee_report', 'daily_reports',
    ];
    private const BOTTOM_NAV_POOL    = ['shifts', 'team', 'requests', 'messages', 'swaps', 'timeclocks', 'daily_reports'];
    private const BOTTOM_NAV_DEFAULT = ['shifts', 'team', 'requests'];

    // Vocabulaire de nav pour un employé simple (ni admin, ni manager d'un store)
    private const NAV_EMPLOYEE_KEYS = [
        'my_planning', 'timeclock',
        'my_timeoff', 'swaps', 'open_shifts', 'messages',
        'my_profile',
    ];
    private const NAV_EMPLOYEE_SECTIONS       = ['planning', 'requests', 'statistics', 'account'];
    private const BOTTOM_NAV_POOL_EMPLOYEE    = ['my_planning', 'timeclock', 'my_timeoff', 'messages', 'swaps', 'open_shifts', 'my_profile'];
    private const BOTTOM_NAV_DEFAULT_EMPLOYEE = ['my_planning', 'timeclock', 'my_timeoff'];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AuthService $auth,
        private readonly AuditLogger $auditLogger,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly IcalTokenRepositoryInterface $icalTokens,
        private readonly UserNavPrefsRepositoryInterface $navPrefs,
        private readonly AvailabilityRepositoryInterface $availabilities,
        private readonly LanguageRepositoryInterface $languages,
        private readonly AvatarImageOptimizer $avatarOptimizer,
    ) {}

    /** @var array<string, string> extension → type MIME (avatars) */
    private const AVATAR_MIME_TYPES = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];

    /** Affiche le formulaire de connexion. */
    public function showLogin(Request $request): Response
    {
        // Déjà connecté → rediriger vers le dashboard
        if ($this->auth->check()) {
            return Response::redirect($this->base() . '/');
        }

        return Response::html($this->view->render('auth.login', [
            'title'      => 'Connexion',
            'error'      => !empty($_GET['error']),
            'login_mode' => $_GET['mode'] ?? 'code',
        ], 'layout.guest'));
    }

    /** Traite la soumission du formulaire de connexion. */
    public function login(Request $request): Response
    {
        $mode     = $request->post('login_mode', 'email');
        $remember = $request->post('remember') === '1';

        if ($mode === 'code') {
            // Connexion par code employé + code magasin + mot de passe
            $employeeCode = trim($request->post('employee_code', ''));
            $storeCode    = trim($request->post('store_code', ''));
            $password     = $request->post('password', '0000');
            $ok = $this->auth->attemptByCode($employeeCode, $storeCode, $password, $remember);
        } else {
            // Connexion classique email + mot de passe
            $email    = trim($request->post('email', ''));
            $password = $request->post('password', '');
            $ok = $this->auth->attempt($email, $password, $remember);
        }

        if ($ok) {
            $authUser = $this->auth->user();
            $userId   = $authUser ? (int) ($authUser['id'] ?? 0) : null;
            $this->auditLogger->log($request, 'auth.login', 'user', $userId, ['mode' => $mode], null, $userId);
            if ($userId !== null) {
                $this->users->save(['id' => $userId, 'last_login_at' => date('Y-m-d H:i:s')]);
            }

            // Laisser la préférence BD de l'utilisateur prendre effet (I18nMiddleware)
            unset($_SESSION['locale']);

            if ($this->auth->isAdmin()) {
                $destination = $this->base() . '/admin/shifts/timeline';
            } elseif ($this->auth->isManager()) {
                $destination = $this->base() . '/admin/shifts/timeline';
            } else {
                $destination = $this->base() . '/employee';
            }
            return Response::redirect($destination);
        }

        $this->auditLogger->log($request, 'auth.login_failed', 'user', null, ['mode' => $mode]);
        return Response::redirect($this->base() . '/login?error=1&mode=' . urlencode($mode));
    }

    /** Bascule entre vue mobile et vue bureau (forcé en session). */
    public function switchDevice(Request $request): Response
    {
        $target = $request->post('device_view', '');
        if (in_array($target, ['mobile', 'desktop'], true)) {
            $_SESSION['device_view'] = $target;
        } else {
            unset($_SESSION['device_view']); // retour à la détection auto
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ($this->base() . '/');
        return Response::redirect($referer);
    }

    /** Change la langue de l'utilisateur (session + BD si connecté). */
    public function switchLanguage(Request $request): Response
    {
        $locale = $request->param('locale');
        $activeCodes = array_column($this->languages->findAllActive(), 'code');
        if (!in_array($locale, $activeCodes, true)) {
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? ($this->base() . '/'));
        }

        $_SESSION['locale'] = $locale;

        // Persister en BD si l'utilisateur est connecté
        $user = $this->auth->user();
        if ($user) {
            try {
                $dbUser = $this->users->findById((int) $user['id']);
                if ($dbUser) {
                    $dbUser['language']    = $locale;
                    $this->users->save($dbUser);
                    $_SESSION['auth_user'] = $dbUser;
                }
            } catch (\Throwable) {
                // Colonne language absente (migration non exécutée) — session suffit
            }
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ($this->base() . '/');
        return Response::redirect($referer);
    }

    /** Affiche le profil de l'utilisateur. */
    public function showProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId    = (int) $user['id'];
        $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base      = $this->base();

        $memberships = $this->storeUsers->findByUser($userId);
        $stores      = [];
        foreach ($memberships as $m) {
            $s = $this->stores->findById((int) $m['store_id']);
            if ($s) {
                $stores[(int) $s['id']] = $s;
            }
        }

        // Onglet courant
        $tab = in_array((string) $request->query('tab'), ['info', 'availability', 'ical', 'nav', 'data'], true)
            ? $request->query('tab')
            : 'info';

        // Liens iCal
        $icalLinks = [];
        foreach ($memberships as $membership) {
            $storeId  = (int) $membership['store_id'];
            $store    = $stores[$storeId] ?? null;
            if (!$store) {
                continue;
            }

            $tokenRow = $this->icalTokens->findByUserAndStore($userId, $storeId);
            if (!$tokenRow) {
                $this->icalTokens->save([
                    'user_id'    => $userId,
                    'store_id'   => $storeId,
                    'token'      => bin2hex(random_bytes(32)),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $tokenRow = $this->icalTokens->findByUserAndStore($userId, $storeId);
            }

            $icalLinks[] = [
                'store_id'   => $storeId,
                'store_name' => $store['name'] ?? '',
                'url'        => "{$scheme}://{$host}{$base}/ical/{$tokenRow['token']}/{$storeId}/shifts.ics",
            ];
        }

        // Disponibilités (tab availability)
        $storeId  = (int) ($request->query('store_id') ?? array_key_first($stores) ?? 0);
        if (!isset($stores[$storeId]) && !empty($stores)) {
            $storeId = (int) array_key_first($stores);
        }
        $existing = [];
        if ($storeId > 0) {
            foreach ($this->availabilities->findByUserAndStore($userId, $storeId) as $a) {
                $existing[(int) $a['day_of_week']] = $a;
            }
        }

        // Un manager (ou admin) voit le vocabulaire de nav admin ; un employé simple
        // (aucun store géré) voit son propre vocabulaire, plus restreint.
        $isManagerView = $this->auth->isManager();
        $isOwner       = !empty($user['is_admin']);
        if ($isManagerView) {
            $defaultSecs = $isOwner ? self::NAV_OWNER_SECTIONS : self::NAV_MANAGER_SECTIONS;
            $allowedKeys = $isOwner ? self::NAV_OWNER_KEYS : self::NAV_MANAGER_KEYS;
            $bnPool      = self::BOTTOM_NAV_POOL;
            $bnDefault   = self::BOTTOM_NAV_DEFAULT;
        } else {
            $defaultSecs = self::NAV_EMPLOYEE_SECTIONS;
            $allowedKeys = self::NAV_EMPLOYEE_KEYS;
            $bnPool      = self::BOTTOM_NAV_POOL_EMPLOYEE;
            $bnDefault   = self::BOTTOM_NAV_DEFAULT_EMPLOYEE;
            // L'onglet "Rapports journaliers" n'est proposé dans le menu personnalisable
            // que si l'employé a effectivement le droit d'en créer pour au moins un store
            // (calculé globalement par DailyReportNavMiddleware).
            if (!empty($this->view->get('daily_report_staff_stores'))) {
                $allowedKeys[] = 'daily_reports';
            }
        }

        $navHidden   = $this->navPrefs->getHidden($userId);
        $navRawOrder = $this->navPrefs->getSectionOrder($userId);
        $navSecOrder = array_values(array_unique(array_merge(
            array_intersect($navRawOrder, $defaultSecs),
            $defaultSecs
        )));

        $bnSaved   = $this->navPrefs->getBottomNavItems($userId);
        $bnItems   = !empty($bnSaved) ? $bnSaved : $bnDefault;

        return Response::html($this->view->render('auth.profile', [
            'title'                => __('profile'),
            'user'                 => $user,
            'tab'                  => $tab,
            'stores'               => $stores,
            'store_id'             => $storeId,
            'existing'             => $existing,
            'ical_links'           => $icalLinks,
            'nav_hidden'           => $navHidden,
            'nav_section_order'    => $navSecOrder,
            'nav_default_sections' => $defaultSecs,
            'nav_allowed_keys'     => $allowedKeys,
            'is_owner'             => $isOwner,
            'is_manager_view'      => $isManagerView,
            'bottom_nav_pool'      => $bnPool,
            'bottom_nav_items'     => $bnItems,
        ], 'layout.app'));
    }

    /** Met à jour le profil de l'utilisateur. */
    public function updateProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId      = (int) $user['id'];
        $activeCodes = array_column($this->languages->findAllActive(), 'code');
        $default     = $this->languages->findDefault();
        $language    = $request->post('language', $default['code'] ?? 'fr');
        if (!in_array($language, $activeCodes, true)) {
            $language = $default['code'] ?? 'fr';
        }

        // Récupérer l'utilisateur complet depuis la DB pour être sûr de ne rien perdre
        $dbUser = $this->users->findById($userId);
        if ($dbUser) {
            $oldUser = $dbUser;
            // Pas de catch ici : la colonne "language" existe depuis la toute première
            // migration de création de users (elle ne peut pas manquer si la table existe),
            // et avaler l'échec masquerait un vrai problème derrière un "?success=1" trompeur
            // — l'utilisateur croirait sa préférence enregistrée alors qu'elle ne l'est pas.
            $dbUser['language']            = $language;
            $dbUser['phone']               = trim((string) $request->post('phone', ''));
            $dbUser['mobile_phone']        = trim((string) $request->post('mobile_phone', ''));
            $dbUser['postal_code']         = trim((string) $request->post('postal_code', ''));
            $dbUser['address']             = trim((string) $request->post('address', ''));
            $dbUser['bio']                 = trim((string) $request->post('bio', ''));
            $dbUser['skills']              = trim((string) $request->post('skills', ''));
            $dbUser['languages_spoken']    = trim((string) $request->post('languages_spoken', ''));
            $dbUser['hobbies']             = trim((string) $request->post('hobbies', ''));
            $dbUser['show_in_directory']   = $request->post('show_in_directory') === '1' ? 1 : 0;
            $dbUser['share_email']         = $request->post('share_email') === '1' ? 1 : 0;
            $dbUser['share_phone']         = $request->post('share_phone') === '1' ? 1 : 0;
            $dbUser['share_mobile_phone']  = $request->post('share_mobile_phone') === '1' ? 1 : 0;
            $this->users->save($dbUser);
            $_SESSION['auth_user'] = $dbUser;

            $_SESSION['locale'] = $language;
            $this->auditLogger->logUpdate($request, 'user.update_profile', 'user', $userId, $oldUser, $dbUser, [], null, $userId);
        }

        return Response::redirect($this->base() . '/profile?success=1');
    }

    /** Met à jour la photo de profil de l'utilisateur connecté. */
    public function uploadAvatar(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId = (int) $user['id'];
        $file   = $request->file('avatar');
        if ($file === null || !is_uploaded_file($file['tmp_name'])) {
            return Response::redirect($this->base() . '/profile?tab=info&error=avatar_invalid');
        }

        $dbUser = $this->users->findById($userId);
        if ($dbUser === null) {
            return Response::redirect($this->base() . '/profile?tab=info&error=error_generic');
        }

        $avatarDir = BASE_PATH . '/storage/uploads/avatars/';
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0775, true);
        }

        $compressed = $this->avatarOptimizer->optimize($file['tmp_name'], $avatarDir . 'user_' . $userId);
        if ($compressed === null) {
            return Response::redirect($this->base() . '/profile?tab=info&error=avatar_invalid');
        }

        // Un ancien avatar dans une extension différente (ex. .png → .jpg après
        // recompression) resterait orphelin sur le disque sans ce nettoyage.
        $oldFilename = $dbUser['avatar_path'] ?? null;
        if ($oldFilename !== null) {
            $oldPath = $avatarDir . basename((string) $oldFilename);
            if (is_file($oldPath) && $oldPath !== $compressed['path']) {
                @unlink($oldPath);
            }
        }

        $oldUser = $dbUser;
        $dbUser['avatar_path'] = basename($compressed['path']);
        $this->users->save($dbUser);
        $_SESSION['auth_user'] = $dbUser;

        $this->auditLogger->logUpdate($request, 'user.avatar_updated', 'user', $userId, $oldUser, $dbUser, [], null, $userId);

        return Response::redirect($this->base() . '/profile?tab=info&success=avatar_saved');
    }

    /** Supprime la photo de profil de l'utilisateur connecté. */
    public function removeAvatar(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId = (int) $user['id'];
        $dbUser = $this->users->findById($userId);
        if ($dbUser === null || empty($dbUser['avatar_path'])) {
            return Response::redirect($this->base() . '/profile?tab=info');
        }

        $path = BASE_PATH . '/storage/uploads/avatars/' . basename((string) $dbUser['avatar_path']);
        if (is_file($path)) {
            @unlink($path);
        }

        $oldUser = $dbUser;
        $dbUser['avatar_path'] = null;
        $this->users->save($dbUser);
        $_SESSION['auth_user'] = $dbUser;

        $this->auditLogger->logUpdate($request, 'user.avatar_removed', 'user', $userId, $oldUser, $dbUser, [], null, $userId);

        return Response::redirect($this->base() . '/profile?tab=info&success=avatar_removed');
    }

    /**
     * Sert la photo de profil d'un utilisateur (visible par tout utilisateur
     * connecté — même logique d'ouverture que le nom/la couleur d'identification,
     * déjà visibles à l'échelle de l'organisation dans les plannings partagés).
     */
    public function avatar(Request $request): Response
    {
        $targetId = (int) $request->param('user_id');
        $target   = $this->users->findById($targetId);
        $filename = $target['avatar_path'] ?? null;
        if ($target === null || $filename === null) {
            throw new NotFoundException(__('error_file_not_found'));
        }

        $path = BASE_PATH . '/storage/uploads/avatars/' . basename((string) $filename);
        if (!is_file($path)) {
            throw new NotFoundException(__('error_file_not_found'));
        }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::AVATAR_MIME_TYPES[$ext] ?? 'application/octet-stream';

        return Response::fileStream($path, $mime, basename($path))
            ->withHeader('Content-Disposition', 'inline; filename="' . basename($path) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, max-age=3600');
    }

    /** Change le mot de passe de l'utilisateur. */
    public function saveProfilePassword(Request $request): Response
    {
        $user    = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId   = (int) $user['id'];
        $current  = $request->post('current_password', '');
        $newPass  = $request->post('new_password', '');
        $confirm  = $request->post('confirm_password', '');

        if ($newPass === '' || $newPass !== $confirm) {
            return Response::redirect($this->base() . '/profile?tab=info&error=password_mismatch');
        }

        if (mb_strlen($newPass) < 4) {
            return Response::redirect($this->base() . '/profile?tab=info&error=password_too_short');
        }

        $dbUser = $this->users->findById($userId);
        if ($dbUser === null) {
            return Response::redirect($this->base() . '/profile?tab=info&error=error_generic');
        }

        $storedHash = $dbUser['password_hash'] ?? '';
        if ($storedHash !== '' && !password_verify($current, $storedHash)) {
            return Response::redirect($this->base() . '/profile?tab=info&error=current_password_wrong');
        }

        $oldUser = $dbUser;
        $dbUser['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
        try {
            $this->users->save($dbUser);
            $this->auditLogger->logUpdate($request, 'user.change_password', 'user', $userId, $oldUser, $dbUser, [], null, $userId);
        } catch (\Throwable) {
            return Response::redirect($this->base() . '/profile?tab=info&error=error_generic');
        }

        return Response::redirect($this->base() . '/profile?tab=info&success=password_changed');
    }

    /** Exporte les données personnelles de l'utilisateur (RGPD portabilité). */
    public function exportData(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId = (int) $user['id'];

        $data = [
            'personal_info' => [
                'first_name'        => $user['first_name'] ?? null,
                'last_name'         => $user['last_name'] ?? null,
                'email'             => $user['email'] ?? null,
                'phone'             => $user['phone'] ?? null,
                'mobile_phone'      => $user['mobile_phone'] ?? null,
                'postal_code'       => $user['postal_code'] ?? null,
                'address'           => $user['address'] ?? null,
                'language'          => $user['language'] ?? null,
                'bio'               => $user['bio'] ?? null,
                'skills'            => $user['skills'] ?? null,
                'languages_spoken'  => $user['languages_spoken'] ?? null,
                'hobbies'           => $user['hobbies'] ?? null,
            ],
            'memberships' => $this->storeUsers->findByUser($userId),
            'availabilities' => $this->availabilities->findByUser($userId),
        ];

        $this->auditLogger->log($request, 'user.data_export', 'user', $userId, [], null, $userId);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return new Response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="kintai-data-' . $userId . '.json"',
        ]);
    }

    /** Supprime le compte de l'utilisateur connecté (auto-service RGPD). */
    public function deleteAccount(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId   = (int) $user['id'];
        $password = (string) $request->post('password', '');

        $storedHash = $user['password_hash'] ?? '';
        if ($storedHash !== '' && !password_verify($password, $storedHash)) {
            return Response::redirect($this->base() . '/profile?tab=data&error=current_password_wrong');
        }

        // La photo de profil est un fichier sur disque, pas juste une colonne :
        // sans ce nettoyage elle survivrait à l'anonymisation du compte.
        $existing = $this->users->findById($userId);
        if (!empty($existing['avatar_path'])) {
            @unlink(BASE_PATH . '/storage/uploads/avatars/' . basename((string) $existing['avatar_path']));
        }

        // Anonymize instead of hard delete: keep related records for data integrity
        $this->users->save([
            'id'                 => $userId,
            'first_name'         => '[Supprimé]',
            'last_name'          => '',
            'display_name'       => 'Utilisateur supprimé',
            'email'              => 'deleted-' . $userId . '@kintai.local',
            'employee_code'      => null,
            'password_hash'      => '',
            'phone'              => null,
            'language'           => null,
            'is_active'          => 0,
            'mobile_phone'       => null,
            'postal_code'        => null,
            'address'            => null,
            'avatar_path'        => null,
            'bio'                => null,
            'skills'             => null,
            'languages_spoken'   => null,
            'hobbies'            => null,
            'show_in_directory'  => 0,
            'share_email'        => 0,
            'share_phone'        => 0,
            'share_mobile_phone' => 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'user.self_deleted', 'user', $userId, [], null, $userId);

        $this->auth->logout();
        return Response::redirect($this->base() . '/login?deleted=1');
    }

    /** Déconnecte l'utilisateur et redirige vers /login. */
    public function logout(Request $request): Response
    {
        // Capturer l'utilisateur avant la déconnexion
        $authUser = $this->auth->user();
        $userId   = $authUser ? (int) ($authUser['id'] ?? 0) : null;

        $this->auth->logout();

        $this->auditLogger->log($request, 'auth.logout', 'user', $userId, [], null, $userId);
        return Response::redirect($this->base() . '/login');
    }


}
