<?php

namespace kintai\Core\Database;

interface PersistenceDriverInterface
{
    /**
     * Connects to the persistence layer.
     * @param array $config Configuration array for the connection.
     * @return void
     */
    public function connect(array $config): void;

    /**
     * Disconnects from the persistence layer.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Finds records based on criteria.
     * @param string $entity The name of the entity/table.
     * @param array $criteria An associative array of criteria (e.g., ['id' => 1]).
     * @return array An array of found records.
     */
    public function find(string $entity, array $criteria = []): array;

    /**
     * Finds a single record based on criteria.
     * @param string $entity The name of the entity/table.
     * @param array $criteria An associative array of criteria.
     * @return array|null The found record or null if not found.
     */
    public function findOne(string $entity, array $criteria): ?array;

    /**
     * Saves a record.
     * If the record has an identifier (e.g., 'id'), it should update an existing record.
     * Otherwise, it should create a new record.
     * @param string $entity The name of the entity/table.
     * @param array $data The data to save.
     * @return array The saved record data, including any generated identifiers.
     */
    public function save(string $entity, array $data): array;

    /**
     * Deletes records based on criteria.
     * @param string $entity The name of the entity/table.
     * @param array $criteria An associative array of criteria.
     * @return int The number of deleted records.
     */
    public function delete(string $entity, array $criteria): int;
}
