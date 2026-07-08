<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Shift as EloquentShift;

final class DatabaseShiftRepository implements ShiftRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $shift = EloquentShift::find($id);
        return $shift ? $shift->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentShift::where('store_id', $storeId)->get()->toArray();
    }

    public function findByUser(int $userId): array
    {
        return EloquentShift::where('user_id', $userId)->get()->toArray();
    }

    public function findByDate(int $storeId, string $date): array
    {
        return EloquentShift::where('store_id', $storeId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findByUserAndDate(int $userId, int $storeId, string $date): array
    {
        return EloquentShift::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentShift::all()->toArray();
    }

    public function findAllByDate(string $date): array
    {
        return EloquentShift::where('shift_date', $date)->get()->toArray();
    }

    public function findOpen(?int $storeId = null): array
    {
        $query = EloquentShift::where('is_open', 1);
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }
        return $query->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $shift = EloquentShift::findOrFail((int) $data['id']);
            $data['ical_sequence'] = ((int) ($shift->ical_sequence ?? 0)) + 1;
            if (!array_key_exists('updated_at', $data)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $shift->fill($data);
            $shift->save();
        } else {
            $data['ical_sequence'] = 0;
            $shift = EloquentShift::create($data);
        }
        return $shift->toArray();
    }

    public function delete(int $id): int
    {
        $shift = EloquentShift::find($id);
        if ($shift) {
            return $shift->delete() ? 1 : 0;
        }
        return 0;
    }
}
