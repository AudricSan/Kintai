<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Notification as EloquentNotification;

/**
 * La table `notifications` n'a que `id, user_id, type, data, read_at,
 * created_at` (voir la migration) : pas de colonnes `body`/`reference_id`/
 * `is_read`. Ce repository est le seul point de traduction entre ce schéma
 * réel et le modèle de lecture (`body`/`reference_id`/`is_read`) attendu par
 * NotificationService et tous les appelants (vues, endpoint de polling, API) —
 * `body`/`reference_id` sont encodés en JSON dans `data`, `is_read` est
 * dérivé de `read_at`. Rien d'autre dans l'app n'a besoin de connaître cette
 * différence.
 */
final class DatabaseNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentNotification::find($id);
        return $record ? $this->fromStorage($record->toArray()) : null;
    }

    public function findByUser(int $userId, int $limit = 20): array
    {
        return array_map(
            fn(array $row) => $this->fromStorage($row),
            EloquentNotification::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->toArray()
        );
    }

    public function findUnreadSince(int $userId, string $since): array
    {
        return array_map(
            fn(array $row) => $this->fromStorage($row),
            EloquentNotification::where('user_id', $userId)
                ->whereNull('read_at')
                ->where('created_at', '>', $since)
                ->get()
                ->toArray()
        );
    }

    public function countUnread(int $userId): int
    {
        return EloquentNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function save(array $data): array
    {
        $payload = $this->toStorage($data);
        if (!empty($payload['id'])) {
            $record = EloquentNotification::findOrFail((int) $payload['id']);
            $record->fill($payload);
            $record->save();
        } else {
            $record = EloquentNotification::create($payload);
        }
        return $this->fromStorage($record->toArray());
    }

    public function markRead(int $id, int $userId): void
    {
        EloquentNotification::where('id', $id)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(int $userId): void
    {
        EloquentNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $id): int
    {
        $record = EloquentNotification::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }

    /** Traduit le modèle de lecture (body/reference_id/is_read) vers les vraies colonnes (data/read_at). */
    private function toStorage(array $data): array
    {
        $storage = $data;
        if (array_key_exists('body', $storage) || array_key_exists('reference_id', $storage)) {
            $storage['data'] = json_encode([
                'body'         => $storage['body'] ?? '',
                'reference_id' => $storage['reference_id'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            unset($storage['body'], $storage['reference_id']);
        }
        // is_read n'est jamais stocké tel quel : uniquement dérivé de read_at à la lecture.
        // Une valeur explicite à la création (notify() envoie toujours is_read=0) doit
        // rester sans effet — read_at, absent du payload, garde sa valeur par défaut NULL.
        unset($storage['is_read']);
        return $storage;
    }

    /** Traduit les vraies colonnes (data/read_at) vers le modèle de lecture (body/reference_id/is_read). */
    private function fromStorage(array $row): array
    {
        $decoded = json_decode((string) ($row['data'] ?? ''), true);
        $decoded = is_array($decoded) ? $decoded : [];

        return array_merge($row, [
            'body'         => $decoded['body'] ?? '',
            'reference_id' => $decoded['reference_id'] ?? null,
            'is_read'      => ($row['read_at'] ?? null) !== null ? 1 : 0,
        ]);
    }
}
