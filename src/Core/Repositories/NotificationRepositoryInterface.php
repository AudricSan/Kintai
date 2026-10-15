<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

/**
 * Champs en lecture : id, user_id, type, body_key, params (array), body (instantané de repli), link,
 * reference_type, reference_id, read_at, is_read (dérivé de read_at, 0|1), created_at. Le texte à afficher se
 * calcule avec notification_body() (traduction de body_key dans la langue courante, sinon body).
 */
interface NotificationRepositoryInterface
{
    public function findById(int $id): ?array;

    /** @return array[] Notifications de l'utilisateur (lues ou non), les plus récentes d'abord. */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array;

    public function countByUser(int $userId): int;

    /**
     * Curseur du polling : notifications (lues ou non) d'id strictement supérieur à $afterId, par id croissant.
     * L'id est attribué par la base : aucune dépendance à l'horloge du navigateur ni à la précision à la seconde.
     *
     * @return array[]
     */
    public function findAfterId(int $userId, int $afterId, int $limit = 50): array;

    public function countUnread(int $userId): int;

    public function save(array $data): array;

    public function markRead(int $id, int $userId): void;

    public function markAllRead(int $userId): void;

    public function delete(int $id): int;

    /** Supprime toutes les notifications (lues ou non) de l'utilisateur. */
    public function deleteAllForUser(int $userId): void;
}
