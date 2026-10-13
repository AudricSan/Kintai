<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Garde-fou des comptes Owner : l'instance doit toujours compter au moins un Owner actif, et un Owner ne
 * peut pas retirer ses propres droits. Sans cela, un Owner pouvait se supprimer (API comprise) ou désactiver
 * le dernier Owner, et l'instance n'avait plus d'administrateur — seule une intervention en base la réparait.
 *
 * « Retirer » couvre trois actions : supprimer le compte, le désactiver, lui retirer le rôle Owner.
 */
final class OwnerGuard
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function isOwner(int $userId): bool
    {
        return in_array($userId, $this->permissions->ownerUserIds(), true);
    }

    /**
     * Owners utilisables : compte actif et non supprimé.
     *
     * @return int[]
     */
    public function activeOwnerIds(): array
    {
        $ids = [];
        foreach ($this->permissions->ownerUserIds() as $ownerId) {
            $user = $this->users->findById($ownerId);
            if ($user !== null && !empty($user['is_active']) && empty($user['deleted_at'])) {
                $ids[] = $ownerId;
            }
        }
        return $ids;
    }

    /**
     * À appeler avant de supprimer, désactiver ou dégrader $targetUserId. Sans effet si la cible n'est pas Owner.
     *
     * @throws ForbiddenException Si l'acteur vise son propre compte Owner, ou si la cible est le dernier Owner actif
     */
    public function assertCanRemoveOwner(int $actorId, int $targetUserId): void
    {
        if (!$this->isOwner($targetUserId)) {
            return;
        }
        if ($actorId === $targetUserId) {
            throw new ForbiddenException(__('error_owner_cannot_remove_self'));
        }
        if (array_diff($this->activeOwnerIds(), [$targetUserId]) === []) {
            throw new ForbiddenException(__('error_last_owner'));
        }
    }
}
