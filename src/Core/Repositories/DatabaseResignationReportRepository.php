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

    public function findAll(?array $storeIds = null, array $filters = []): array
    {
        $q = EloquentResignationReport::orderByDesc('created_at');
        if ($storeIds !== null && $storeIds !== []) {
            $q->whereIn('store_id', $storeIds);
        }
        if (!empty($filters['year'])) {
            $q->whereYear('resignation_date', (int) $filters['year']);
        }
        if (!empty($filters['month'])) {
            $q->whereMonth('resignation_date', (int) $filters['month']);
        }
        if (!empty($filters['person_in_charge'])) {
            $q->where('person_in_charge', 'LIKE', '%' . $filters['person_in_charge'] . '%');
        }
        if (!empty($filters['store_id'])) {
            $q->where('store_id', (int) $filters['store_id']);
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
