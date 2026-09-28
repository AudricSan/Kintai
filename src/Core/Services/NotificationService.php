<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * La locale de chaque notification est résolue par destinataire : langue de
 * l'utilisateur (`users.language`) sinon 'fr' par défaut — voir DailyReportMailService
 * pour le même pattern côté e-mail.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repo,
        private readonly PushNotificationService $push,
        private readonly TranslationService $translations,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param string|null $link Chemin relatif de l'app (ex. '/employee/shifts/day?start=2026-08-03')
     *                          vers lequel la notification renvoie au clic — voir NotificationController::open().
     *                          null si cette notification n'a pas de destination pertinente (comportement
     *                          par défaut inchangé : simple redirection vers /notifications).
     */
    public function notify(int $userId, string $type, string $bodyKey, array $replace = [], ?int $referenceId = null, ?string $link = null): void
    {
        $locale   = $this->resolveLocale($userId);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($locale);

        try {
            $title = $this->translations->translate('notif_' . $type);
            $body  = $this->translations->translate($bodyKey, $replace);
        } finally {
            $this->translations->setLocale($previous);
        }

        $saved = $this->repo->save([
            'user_id'      => $userId,
            'type'         => $type,
            'reference_id' => $referenceId,
            'body'         => $body,
            'link'         => $link,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Best-effort : une notification en base ne doit jamais échouer parce que
        // l'envoi push (appel réseau externe vers FCM) a un problème. PushNotificationService
        // avale déjà ses propres erreurs (voir sa docblock) ; ceinture-bretelles ici.
        try {
            $this->push->sendToUser($userId, $title === 'notif_' . $type ? 'Kintai' : $title, $body, [
                'type'              => $type,
                'reference_id'      => (string) ($referenceId ?? ''),
                'link'              => $link ?? '',
                // Permet au clic sur la notification système (service worker) de réutiliser
                // le même mécanisme "marquer lu + rediriger" que le dropdown web — voir
                // NotificationController::open() et le notificationclick de sw.js.tpl.
                'notification_id'   => (string) ($saved['id'] ?? ''),
            ]);
        } catch (\Throwable) {
        }
    }

    /** @param int[] $userIds */
    public function notifyMany(array $userIds, string $type, string $bodyKey, array $replace = [], ?int $referenceId = null, ?string $link = null): void
    {
        foreach (array_unique($userIds) as $uid) {
            $this->notify((int) $uid, $type, $bodyKey, $replace, $referenceId, $link);
        }
    }

    private function resolveLocale(int $userId): string
    {
        $user = $this->users->findById($userId);
        $raw  = $user['language'] ?? 'fr';
        if ($raw === null || $raw === '') {
            $raw = 'fr';
        }
        $lang = strtolower(substr((string) $raw, 0, 2));
        return match ($lang) {
            'ja'    => 'ja',
            'en'    => 'en',
            default => 'fr',
        };
    }
}
