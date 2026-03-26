<?php

namespace kintai\Core\Repositories;

use kintai\Core\Database\PersistenceDriverInterface;

class DatabaseUserShiftTypeRateRepository implements UserShiftTypeRateRepositoryInterface
{
    private const ENTITY_NAME = 'user_shift_type_rates';

    public function __construct(private readonly PersistenceDriverInterface $driver) {}

    public function findById(int $id): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['id' => $id]);
    }

    public function findByUser(int $userId): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['user_id' => $userId]);
    }

    public function findByShiftType(int $shiftTypeId): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['shift_type_id' => $shiftTypeId]);
    }

    public function findRate(int $userId, int $shiftTypeId): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, [
            'user_id'       => $userId,
            'shift_type_id' => $shiftTypeId,
        ]);
    }

    public function save(array $data): array
    {
        return $this->driver->save(self::ENTITY_NAME, $data);
    }

    public function delete(int $id): int
    {
        return $this->driver->delete(self::ENTITY_NAME, ['id' => $id]);
    }
}
