<?php

declare(strict_types=1);

namespace kintai\Bundles\DailyReport;

use kintai\Core\Bundle;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\DatabaseDailyReportRepository;
use kintai\Core\Services\DailyReportAutoValidateService;
use kintai\Core\Services\DailyReportMailService;
use kintai\Core\Services\DailyReportPdfService;
use kintai\Core\Services\DailyReportPermissionService;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Mail\MailerService;
use kintai\UI\ViewRenderer;
use kintai\Core\Services\TranslationService;
use kintai\Core\Container;

final class DailyReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'daily-report';
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'daily-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            DailyReportRepositoryInterface::class,
            fn() => new DatabaseDailyReportRepository()
        );

        $container->singleton(DailyReportPermissionService::class, fn() => new DailyReportPermissionService());
        
        $container->singleton(DailyReportPdfService::class, fn(Container $c) => new DailyReportPdfService(
            $c->make(ViewRenderer::class),
            $c->make(TranslationService::class),
            $c->make(ShiftRepositoryInterface::class),
            $c->make(ShiftTypeRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
        ));

        $container->singleton(DailyReportMailService::class, fn(Container $c) => new DailyReportMailService(
            $c->make(DailyReportPermissionService::class),
            $c->make(MailerService::class),
            $c->make(TranslationService::class),
        ));

        $container->singleton(DailyReportAutoValidateService::class, fn(Container $c) => new DailyReportAutoValidateService(
            $c->make(StoreRepositoryInterface::class),
            $c->make(DailyReportRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(DailyReportPermissionService::class),
            $c->make(DailyReportPdfService::class),
            $c->make(DailyReportMailService::class),
        ));
    }
}
