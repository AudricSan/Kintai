<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\IcalToken as EloquentIcalToken;

final class DatabaseIcalTokenRepository implements IcalTokenRepositoryInterface
{
    public function __construct() {}

    public function findByToken(string $token): ?array
    {
        $record = EloquentIcalToken::where('token', $token)->first();
        return $record ? $record->toArray() : null;
    }

    public function findByUserAndStore(int $userId, int $storeId): ?array
    {
        $record = EloquentIcalToken::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->first();
        return $record ? $record->toArray() : null;
    }

    public function findByUser(int $userId): array
    {
        return EloquentIcalToken::where('user_id', $userId)->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentIcalToken::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentIcalToken::create($data);
        }
        return $record->toArray();
    }

    public function deleteByUserAndStore(int $userId, int $storeId): int
    {
        return EloquentIcalToken::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->delete();
    }
}
