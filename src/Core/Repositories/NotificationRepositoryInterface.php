<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

/**
 * Champs attendus en lecture/écriture : id, user_id, type, body, reference_id
 * (nullable), is_read (dérivé, 0|1), created_at, read_at. L'implémentation DB
 * traduit ce modèle vers/depuis le schéma réel de la table `notifications`
 * (data JSON + read_at) — voir DatabaseNotificationRepository.
 */
interface NotificationRepositoryInterface
{
    public function findById(int $id): ?array;

    /** @return array[] Les N plus récentes notifications de l'utilisateur (toutes, lues ou non). */
    public function findByUser(int $userId, int $limit = 20): array;

    /** @return array[] Notifications non lues créées APRÈS $since (format 'Y-m-d H:i:s'). */
    public function findUnreadSince(int $userId, string $since): array;

    public function countUnread(int $userId): int;

    public function save(array $data): array;

    public function markRead(int $id, int $userId): void;

    public function markAllRead(int $userId): void;

    public function delete(int $id): int;
}
