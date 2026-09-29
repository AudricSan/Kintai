<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\DatabaseSalaryReportRepository;

/**
 * Comme ResignationReport/ShiftClaim/StorePhoto, aucun autre composant Core
 * ne dépend de SalaryReportRepositoryInterface : le repository est enregistré
 * par ce bundle, pas par le Core. Désactiver ou désinstaller "salary-report"
 * retire entièrement la fonctionnalité.
 *
 * Dépendance bundle→bundle : AdminSalaryReportController utilise
 * DailyReportRepositoryInterface (Core-bound, voir docs/architecture.md côté
 * Kintai) pour pré-remplir le chiffre d'affaires du mois depuis les rapports
 * journaliers validés/soumis (calculateSalaryPreset). Cette interface restant
 * liée par Kintai Core indépendamment du bundle "daily-report", elle se
 * résout toujours, que ce bundle soit installé ou non.
 */
final class SalaryReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'salary-report';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_salary_report');
    }

    public function getDescription(): string
    {
        return __('bundle_salary_report_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'salary-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            SalaryReportRepositoryInterface::class,
            fn() => new DatabaseSalaryReportRepository()
        );
    }
}
