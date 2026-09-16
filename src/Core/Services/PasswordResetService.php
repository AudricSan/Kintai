<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Mail\MailerService;
use kintai\Core\Repositories\PasswordResetRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

final class PasswordResetService
{
    private const TTL_HOURS = 1;

    public function __construct(
        private readonly PasswordResetRepositoryInterface $resets,
        private readonly UserRepositoryInterface $users,
        private readonly MailerService $mailer,
    ) {}

    /**
     * Génère un token et envoie le lien par mail.
     * Retourne toujours true même si l'email n'existe pas (anti-énumération).
     */
    public function sendResetLink(string $email, string $baseUrl): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || empty($user['is_active']) || !empty($user['deleted_at'])) {
            return true;
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600);

        $this->resets->create($email, hash_token($token), $expiresAt);

        $name = trim(($user['display_name'] ?? '') !== ''
            ? $user['display_name']
            : ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        $link = rtrim($baseUrl, '/') . '/reset-password/' . $token;

        return $this->mailer->send(
            [$email],
            '[Kintai] Réinitialisation de votre mot de passe',
            $this->buildMailBody($name, $link),
        );
    }

    /**
     * Vérifie qu'un token est valide et non expiré.
     * Retourne l'enregistrement ou null si invalide.
     */
    public function findValidToken(string $token): ?array
    {
        $record = $this->resets->findByToken(hash_token($token));

        if ($record === null) {
            return null;
        }

        if (($record['expires_at'] ?? '') < date('Y-m-d H:i:s')) {
            return null;
        }

        return $record;
    }

    /**
     * Réinitialise le mot de passe et invalide le token.
     */
    public function reset(string $token, string $newPassword): bool
    {
        $record = $this->findValidToken($token);

        if ($record === null) {
            return false;
        }

        $user = $this->users->findByEmail($record['email']);

        if ($user === null || empty($user['is_active'])) {
            return false;
        }

        $this->users->save(array_merge($user, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]));

        $this->resets->deleteByEmail($record['email']);

        return true;
    }

    private function buildMailBody(string $name, string $link): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES);
        $escapedLink = htmlspecialchars($link, ENT_QUOTES);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head><meta charset="UTF-8"></head>
        <body style="font-family:sans-serif;background:#f8fafc;margin:0;padding:32px 0;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <table width="520" cellpadding="0" cellspacing="0"
                     style="background:#fff;border-radius:8px;border:1px solid #e2e8f0;padding:40px;">
                <tr><td>
                  <h1 style="font-size:22px;color:#1e293b;margin:0 0 8px;">Kintai</h1>
                  <h2 style="font-size:16px;color:#475569;font-weight:normal;margin:0 0 24px;">
                    Réinitialisation de mot de passe
                  </h2>
                  <p style="color:#334155;line-height:1.6;">Bonjour {$escapedName},</p>
                  <p style="color:#334155;line-height:1.6;">
                    Vous avez demandé la réinitialisation de votre mot de passe.
                    Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
                  </p>
                  <p style="text-align:center;margin:32px 0;">
                    <a href="{$escapedLink}"
                       style="background:#2563eb;color:#fff;text-decoration:none;
                              padding:12px 28px;border-radius:6px;font-size:15px;font-weight:600;">
                      Réinitialiser mon mot de passe
                    </a>
                  </p>
                  <p style="color:#64748b;font-size:13px;line-height:1.6;">
                    Ce lien expire dans 1 heure.<br>
                    Si vous n'avez pas fait cette demande, ignorez simplement ce message.
                  </p>
                  <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
                  <p style="color:#94a3b8;font-size:12px;">Kintai — Shift Management</p>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }
}
