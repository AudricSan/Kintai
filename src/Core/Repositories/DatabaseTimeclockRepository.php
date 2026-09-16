<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Timeclock as EloquentTimeclock;

final class DatabaseTimeclockRepository implements TimeclockRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentTimeclock::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByUser(int $userId): array
    {
        return EloquentTimeclock::where('user_id', $userId)->get()->toArray();
    }

    public function findByStore(int $storeId): array
    {
        return EloquentTimeclock::where('store_id', $storeId)->get()->toArray();
    }

    public function findByUserAndDate(int $userId, string $date): array
    {
        return EloquentTimeclock::where('user_id', $userId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findByStoreAndDate(int $storeId, string $date): array
    {
        return EloquentTimeclock::where('store_id', $storeId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findActiveByUser(int $userId): ?array
    {
        $record = EloquentTimeclock::where('user_id', $userId)
            ->whereNull('clock_out_time')
            ->first();
        return $record ? $record->toArray() : null;
    }

    public function findAll(): array
    {
        return EloquentTimeclock::all()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentTimeclock::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentTimeclock::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentTimeclock::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
