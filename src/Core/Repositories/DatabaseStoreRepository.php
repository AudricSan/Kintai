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
        $store = EloquentStore::where('code', $code)->whereNull('deleted_at')->first();
        return $store ? $store->toArray() : null;
    }

    public function findAll(): array
    {
        return EloquentStore::whereNull('deleted_at')->get()->toArray();
    }

    public function findAllWithDeleted(): array
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

    public function countActive(): int
    {
        return EloquentStore::where('is_active', 1)
            ->whereNull('deleted_at')
            ->count();
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
        $store = EloquentStore::whereNull('deleted_at')->find($id);
        if (!$store) {
            return 0;
        }

        // Alias d'URL du magasin (courant et historique) : libérés pour un futur magasin.
        try {
            \kintai\Domain\Eloquent\RouteSlug::where('entity_type', 'store')->where('entity_id', $id)->delete();
        } catch (\Illuminate\Database\QueryException) {
            // Table route_slugs absente (migration pas encore jouée).
        }

        // Suppression douce (audit du 13/10/2026, M8) : effacer la ligne supprimait en cascade, sous MySQL,
        // les shifts, pointages et rapports du magasin, et les laissait orphelins sous SQLite. Le code,
        // unique et utilisé à la connexion par code, est libéré pour pouvoir être réattribué.
        $store->fill([
            'code'       => 'DEL-' . $id . '-' . $store->code,
            'is_active'  => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $store->save() ? 1 : 0;
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
