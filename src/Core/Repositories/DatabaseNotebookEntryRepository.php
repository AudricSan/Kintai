<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\NotebookEntry as EloquentNotebookEntry;

final class DatabaseNotebookEntryRepository implements NotebookEntryRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentNotebookEntry::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentNotebookEntry::where('store_id', $storeId)->get()->toArray();
    }

    public function findAll(): array
    {
        return EloquentNotebookEntry::all()->toArray();
    }

    public function findVisibleForStores(array $storeIds): array
    {
        return EloquentNotebookEntry::where(function ($query) use ($storeIds) {
            $query->whereNull('store_id');
            if ($storeIds !== []) {
                $query->orWhereIn('store_id', $storeIds);
            }
        })->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentNotebookEntry::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentNotebookEntry::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentNotebookEntry::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
