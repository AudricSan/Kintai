<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\HiringReport as EloquentHiringReport;

final class DatabaseHiringReportRepository implements HiringReportRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $r = EloquentHiringReport::find($id);
        return $r ? $r->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentHiringReport::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $r = EloquentHiringReport::findOrFail($data['id']);
            $r->fill($data);
            $r->save();
        } else {
            $r = EloquentHiringReport::create($data);
        }
        return $r->toArray();
    }

    public function delete(int $id): int
    {
        $r = EloquentHiringReport::find($id);
        if ($r) {
            return $r->delete() ? 1 : 0;
        }
        return 0;
    }
}
