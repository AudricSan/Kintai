<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\ShiftType as EloquentShiftType;

final class DatabaseShiftTypeRepository implements ShiftTypeRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $type = EloquentShiftType::find($id);
        return $type ? $type->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentShiftType::where('store_id', $storeId)->get()->toArray();
    }

    public function findActive(int $storeId): array
    {
        return EloquentShiftType::where('store_id', $storeId)
            ->where('is_active', 1)
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentShiftType::all()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $type = EloquentShiftType::findOrFail($data['id']);
            $type->fill($data);
            $type->save();
        } else {
            $type = EloquentShiftType::create($data);
        }
        return $type->toArray();
    }

    public function delete(int $id): int
    {
        $type = EloquentShiftType::find($id);
        if ($type) {
            return $type->delete() ? 1 : 0;
        }
        return 0;
    }
}
