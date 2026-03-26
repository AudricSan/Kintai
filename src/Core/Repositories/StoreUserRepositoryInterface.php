<?php

namespace kintai\Core\Repositories;

interface StoreUserRepositoryInterface
{
    /**
     * Trouve un enregistrement store_user par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne tous les membres d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Retourne tous les stores auxquels appartient un utilisateur.
     */
    public function findByUser(int $userId): array;

    /**
     * Trouve l'entrée pivot pour un store et un utilisateur donnés.
     */
    public function findMembership(int $storeId, int $userId): ?array;

    /**
     * Crée ou met à jour une appartenance. Crée si pas d'ID, met à jour sinon.
     */
    public function save(array $data): array;

    /**
     * Supprime une appartenance par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
