<?php

declare(strict_types=1);

namespace kintai\Bundles\ShiftClaim;

use kintai\Core\Bundle;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\DatabaseShiftClaimRepository;

/**
 * Contrairement à TimeOff/ShiftSwap, aucun composant Core ne dépend de
 * ShiftClaimRepositoryInterface en dehors de la bourse aux shifts elle-même
 * (pas de lecture depuis les stats, l'iCal ou le dashboard) : le repository
 * peut donc être enregistré par ce bundle, comme DailyReport/Messaging/
 * StorePhoto. Désactiver "shift-claim" retire entièrement la fonctionnalité.
 */
final class ShiftClaimBundle extends Bundle
{
    public function getName(): string
    {
        return 'shift-claim';
    }

    public function getLabel(): string
    {
        return __('bundle_shift_claim');
    }

    public function getDescription(): string
    {
        return __('bundle_shift_claim_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'shift-claim');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            ShiftClaimRepositoryInterface::class,
            fn() => new DatabaseShiftClaimRepository()
        );
    }
}
