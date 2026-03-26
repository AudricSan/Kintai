<?php

namespace kintai\Core\Repositories;

use kintai\Core\Database\PersistenceDriverInterface;

class DatabaseFeedbackRepository implements FeedbackRepositoryInterface
{
    private const ENTITY_NAME = 'employee_feedbacks';
    private PersistenceDriverInterface $driver;

    public function __construct(PersistenceDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function findById(int $id): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['id' => $id]);
    }

    public function findByStore(int $storeId): array
    {
        return $this->driver->find(self::ENTITY_NAME, ['store_id' => $storeId]);
    }

    public function findAll(): array
    {
        return $this->driver->find(self::ENTITY_NAME);
    }

    public function findByShift(int $shiftId): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['shift_id' => $shiftId]);
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
