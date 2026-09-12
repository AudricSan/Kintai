<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\NotificationRepositoryInterface;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repo,
        private readonly PushNotificationService $push,
    ) {}

    public function notify(int $userId, string $type, string $body, ?int $referenceId = null): void
    {
        $this->repo->save([
            'user_id'      => $userId,
            'type'         => $type,
            'reference_id' => $referenceId,
            'body'         => $body,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Best-effort : une notification en base ne doit jamais échouer parce que
        // l'envoi push (appel réseau externe vers FCM) a un problème. PushNotificationService
        // avale déjà ses propres erreurs (voir sa docblock) ; ceinture-bretelles ici.
        try {
            $title = __('notif_' . $type);
            $this->push->sendToUser($userId, $title === 'notif_' . $type ? 'Kintai' : $title, $body, [
                'type'         => $type,
                'reference_id' => (string) ($referenceId ?? ''),
            ]);
        } catch (\Throwable) {
        }
    }

    /** @param int[] $userIds */
    public function notifyMany(array $userIds, string $type, string $body, ?int $referenceId = null): void
    {
        foreach (array_unique($userIds) as $uid) {
            $this->notify((int) $uid, $type, $body, $referenceId);
        }
    }
}
