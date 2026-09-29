<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport;

use kintai\Core\BundleContract\Bundle;

/**
 * Le repository et les services métier (permission, PDF, mail, auto-validate)
 * sont liés par Kintai Core (RepositoryServiceProvider/AppServiceProvider), pas
 * par ce bundle : plusieurs composants Core (DailyReportNavMiddleware,
 * AutoValidateJob/CronController) en dépendent directement et doivent
 * continuer de fonctionner même si ce bundle est désactivé ou désinstallé —
 * même exception que Timeoff/ShiftSwap/Timeclock (voir docs/architecture.md
 * "Modular Bundles" côté Kintai).
 */
final class DailyReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'daily-report';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_daily_report');
    }

    public function getDescription(): string
    {
        return __('bundle_daily_report_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'daily-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
