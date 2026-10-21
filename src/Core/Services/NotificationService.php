<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Le push et l'instantané body sont traduits dans la langue du destinataire (`users.language`, sinon celle de
 * l'instance) — même principe que l'e-mail des rapports journaliers (bundle daily-report). L'affichage dans
 * l'application, lui, retraduit body_key dans la langue courante (notification_body()).
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repo,
        private readonly PushNotificationService $push,
        private readonly TranslationService $translations,
        private readonly UserRepositoryInterface $users,
        // Optionnel : sans registre, aucune notification n'est rattachée à un type d'objet (pas de « lu » automatique).
        private readonly ?NotificationTypeRegistry $types = null,
        // Optionnel : sans préférences, le push part toujours (comportement d'avant les préférences).
        private readonly ?NotificationPreferenceService $preferences = null,
    ) {}

    /**
     * @param string|null $link Chemin relatif de l'app (ex. '/employee/shifts/day?start=2026-08-03')
     *                          vers lequel la notification renvoie au clic — voir NotificationController::open().
     *                          null si cette notification n'a pas de destination pertinente (comportement
     *                          par défaut inchangé : simple redirection vers /notifications).
     */
    public function notify(int $userId, string $type, string $bodyKey, array $replace = [], ?int $referenceId = null, ?string $link = null): void
    {
        // Paramètres réduits à des chaînes : ils sont stockés en JSON pour retraduire plus tard, avec le même
        // résultat qu'aujourd'hui.
        $params = array_map(fn($v) => is_scalar($v) || $v === null ? (string) $v : '', $replace);

        $locale   = $this->resolveLocale($userId);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($locale);

        try {
            $title = $this->translations->translate('notif_' . $type);
            $body  = $this->translations->translate($bodyKey, $params);
        } finally {
            $this->translations->setLocale($previous);
        }

        // body_key + params : le texte est retraduit à l'affichage dans la langue courante du lecteur (voir
        // notification_body()). body n'est qu'un instantané de repli, pour une clé devenue introuvable (ex. bundle
        // désactivé depuis) : on garde au moins le texte dans la langue du destinataire au moment de l'envoi.
        $saved = $this->repo->save([
            'user_id'      => $userId,
            'type'         => $type,
            'body_key'     => $bodyKey,
            'params'       => $params,
            'reference_type' => $referenceId !== null ? $this->types?->get($type)?->referenceType : null,
            'reference_id' => $referenceId,
            'body'         => $body,
            'link'         => $link,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Best-effort : une notification en base ne doit jamais échouer parce que
        // l'envoi push (appel réseau externe vers FCM) a un problème. PushNotificationService
        // avale déjà ses propres erreurs (voir sa docblock) ; ceinture-bretelles ici.
        // La cloche (ci-dessus) est toujours alimentée ; le push respecte la préférence du type et les heures
        // silencieuses de l'utilisateur (un push retenu n'est pas rejoué : la notification reste dans la cloche).
        try {
            if (!$this->channelAllowed($userId, $type, 'push')) {
                return;
            }
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
        // Préférences de tous les destinataires en une requête.
        $this->preferences?->preload($userIds);
        foreach (array_unique($userIds) as $uid) {
            $this->notify((int) $uid, $type, $bodyKey, $replace, $referenceId, $link);
        }
    }

    /**
     * « Lu » automatique : l'utilisateur vient de voir l'objet (shift, congé, échange…) désigné par ses notifications
     * non lues. Ne lève jamais : une panne ici ne doit pas empêcher d'afficher la page.
     *
     * @param int|list<int> $referenceId identifiant(s) de l'objet, tel que passé à notify()
     */
    public function markReadFor(int $userId, string $referenceType, int|array $referenceId): void
    {
        try {
            $this->repo->markReadByReference($userId, $referenceType, array_values(array_map('intval', (array) $referenceId)));
        } catch (\Throwable $e) {
            Log::warning('notification_mark_read_failed', ['user_id' => $userId, 'reference_type' => $referenceType, 'error' => $e->getMessage()]);
        }
    }

    /** Canal externe autorisé : préférence activée pour ce type, hors heures silencieuses. */
    private function channelAllowed(int $userId, string $type, string $channel): bool
    {
        if ($this->preferences === null) {
            return true;
        }
        return $this->preferences->isEnabled($userId, $type, $channel) && !$this->preferences->isQuietNow($userId);
    }

    private function resolveLocale(int $userId): string
    {
        // Langue du profil du destinataire, sinon celle de l'instance.
        return user_locale($this->users->findById($userId));
    }
}
