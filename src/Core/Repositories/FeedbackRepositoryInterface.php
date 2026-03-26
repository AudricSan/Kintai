<?php

namespace kintai\Core\Repositories;

interface FeedbackRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByStore(int $storeId): array;

    public function findAll(): array;

    /** Retourne le feedback associé à un shift (contrainte unicité). */
    public function findByShift(int $shiftId): ?array;

    public function save(array $data): array;

    public function delete(int $id): int;
}
