<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;

/**
 * Maintient role_assignments synchronisée avec les points d'entrée qui
 * écrivent encore les colonnes historiques (users.is_admin, store_user.role) :
 * AuthService lit désormais exclusivement role_assignments pour calculer
 * isAdmin()/managedStoreIds(), donc toute création/modification d'un compte
 * Owner ou d'une appartenance à un store doit aussi mettre à jour
 * role_assignments, sous peine de rendre le changement invisible pour
 * l'autorisation (task/mermission.md, phase 3 — bascule).
 */
final class RoleAssignmentSyncService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly RoleAssignmentRepositoryInterface $assignments,
    ) {}

    /** Assigne ou retire le rôle Owner (portée globale) selon le flag users.is_admin posté. */
    public function syncOwnerRole(int $userId, bool $isAdmin): void
    {
        $ownerRole = $this->roles->findBySlug('owner');
        if ($ownerRole === null) {
            return;
        }
        $ownerRoleId = (int) $ownerRole['id'];
        $existing = array_values(array_filter(
            $this->assignments->findByUser($userId),
            fn($a) => $a['scope_type'] === 'global' && (int) $a['role_id'] === $ownerRoleId,
        ));

        if ($isAdmin) {
            if ($existing === []) {
                $this->assignments->assign($userId, $ownerRoleId, 'global', null);
            }
            return;
        }
        foreach ($existing as $a) {
            $this->assignments->revoke((int) $a['id']);
        }
    }

    /**
     * Synchronise l'affectation de rôle store-scope à partir d'une valeur
     * historique de store_user.role ('staff'|'manager'|'admin'). 'admin'
     * (l'ancien "Store Administrator") est fusionné dans Manager, comme dans
     * le backfill de la phase 2 — les deux ont toujours eu les mêmes
     * permissions dans le code.
     */
    public function syncStoreRole(int $userId, int $storeId, string $legacyRole): void
    {
        $targetSlug = in_array($legacyRole, ['admin', 'manager'], true) ? 'manager' : 'employee';
        $targetRole = $this->roles->findBySlug($targetSlug);
        if ($targetRole === null) {
            return;
        }
        $targetRoleId = (int) $targetRole['id'];

        $storeAssignments = array_values(array_filter(
            $this->assignments->findByUser($userId),
            fn($a) => $a['scope_type'] === 'store' && (int) $a['scope_id'] === $storeId,
        ));

        foreach ($storeAssignments as $a) {
            if ((int) $a['role_id'] !== $targetRoleId) {
                $this->assignments->revoke((int) $a['id']);
            }
        }
        if (!in_array($targetRoleId, array_map(fn($a) => (int) $a['role_id'], $storeAssignments), true)) {
            $this->assignments->assign($userId, $targetRoleId, 'store', $storeId);
        }
    }

    /** Retire toute affectation de rôle store-scope pour cet utilisateur sur ce store. */
    public function revokeStoreRole(int $userId, int $storeId): void
    {
        foreach ($this->assignments->findByUser($userId) as $a) {
            if ($a['scope_type'] === 'store' && (int) $a['scope_id'] === $storeId) {
                $this->assignments->revoke((int) $a['id']);
            }
        }
    }
}
