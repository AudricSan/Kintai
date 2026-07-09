<?php

declare(strict_types=1);

namespace kintai\Bundles\Timeclock;

use kintai\Core\Bundle;

/**
 * Contrairement aux bundles à extraction complète (StorePhoto, ShiftClaim, ...),
 * TimeclockRepositoryInterface reste un service Core (RepositoryServiceProvider) :
 * HomeController (widget "pointages actifs" du dashboard admin) et
 * EmployeeController::dashboard() (widget "pointage en cours" de l'employé) en
 * dépendent tous deux pour des calculs qui doivent continuer de fonctionner même
 * si ce bundle est désactivé. Désactiver "timeclock" retire uniquement l'UI
 * dédiée (pointer/dépointer, historique employé, gestion admin des entrées,
 * /api/v1/timeclocks), pas les données existantes ni ces deux widgets.
 */
final class TimeclockBundle extends Bundle
{
    public function getName(): string
    {
        return 'timeclock';
    }

    public function getLabel(): string
    {
        return __('bundle_timeclock');
    }

    public function getDescription(): string
    {
        return __('bundle_timeclock_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'timeclock');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
