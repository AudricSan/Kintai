<?php

namespace kintai\Core\Database;

use PDO;
use PDOException;
use Exception;

class MysqlDriver implements PersistenceDriverInterface
{
    private ?PDO $pdo = null;
    private bool $connected = false;

    /**
     * Connects to the MySQL persistence layer.
     * @param array $config Configuration array for the connection.
     * @return void
     * @throws Exception If connection fails or required config is missing.
     */
    public function connect(array $config): void
    {
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new Exception("MySQL driver configuration '{$key}' is missing.");
            }
        }

        $port    = $config['port']    ?? 3306;
        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn     = "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset={$charset}";
        try {
            $this->pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // For better security and proper data types
                ]
            );
            $this->connected = true;
        } catch (PDOException $e) {
            throw new Exception("MySQL connection failed: " . $e->getMessage());
        }
    }

    /**
     * Disconnects from the MySQL persistence layer.
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
    }

    /**
     * Finds records based on criteria in MySQL.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return array An array of found records.
     * @throws Exception If not connected.
     */
    public function find(string $entity, array $criteria = []): array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("MySQL Driver not connected.");
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
     * Finds a single record based on criteria in MySQL.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return array|null The found record or null if not found.
     * @throws Exception If not connected.
     */
    public function findOne(string $entity, array $criteria): ?array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("MySQL Driver not connected.");
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
     * Saves a record to MySQL.
     * @param string $entity The name of the table.
     * @param array $data The data to save.
     * @return array The saved record data, including any generated identifiers.
     * @throws Exception If not connected.
     */
    public function save(string $entity, array $data): array
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("MySQL Driver not connected.");
        }

        if (isset($data['id']) && $this->findOne($entity, ['id' => $data['id']])) {
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

        // Insert new record — never pass 'id' to allow the DB to auto-increment.
        $insertData   = $data;
        unset($insertData['id']);

        $columns      = implode(', ', array_keys($insertData));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($insertData)));

        $stmt = $this->pdo->prepare("INSERT INTO {$entity} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($insertData);
        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }

    /**
     * Deletes records based on criteria from MySQL.
     * @param string $entity The name of the table.
     * @param array $criteria An associative array of criteria.
     * @return int The number of deleted records.
     * @throws Exception If not connected.
     */
    public function delete(string $entity, array $criteria): int
    {
        if (!$this->connected || !$this->pdo) {
            throw new Exception("MySQL Driver not connected.");
        }

        $where = implode(' AND ', array_map(function ($key) {
            return "{$key} = :{$key}";
        }, array_keys($criteria)));

        $stmt = $this->pdo->prepare("DELETE FROM {$entity} WHERE {$where}");
        $stmt->execute($criteria);
        return $stmt->rowCount();
    }
}
