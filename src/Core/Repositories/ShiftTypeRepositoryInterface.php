<?php

namespace kintai\Core\Repositories;

interface ShiftTypeRepositoryInterface
{
    /**
     * Trouve un type de shift par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne tous les types de shifts d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Retourne les types de shifts actifs d'un store.
     */
    public function findActive(int $storeId): array;

    /**
     * Retourne tous les types de shifts (sans filtre).
     */
    public function findAll(): array;

    /**
     * Crée ou met à jour un type de shift.
     */
    public function save(array $data): array;

    /**
     * Supprime un type de shift par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
