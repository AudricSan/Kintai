<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Feedback as EloquentFeedback;

final class DatabaseFeedbackRepository implements FeedbackRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentFeedback::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentFeedback::where('store_id', $storeId)->get()->toArray();
    }

    public function findAll(): array
    {
        return EloquentFeedback::all()->toArray();
    }

    public function findByShift(int $shiftId): ?array
    {
        $record = EloquentFeedback::where('shift_id', $shiftId)->first();
        return $record ? $record->toArray() : null;
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentFeedback::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentFeedback::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentFeedback::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
