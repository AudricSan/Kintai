<?php

namespace kintai\Core\Database;

use Exception;

class JsonDriver implements PersistenceDriverInterface
{
    private string $filePath;
    private array $data = [];
    private bool $connected = false;

    /**
     * Connects to the JSON persistence layer.
     * @param array $config Configuration array for the connection.
     * @return void
     * @throws Exception If 'path' is not provided in the config.
     */
    public function connect(array $config): void
    {
        if (!isset($config['path'])) {
            throw new Exception("JSON driver 'path' configuration is missing.");
        }
        $this->filePath = $config['path'];

        if (!file_exists($this->filePath)) {
            // Attempt to create the directory if it doesn't exist
            $dir = dirname($this->filePath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                throw new Exception("Failed to create directory for JSON file: {$dir}");
            }
            file_put_contents($this->filePath, json_encode([])); // Initialize with empty array
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new Exception("Failed to read JSON file: {$this->filePath}");
        }
        $this->data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON file: " . json_last_error_msg());
        }
        $this->connected = true;
    }

    /**
     * Disconnects from the JSON persistence layer.
     * For JSON, this typically means saving current data back to the file.
     * @return void
     * @throws Exception If not connected or failed to write to file.
     */
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }
        $result = file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT));
        if ($result === false) {
            throw new Exception("Failed to write to JSON file: {$this->filePath}");
        }
        $this->data = [];
        $this->connected = false;
    }

    /**
     * Finds records based on criteria in JSON data.
     * @param string $entity The name of the entity/collection (key in JSON).
     * @param array $criteria An associative array of criteria.
     * @return array An array of found records.
     */
    public function find(string $entity, array $criteria = []): array
    {
        if (!$this->connected) {
            throw new Exception("JSON Driver not connected.");
        }

        if (!isset($this->data[$entity])) {
            return [];
        }

        $results = [];
        foreach ($this->data[$entity] as $record) {
            $match = true;
            foreach ($criteria as $key => $value) {
                if (!isset($record[$key]) || $record[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $results[] = $record;
            }
        }
        return $results;
    }

    /**
     * Finds a single record based on criteria in JSON data.
     * @param string $entity The name of the entity/collection (key in JSON).
     * @param array $criteria An associative array of criteria.
     * @return array|null The found record or null if not found.
     */
    public function findOne(string $entity, array $criteria): ?array
    {
        $results = $this->find($entity, $criteria);
        return empty($results) ? null : $results[0];
    }

    /**
     * Saves a record to JSON data.
     * @param string $entity The name of the entity/collection (key in JSON).
     * @param array $data The data to save.
     * @return array The saved record data, including any generated identifiers.
     * @throws Exception If not connected.
     */
    public function save(string $entity, array $data): array
    {
        if (!$this->connected) {
            throw new Exception("JSON Driver not connected.");
        }

        if (!isset($this->data[$entity])) {
            $this->data[$entity] = [];
        }

        if (isset($data['id'])) {
            // Attempt to update existing record
            foreach ($this->data[$entity] as $key => $record) {
                if (isset($record['id']) && $record['id'] === $data['id']) {
                    $this->data[$entity][$key] = array_merge($record, $data);
                    return $this->data[$entity][$key];
                }
            }
        }

        // Add new record
        $id = $this->generateId($entity);
        $data['id'] = $id;
        $this->data[$entity][] = $data;
        return $data;
    }

    /**
     * Deletes records based on criteria from JSON data.
     * @param string $entity The name of the entity/collection (key in JSON).
     * @param array $criteria An associative array of criteria.
     * @return int The number of deleted records.
     * @throws Exception If not connected.
     */
    public function delete(string $entity, array $criteria): int
    {
        if (!$this->connected) {
            throw new Exception("JSON Driver not connected.");
        }

        if (!isset($this->data[$entity])) {
            return 0;
        }

        $initialCount = count($this->data[$entity]);
        $this->data[$entity] = array_filter($this->data[$entity], function ($record) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!isset($record[$key]) || $record[$key] !== $value) {
                    return true; // Keep this record
                }
            }
            return false; // Remove this record
        });
        // Re-index array after filtering
        $this->data[$entity] = array_values($this->data[$entity]);

        return $initialCount - count($this->data[$entity]);
    }

    /**
     * Generates a unique ID for a new record within an entity.
     * @param string $entity The name of the entity/collection.
     * @return int The generated ID.
     */
    private function generateId(string $entity): int
    {
        if (!isset($this->data[$entity]) || empty($this->data[$entity])) {
            return 1;
        }
        $ids = array_column($this->data[$entity], 'id');
        return $ids ? max($ids) + 1 : 1;
    }
}
