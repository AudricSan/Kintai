<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\PasswordResetService;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

final class PasswordResetController
{
    use HasBaseUrl;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly PasswordResetService $passwordReset,
    ) {}

    /** GET /forgot-password */
    public function showForgotForm(Request $request): Response
    {
        return Response::html($this->view->render('auth.forgot-password', [
            'title'   => 'Mot de passe oublié',
            'sent'    => false,
            'error'   => false,
        ], 'layout.guest'));
    }

    /** POST /forgot-password */
    public function sendLink(Request $request): Response
    {
        $email = trim((string) $request->post('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::html($this->view->render('auth.forgot-password', [
                'title' => 'Mot de passe oublié',
                'sent'  => false,
                'error' => true,
            ], 'layout.guest'));
        }

        // Anti-énumération : on envoie toujours la page "succès"
        $this->passwordReset->sendResetLink($email, $this->base());

        return Response::html($this->view->render('auth.forgot-password', [
            'title' => 'Mot de passe oublié',
            'sent'  => true,
            'error' => false,
        ], 'layout.guest'));
    }

    /** GET /reset-password/{token} */
    public function showResetForm(Request $request): Response
    {
        $token  = (string) $request->param('token', '');
        $record = $this->passwordReset->findValidToken($token);

        return Response::html($this->view->render('auth.reset-password', [
            'title'   => 'Nouveau mot de passe',
            'token'   => $token,
            'valid'   => $record !== null,
            'success' => false,
            'error'   => null,
        ], 'layout.guest'));
    }

    /** POST /reset-password/{token} */
    public function reset(Request $request): Response
    {
        $token    = (string) $request->param('token', '');
        $password = (string) $request->post('password', '');
        $confirm  = (string) $request->post('password_confirmation', '');

        if (strlen($password) < 8) {
            return $this->resetView($token, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        if ($password !== $confirm) {
            return $this->resetView($token, 'Les mots de passe ne correspondent pas.');
        }

        $ok = $this->passwordReset->reset($token, $password);

        if (!$ok) {
            return $this->resetView($token, 'Ce lien est invalide ou a expiré.');
        }

        return Response::html($this->view->render('auth.reset-password', [
            'title'   => 'Nouveau mot de passe',
            'token'   => $token,
            'valid'   => true,
            'success' => true,
            'error'   => null,
            'login_url' => $this->base() . '/login',
        ], 'layout.guest'));
    }

    private function resetView(string $token, string $error): Response
    {
        $record = $this->passwordReset->findValidToken($token);

        return Response::html($this->view->render('auth.reset-password', [
            'title'   => 'Nouveau mot de passe',
            'token'   => $token,
            'valid'   => $record !== null,
            'success' => false,
            'error'   => $error,
        ], 'layout.guest'));
    }


}
