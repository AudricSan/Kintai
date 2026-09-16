<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface ResignationReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;
    public function findAll(?array $storeIds = null, array $filters = []): array;
    public function save(array $data): array;
    public function delete(int $id): int;
}
