<?php

namespace kintai\Core\Repositories;

interface ShiftRepositoryInterface
{
    /**
     * Trouve un shift par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne tous les shifts d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Retourne tous les shifts d'un utilisateur.
     */
    public function findByUser(int $userId): array;

    /**
     * Retourne les shifts d'un store à une date donnée (YYYY-MM-DD).
     */
    public function findByDate(int $storeId, string $date): array;

    /**
     * Retourne les shifts d'un utilisateur dans un store à une date donnée.
     */
    public function findByUserAndDate(int $userId, int $storeId, string $date): array;

    /**
     * Retourne tous les shifts (toutes tables, sans filtre).
     */
    public function findAll(): array;

    /**
     * Retourne tous les shifts pour une date donnée (YYYY-MM-DD), tous stores confondus.
     */
    public function findAllByDate(string $date): array;

    /**
     * Crée ou met à jour un shift.
     */
    public function save(array $data): array;

    /**
     * Supprime un shift par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
