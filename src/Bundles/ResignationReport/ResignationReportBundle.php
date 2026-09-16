<?php

declare(strict_types=1);

namespace kintai\Bundles\ResignationReport;

use kintai\Core\Bundle;
use kintai\Core\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\DatabaseResignationReportRepository;

/**
 * Comme ShiftClaim/StorePhoto, aucun autre composant Core ne dépend de
 * ResignationReportRepositoryInterface : le repository est enregistré par ce
 * bundle, pas par le Core. Désactiver "resignation-report" retire entièrement
 * la fonctionnalité.
 */
final class ResignationReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'resignation-report';
    }

    public function getLabel(): string
    {
        return __('bundle_resignation_report');
    }

    public function getDescription(): string
    {
        return __('bundle_resignation_report_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'resignation-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            ResignationReportRepositoryInterface::class,
            fn() => new DatabaseResignationReportRepository()
        );
    }
}
