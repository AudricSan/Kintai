<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\ResignationReport as EloquentResignationReport;

final class DatabaseResignationReportRepository implements ResignationReportRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $r = EloquentResignationReport::find($id);
        return $r ? $r->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentResignationReport::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function findAll(?array $storeIds = null): array
    {
        $q = EloquentResignationReport::orderByDesc('created_at');
        if ($storeIds !== null && $storeIds !== []) {
            $q->whereIn('store_id', $storeIds);
        }
        return $q->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $r = EloquentResignationReport::findOrFail($data['id']);
            $r->fill($data);
            $r->save();
        } else {
            $r = EloquentResignationReport::create($data);
        }
        return $r->toArray();
    }

    public function delete(int $id): int
    {
        $r = EloquentResignationReport::find($id);
        if ($r) {
            return $r->delete() ? 1 : 0;
        }
        return 0;
    }
}
