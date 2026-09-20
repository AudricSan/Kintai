<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Applique les limites du plan gratuit (freemium). Une licence payante active
 * (`LicenseClientService::isPaidPlanActive()`, lecture locale sans réseau)
 * lève toutes les limites ci-dessous — le serveur de licence distant ne
 * renvoie pas de quotas par plan (voir docs/architecture.md "Licensing &
 * Freemium"), donc tout plan payant vaut illimité pour l'instant.
 */
final class PlanLimitService
{
    private const FREE_MAX_STORES = 1;
    private const FREE_MAX_EMPLOYEES = 15;
    private const FREE_MAX_ACTIVE_BUNDLES = 4;

    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly LicenseClientService $license,
    ) {
    }

    /**
     * Nombre max de bundles actifs simultanément, ou null si illimité.
     */
    public function maxActiveBundles(): ?int
    {
        return $this->license->isPaidPlanActive() ? null : self::FREE_MAX_ACTIVE_BUNDLES;
    }

    /** Nombre max de magasins, ou null si illimité (licence payante active). */
    public function maxStores(): ?int
    {
        return $this->license->isPaidPlanActive() ? null : self::FREE_MAX_STORES;
    }

    /** Nombre max d'employés actifs, ou null si illimité (licence payante active). */
    public function maxEmployees(): ?int
    {
        return $this->license->isPaidPlanActive() ? null : self::FREE_MAX_EMPLOYEES;
    }

    public function currentStoreCount(): int
    {
        return $this->stores->countActive();
    }

    public function currentEmployeeCount(): int
    {
        return $this->users->countActive();
    }

    public function assertCanCreateStore(): void
    {
        if ($this->license->isPaidPlanActive()) {
            return;
        }
        if ($this->stores->countActive() >= self::FREE_MAX_STORES) {
            throw new PlanLimitExceededException(__('plan_limit_stores'));
        }
    }

    public function assertCanCreateEmployee(): void
    {
        if ($this->license->isPaidPlanActive()) {
            return;
        }
        if ($this->users->countActive() >= self::FREE_MAX_EMPLOYEES) {
            throw new PlanLimitExceededException(__('plan_limit_employees'));
        }
    }
}
