<?php

namespace kintai\Core\Repositories;

interface StoreRepositoryInterface
{
    /**
     * Trouve un store par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Trouve un store par son code unique.
     */
    public function findByCode(string $code): ?array;

    /**
     * Retourne tous les stores.
     */
    public function findAll(): array;

    /**
     * Retourne les stores actifs (deleted_at IS NULL, is_active = 1).
     */
    public function findActive(): array;

    /**
     * Crée ou met à jour un store. Crée si pas d'ID, met à jour sinon.
     */
    public function save(array $data): array;

    /**
     * Supprime un store par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
