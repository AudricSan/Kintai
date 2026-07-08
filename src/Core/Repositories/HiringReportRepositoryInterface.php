<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface HiringReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;
    public function save(array $data): array;
    public function delete(int $id): int;
}
