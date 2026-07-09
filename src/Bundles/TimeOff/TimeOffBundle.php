<?php

declare(strict_types=1);

namespace kintai\Bundles\TimeOff;

use kintai\Core\Bundle;

/**
 * Contrairement aux autres bundles, TimeOff n'enregistre pas son propre
 * repository : TimeoffRequestRepositoryInterface reste un service Core
 * (RepositoryServiceProvider), car StoreStatsService, ShiftService,
 * AdminShiftController, IcalController et HomeController en dépendent tous
 * pour des calculs qui doivent continuer de fonctionner même si ce bundle
 * est désactivé. Désactiver "timeoff" retire uniquement l'UI de gestion des
 * congés (créer/approuver/consulter une demande), pas les données elles-mêmes.
 */
final class TimeOffBundle extends Bundle
{
    public function getName(): string
    {
        return 'timeoff';
    }

    public function getLabel(): string
    {
        return __('bundle_timeoff');
    }

    public function getDescription(): string
    {
        return __('bundle_timeoff_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'timeoff');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
