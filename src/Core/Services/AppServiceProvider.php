<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\ServiceProvider;
use kintai\Core\Container;
use kintai\Core\Cron\AutoValidateJob;
use kintai\Core\Cron\BackupJob;
use kintai\Core\Cron\CronRunner;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Repositories\CronTokenRepositoryInterface;
use kintai\Core\Mail\MailerService;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\PasswordResetRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\UI\ViewRenderer;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerMail();
        $this->registerLogging();
        $this->registerBusinessServices();
    }

    private function registerMail(): void
    {
        $this->container->singleton(MailerService::class, function () {
            $path = dirname(dirname(dirname(__DIR__))) . '/config/mail.php';
            $config = file_exists($path) ? require $path : [];
            return new MailerService($config);
        });
    }

    private function registerLogging(): void
    {
        $this->container->singleton(AuditLogger::class, fn() => new AuditLogger());

        Log::setContainer($this->container);
    }

    private function registerBusinessServices(): void
    {
        $this->container->singleton(NotificationService::class, fn(Container $c) => new NotificationService($c->make(NotificationRepositoryInterface::class)));
        
        $this->container->singleton(IcalService::class, fn() => new IcalService());
        
        $this->container->singleton(PasswordResetService::class, fn(Container $c) => new PasswordResetService(
            $c->make(PasswordResetRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(MailerService::class),
        ));

        $this->container->singleton(AppSettingsService::class, fn(Container $c) => new AppSettingsService($c->make(AppSettingsRepositoryInterface::class)));

        $this->container->singleton(ShiftServiceInterface::class, fn(Container $c) => new ShiftService(
            $c->make(ShiftRepositoryInterface::class),
            $c->make(ShiftTypeRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(TimeoffRequestRepositoryInterface::class),
        ));

        $this->container->singleton(StoreServiceInterface::class, fn(Container $c) => new StoreService(
            $c->make(StoreRepositoryInterface::class),
            $c->make(StoreUserRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(LanguageRepositoryInterface::class),
        ));

        $this->container->singleton(StoreStatsServiceInterface::class, fn(Container $c) => new StoreStatsService(
            $c->make(StoreRepositoryInterface::class),
            $c->make(ShiftRepositoryInterface::class),
            $c->make(ShiftTypeRepositoryInterface::class),
            $c->make(StoreUserRepositoryInterface::class),
            $c->make(TimeoffRequestRepositoryInterface::class),
            $c->make(ShiftSwapRequestRepositoryInterface::class),
            $c->make(UserShiftTypeRateRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(DailyReportRepositoryInterface::class),
        ));

        $this->container->singleton(BackupService::class, fn(Container $c) => new BackupService(
            $c->make(Capsule::class),
        ));

        $this->container->singleton(UpdateService::class, fn() => new UpdateService());

        $this->container->singleton(GithubUpdateService::class, fn(Container $c) => new GithubUpdateService(
            $c->make(UpdateService::class),
            $c->make(BackupService::class),
            $c->make(MigrationRunner::class),
            $c->make(AppSettingsService::class),
        ));

        $this->container->singleton(TranslationManagementService::class, fn(Container $c) => new TranslationManagementService(
            $c->make(TranslationRepositoryInterface::class),
        ));

        $this->container->singleton(CronRunner::class, function (Container $c) {
            $runner = new CronRunner($c->make(CronTokenRepositoryInterface::class));
            $runner->register($c->make(AutoValidateJob::class));
            $runner->register($c->make(BackupJob::class));
            return $runner;
        });
    }
}
