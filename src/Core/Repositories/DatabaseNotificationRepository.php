<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Notification as EloquentNotification;

final class DatabaseNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentNotification::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByUser(int $userId, int $limit = 20): array
    {
        return EloquentNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->toArray();
    }

    public function findUnreadSince(int $userId, string $since): array
    {
        return EloquentNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->where('created_at', '>', $since)
            ->get()
            ->toArray();
    }

    public function countUnread(int $userId): int
    {
        return EloquentNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentNotification::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentNotification::create($data);
        }
        return $record->toArray();
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
}
