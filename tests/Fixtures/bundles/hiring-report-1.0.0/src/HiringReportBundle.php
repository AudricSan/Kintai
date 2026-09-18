<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport;

use kintai\Core\BundleContract\Bundle;

/**
 * Contrairement à ResignationReport/SalaryReport/StorePhoto, ce bundle
 * n'enregistre pas son propre repository : HiringReportRepositoryInterface
 * reste un service Core (RepositoryServiceProvider), car AdminUserController
 * l'utilise directement pour générer automatiquement un rapport d'embauche
 * à chaque création d'employé (formulaire standard et import Excel rapide) —
 * un effet de bord du cœur de la gestion des utilisateurs, qui doit continuer
 * de fonctionner même si ce bundle est désactivé ou désinstallé. Désactiver
 * "hiring-report" retire uniquement l'UI de consultation/édition des rapports
 * d'embauche (liste, fiche, PDF), pas la génération automatique ni les
 * données elles-mêmes.
 */
final class HiringReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'hiring-report';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_hiring_report');
    }

    public function getDescription(): string
    {
        return __('bundle_hiring_report_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'hiring-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
