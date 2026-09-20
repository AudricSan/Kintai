<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Applique les limites du plan gratuit (freemium). Phase 1 : plan "free" codé
 * en dur, sans licence distante — un futur client de licence (phase 2) pourra
 * remonter un plan payant qui lève ces limites, sans changer les points d'appel.
 */
final class PlanLimitService
{
    private const FREE_MAX_STORES = 1;
    private const FREE_MAX_EMPLOYEES = 15;
    private const FREE_MAX_ACTIVE_BUNDLES = 4;

    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * Nombre max de bundles actifs simultanément, ou null si illimité.
     */
    public function maxActiveBundles(): ?int
    {
        return self::FREE_MAX_ACTIVE_BUNDLES;
    }

    public function assertCanCreateStore(): void
    {
        if ($this->stores->countActive() >= self::FREE_MAX_STORES) {
            throw new PlanLimitExceededException(__('plan_limit_stores'));
        }
    }

    public function assertCanCreateEmployee(): void
    {
        if ($this->users->countActive() >= self::FREE_MAX_EMPLOYEES) {
            throw new PlanLimitExceededException(__('plan_limit_employees'));
        }
    }
}
