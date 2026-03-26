<?php

namespace kintai\Core\Database;

use PDO;
use PDOException;
use Exception;

class SqliteDriver implements PersistenceDriverInterface
{
    private ?PDO $pdo = null;
    private bool $connected = false;

    /**
     * Connects to the SQLite persistence layer.
     * @param array $config Configuration array for the connection.
     * @return void
     * @throws Exception If 'path' is not provided in the config or connection fails.
     */
    public function connect(array $config): void
    {
        if (!isset($config['path'])) {
            throw new Exception("SQLite driver 'path' configuration is missing.");
        }

        $dbPath = $config['path'];
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new Exception("Failed to create directory for SQLite file: {$dir}");
        }

        try {
            $this->pdo = new PDO("sqlite:{$dbPath}");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connected = true;
        } catch (PDOException $e) {
            throw new Exception("SQLite connection failed: " . $e->getMessage());
        }
    }

    /**
     * Disconnects from the SQLite persistence layer.
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
    }

    /**
     * Finds records based on criteria in SQLite.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return array An array of found records.
     * @throws Exception If not connected.
     */
    public function find(string $entity, array $criteria = []): array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("SQLite Driver not connected.");
        }

        $where = '';
        $params = [];
        if (!empty($criteria)) {
            $where = ' WHERE ' . implode(' AND ', array_map(function ($key) {
                return "{$key} = :{$key}";
            }, array_keys($criteria)));
            $params = $criteria;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$entity}{$where}");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Finds a single record based on criteria in SQLite.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return array|null The found record or null if not found.
     * @throws Exception If not connected.
     */
    public function findOne(string $entity, array $criteria): ?array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("SQLite Driver not connected.");
        }

        $where = ' WHERE ' . implode(' AND ', array_map(function ($key) {
            return "{$key} = :{$key}";
        }, array_keys($criteria)));
        
        $stmt = $this->pdo->prepare("SELECT * FROM {$entity}{$where} LIMIT 1");
        $stmt->execute($criteria);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Saves a record to SQLite.
     * @param string $entity The name of the table.
     * @param array $data The data to save.
     * @return array The saved record data, including any generated identifiers.
     * @throws Exception If not connected.
     */
    public function save(string $entity, array $data): array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("SQLite Driver not connected.");
        }

        if (isset($data['id'])) {
            // Exclude 'id' from the SET clause to avoid double-binding the :id parameter.
            $id     = $data['id'];
            $fields = $data;
            unset($fields['id']);

            $set    = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
            $params = array_merge($fields, ['id' => $id]);

            $stmt = $this->pdo->prepare("UPDATE {$entity} SET {$set} WHERE id = :id");
            $stmt->execute($params);
            return $data;
        }

        // Insert new record
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO {$entity} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($data);
        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }

    /**
     * Deletes records based on criteria from SQLite.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return int The number of deleted records.
     * @throws Exception If not connected.
     */
    public function delete(string $entity, array $criteria): int
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("SQLite Driver not connected.");
        }

        $where = implode(' AND ', array_map(function ($key) {
            return "{$key} = :{$key}";
        }, array_keys($criteria)));

        $stmt = $this->pdo->prepare("DELETE FROM {$entity} WHERE {$where}");
        $stmt->execute($criteria);
        return $stmt->rowCount();
    }
}
