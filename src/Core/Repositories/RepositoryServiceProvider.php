<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Core\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(UserRepositoryInterface::class, fn() => new DatabaseUserRepository());
        $this->container->singleton(StoreRepositoryInterface::class, fn() => new DatabaseStoreRepository());
        $this->container->singleton(StoreUserRepositoryInterface::class, fn() => new DatabaseStoreUserRepository());
        $this->container->singleton(ShiftTypeRepositoryInterface::class, fn() => new DatabaseShiftTypeRepository());
        $this->container->singleton(ShiftRepositoryInterface::class, fn() => new DatabaseShiftRepository());
        $this->container->singleton(AvailabilityRepositoryInterface::class, fn() => new DatabaseAvailabilityRepository());
        $this->container->singleton(TimeoffRequestRepositoryInterface::class, fn() => new DatabaseTimeoffRequestRepository());
        $this->container->singleton(ShiftSwapRequestRepositoryInterface::class, fn() => new DatabaseShiftSwapRequestRepository());
        $this->container->singleton(UserShiftTypeRateRepositoryInterface::class, fn() => new DatabaseUserShiftTypeRateRepository());
        $this->container->singleton(FeedbackRepositoryInterface::class, fn() => new DatabaseFeedbackRepository());
        $this->container->singleton(IcalTokenRepositoryInterface::class, fn() => new DatabaseIcalTokenRepository());
        $this->container->singleton(TimeclockRepositoryInterface::class, fn() => new DatabaseTimeclockRepository());
        $this->container->singleton(UserDashboardPrefsRepositoryInterface::class, fn() => new DatabaseUserDashboardPrefsRepository());
        $this->container->singleton(UserNavPrefsRepositoryInterface::class, fn() => new DatabaseUserNavPrefsRepository());
        $this->container->singleton(ShiftClaimRepositoryInterface::class, fn() => new DatabaseShiftClaimRepository());
        $this->container->singleton(NotificationRepositoryInterface::class, fn() => new DatabaseNotificationRepository());
        $this->container->singleton(ApiTokenRepositoryInterface::class, fn() => new DatabaseApiTokenRepository());
        $this->container->singleton(ImportAliasRepositoryInterface::class, fn() => new DatabaseImportAliasRepository());
        $this->container->singleton(PasswordResetRepositoryInterface::class, fn() => new DatabasePasswordResetRepository());
        $this->container->singleton(AppSettingsRepositoryInterface::class, fn() => new DatabaseAppSettingsRepository());
        $this->container->singleton(CronTokenRepositoryInterface::class, fn() => new DatabaseCronTokenRepository());
        $this->container->singleton(LogRepositoryInterface::class, fn() => new DatabaseLogRepository());

        // Rapports
        $this->container->singleton(HiringReportRepositoryInterface::class, fn() => new DatabaseHiringReportRepository());
        $this->container->singleton(ResignationReportRepositoryInterface::class, fn() => new DatabaseResignationReportRepository());
        $this->container->singleton(SalaryReportRepositoryInterface::class, fn() => new DatabaseSalaryReportRepository());

        // Photos : voir src/Bundles/StorePhoto/StorePhotoBundle.php

        // Traductions (fichiers JSON, voir lang/languages.json et lang/{code}.json)
        $this->container->singleton(LanguageRepositoryInterface::class, fn() => new JsonLanguageRepository(BASE_PATH . '/lang'));
        $this->container->singleton(TranslationRepositoryInterface::class, fn() => new JsonTranslationRepository(BASE_PATH . '/lang'));
    }
}
