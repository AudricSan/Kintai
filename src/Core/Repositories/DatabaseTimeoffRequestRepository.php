<?php

namespace kintai\Core\Repositories;

use kintai\Core\Database\PersistenceDriverInterface;

class DatabaseTimeoffRequestRepository implements TimeoffRequestRepositoryInterface
{
    private const ENTITY_NAME = 'timeoff_requests';
    private PersistenceDriverInterface $driver;

    public function __construct(PersistenceDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function findById(int $id): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['id' => $id]);
    }

    public function findByUser(int $userId): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['user_id' => $userId]);
    }

    public function findByStore(int $storeId): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['store_id' => $storeId]);
    }

    public function findByStatus(int $storeId, string $status): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['store_id' => $storeId, 'status' => $status]);
    }

    public function findAll(): array
    {
        return $this->driver->find(self::ENTITY_NAME);
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
