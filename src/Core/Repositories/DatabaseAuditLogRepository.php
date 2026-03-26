<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Core\Database\PersistenceDriverInterface;

class DatabaseAuditLogRepository implements AuditLogRepositoryInterface
{
    private const ENTITY_NAME = 'audit_log';

    public function __construct(private readonly PersistenceDriverInterface $driver) {}

    public function log(array $data): void
    {
        // Insertion uniquement (pas d'update — table append-only)
        $this->driver->save(self::ENTITY_NAME, $data);
    }

    public function findRecent(int $limit = 500): array
    {
        $all = $this->driver->find(self::ENTITY_NAME);
        usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($all, 0, $limit);
    }
}
