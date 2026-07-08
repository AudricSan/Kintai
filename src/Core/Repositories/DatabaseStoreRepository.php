<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Store as EloquentStore;
use kintai\Domain\Eloquent\StoreFeature;
use kintai\Domain\Eloquent\StoreImportSetting;
use kintai\Domain\Eloquent\StoreDeductionSetting;

final class DatabaseStoreRepository implements StoreRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $store = EloquentStore::find($id);
        return $store ? $store->toArray() : null;
    }

    public function findByCode(string $code): ?array
    {
        $store = EloquentStore::where('code', $code)->first();
        return $store ? $store->toArray() : null;
    }

    public function findAll(): array
    {
        return EloquentStore::all()->toArray();
    }

    public function findActive(): array
    {
        return EloquentStore::where('is_active', 1)
            ->whereNull('deleted_at')
            ->get()
            ->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $store = EloquentStore::findOrFail($data['id']);
            $store->fill($data);
            $store->save();
        } else {
            $store = EloquentStore::create($data);
        }
        return $store->toArray();
    }

    public function delete(int $id): int
    {
        $store = EloquentStore::find($id);
        if ($store) {
            return $store->delete() ? 1 : 0;
        }
        return 0;
    }

    public function getFeatures(int $storeId): array
    {
        return StoreFeature::where('store_id', $storeId)
            ->pluck('feature_key')
            ->toArray();
    }

    public function saveFeatures(int $storeId, array $features): void
    {
        StoreFeature::where('store_id', $storeId)->delete();
        foreach ($features as $key) {
            StoreFeature::create(['store_id' => $storeId, 'feature_key' => $key]);
        }
    }

    public function getImportSettings(int $storeId): array
    {
        $row = StoreImportSetting::where('store_id', $storeId)->first();
        return $row ? $row->toArray() : [];
    }

    public function saveImportSettings(int $storeId, array $settings): void
    {
        StoreImportSetting::updateOrCreate(
            ['store_id' => $storeId],
            $settings
        );
    }

    public function getDeductionSettings(int $storeId): array
    {
        $row = StoreDeductionSetting::where('store_id', $storeId)->first();
        return $row ? $row->toArray() : [];
    }

    public function saveDeductionSettings(int $storeId, array $settings): void
    {
        StoreDeductionSetting::updateOrCreate(
            ['store_id' => $storeId],
            $settings
        );
    }
}
