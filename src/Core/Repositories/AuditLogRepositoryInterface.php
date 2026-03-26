<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface AuditLogRepositoryInterface
{
    /** Insère une entrée dans le journal. */
    public function log(array $data): void;

    /** Retourne toutes les entrées, triées par date décroissante, limitées à $limit. */
    public function findRecent(int $limit = 500): array;
}
