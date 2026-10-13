<?php

declare(strict_types=1);


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
     * Retourne tous les stores, sauf les supprimés (deleted_at renseigné).
     */
    public function findAll(): array;

    /**
     * Retourne tous les stores, supprimés compris : pour retrouver le nom du magasin d'un shift ou d'un
     * pointage ancien. Ne jamais l'utiliser pour une liste ou un sélecteur.
     */
    public function findAllWithDeleted(): array;

    /**
     * Retourne les stores actifs (deleted_at IS NULL, is_active = 1).
     */
    public function findActive(): array;

    /**
     * Compte les stores actifs (deleted_at IS NULL, is_active = 1).
     */
    public function countActive(): int;

    /**
     * Crée ou met à jour un store. Crée si pas d'ID, met à jour sinon.
     */
    public function save(array $data): array;

    /**
     * Supprime un store (suppression douce : deleted_at, inactif, code et alias d'URL libérés ; l'historique
     * qui le référence reste en base). La ligne n'est jamais effacée.
     * @return int Nombre de stores supprimés (0 ou 1).
     */
    public function delete(int $id): int;

    public function getFeatures(int $storeId): array;

    public function saveFeatures(int $storeId, array $features): void;

    public function getImportSettings(int $storeId): array;

    public function saveImportSettings(int $storeId, array $settings): void;

    public function getDeductionSettings(int $storeId): array;

    public function saveDeductionSettings(int $storeId, array $settings): void;
}
