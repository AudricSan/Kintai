<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface IcalTokenRepositoryInterface
{
    public function findByToken(string $token): ?array;

    public function findByUserAndStore(int $userId, int $storeId): ?array;

    public function findByUser(int $userId): array;

    public function save(array $data): array;

    public function deleteByUserAndStore(int $userId, int $storeId): int;
}
