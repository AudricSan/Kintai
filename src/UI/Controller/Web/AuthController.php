<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\UI\ViewRenderer;

final class AuthController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AuthService $auth,
        private readonly AuditLogger $auditLogger,
        private readonly UserRepositoryInterface $users,
    ) {}

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
        $mode = $request->post('login_mode', 'email');

        if ($mode === 'code') {
            // Connexion par code employé + code magasin + mot de passe
            $employeeCode = trim($request->post('employee_code', ''));
            $storeCode    = trim($request->post('store_code', ''));
            $password     = $request->post('password', '0000');
            $ok = $this->auth->attemptByCode($employeeCode, $storeCode, $password);
        } else {
            // Connexion classique email + mot de passe
            $email    = trim($request->post('email', ''));
            $password = $password = $request->post('password', '');
            $ok = $this->auth->attempt($email, $password);
        }

        if ($ok) {
            $authUser = $this->auth->user();
            $userId   = $authUser ? (int) ($authUser['id'] ?? 0) : null;
            $this->auditLogger->log($request, 'auth.login', 'user', $userId, ['mode' => $mode], null, $userId);

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

    /** Bascule entre la vue admin et la vue employé (pour les managers/admins). */
    public function switchView(Request $request): Response
    {
        $current = $_SESSION['view_mode'] ?? 'admin';
        $next    = $current === 'admin' ? 'employee' : 'admin';
        $_SESSION['view_mode'] = $next;

        $redirectTo = $next === 'employee'
            ? $this->base() . '/employee'
            : $this->base() . '/admin/shifts/timeline';

        return Response::redirect($redirectTo);
    }

    /** Change la langue de l'utilisateur (session + BD si connecté). */
    public function switchLanguage(Request $request): Response
    {
        $locale = $request->param('locale');
        if (!in_array($locale, ['en', 'fr', 'ja'], true)) {
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

        return Response::html($this->view->render('auth.profile', [
            'title' => __('profile'),
            'user'  => $user,
        ], 'layout.app'));
    }

    /** Met à jour le profil de l'utilisateur. */
    public function updateProfile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId   = (int) $user['id'];
        $language = $request->post('language', 'fr');
        if (!in_array($language, ['en', 'fr', 'ja'], true)) {
            $language = 'fr';
        }

        // Récupérer l'utilisateur complet depuis la DB pour être sûr de ne rien perdre
        $dbUser = $this->users->findById($userId);
        if ($dbUser) {
            try {
                $dbUser['language'] = $language;
                $this->users->save($dbUser);
                $_SESSION['auth_user'] = $dbUser;
            } catch (\Throwable) {
                // Colonne language absente (migration non exécutée) — session suffit
            }

            $_SESSION['locale'] = $language;
            $this->auditLogger->log($request, 'user.update_profile', 'user', $userId, ['language' => $language], null, $userId);
        }

        return Response::redirect($this->base() . '/profile?success=1');
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

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }
}
