<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface ShiftClaimRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByShift(int $shiftId): array;

    public function findByUser(int $userId): array;

    public function findByStore(int $storeId): array;

    public function findByUserAndShift(int $userId, int $shiftId): ?array;

    /** Retourne les candidatures en attente pour un ou plusieurs stores. */
    public function findPendingByStore(int $storeId): array;

    public function findAll(): array;

    public function save(array $data): array;

    public function delete(int $id): int;
}
