<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto;

use kintai\Core\BundleContract\Bundle;

/**
 * Contrairement à sa version legacy, ce bundle n'enregistre plus son propre
 * repository : StorePhotoRepositoryInterface reste un service Core
 * (RepositoryServiceProvider), car GithubUpdateService et le script CLI
 * consolidate-daily-photo-reports.php en dépendent directement — ils doivent
 * continuer de fonctionner même si ce bundle est désactivé ou désinstallé.
 * StorePhotoConsolidationService, utilisé par ces deux consommateurs Core,
 * vit désormais dans kintai\Core\Services pour la même raison. Désactiver
 * "store-photos" retire uniquement l'UI d'envoi/consultation des photos, pas
 * les données elles-mêmes ni le rattrapage de consolidation.
 */
final class StorePhotoBundle extends Bundle
{
    public function getName(): string
    {
        return 'store-photos';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_store_photos');
    }

    public function getDescription(): string
    {
        return __('bundle_store_photos_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'store-photos');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
