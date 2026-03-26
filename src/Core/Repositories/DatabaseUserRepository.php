<?php

namespace kintai\Core\Repositories;

use kintai\Core\Database\PersistenceDriverInterface;

class DatabaseUserRepository implements UserRepositoryInterface
{
    private const ENTITY_NAME = 'users'; // Represents the table name or JSON collection name
    private PersistenceDriverInterface $driver;

    public function __construct(PersistenceDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Finds a user by their ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['id' => $id]);
    }

    /**
     * Finds a user by their email address.
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['email' => $email]);
    }

    public function findByEmployeeCode(string $code): ?array
    {
        return $this->driver->findOne(self::ENTITY_NAME, ['employee_code' => $code]);
    }

    /**
     * Retrieves all users.
     * @return array
     */
    public function findAll(): array
    {
        return $this->driver->find(self::ENTITY_NAME);
    }

    /**
     * Saves a user record. Creates if no ID, updates if ID exists.
     * @param array $userData
     * @return array The saved user data.
     */
    public function save(array $userData): array
    {
        return $this->driver->save(self::ENTITY_NAME, $userData);
    }

    /**
     * Deletes a user by their ID.
     * @param int $id
     * @return int The number of deleted users (0 or 1).
     */
    public function delete(int $id): int
    {
        return $this->driver->delete(self::ENTITY_NAME, ['id' => $id]);
    }
}
