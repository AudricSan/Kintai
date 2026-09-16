<?php

declare(strict_types=1);

namespace kintai\Bundles\SalaryReport;

use kintai\Core\Bundle;
use kintai\Core\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\DatabaseSalaryReportRepository;

/**
 * Comme ResignationReport/ShiftClaim/StorePhoto, aucun autre composant Core
 * ne dépend de SalaryReportRepositoryInterface : le repository est enregistré
 * par ce bundle, pas par le Core. Désactiver "salary-report" retire
 * entièrement la fonctionnalité.
 *
 * Dépendance bundle→bundle : AdminSalaryReportController utilise
 * DailyReportRepositoryInterface (bundle DailyReport) pour pré-remplir le
 * chiffre d'affaires du mois depuis les rapports journaliers validés/soumis
 * (calculateSalaryPreset). Ce bundle suppose donc que "daily-report" est
 * actif ; si désactivé, la création de rapport de salaire échouera à la
 * résolution du container (comportement identique aux autres dépendances
 * bundle→bundle déjà en place pour Messaging/DailyReport).
 */
final class SalaryReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'salary-report';
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
