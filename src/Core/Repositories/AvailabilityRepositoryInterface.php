<?php

namespace kintai\Core\Repositories;

interface AvailabilityRepositoryInterface
{
    /**
     * Trouve une disponibilité par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne toutes les disponibilités d'un utilisateur.
     */
    public function findByUser(int $userId): array;

    /**
     * Retourne toutes les disponibilités d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Crée ou met à jour une disponibilité.
     */
    public function save(array $data): array;

    /**
     * Supprime une disponibilité par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
