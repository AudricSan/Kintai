<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface SalaryReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;
    public function findAll(?array $storeIds = null, array $filters = []): array;
    public function findByStoreAndMonth(int $storeId, string $targetMonth): ?array;
    public function save(array $data): array;
    public function delete(int $id): int;
}
