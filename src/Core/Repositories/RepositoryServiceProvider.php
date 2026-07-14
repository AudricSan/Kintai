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
        $this->container->singleton(IcalTokenRepositoryInterface::class, fn() => new DatabaseIcalTokenRepository());
        // TimeclockRepositoryInterface reste ici (bundle "timeclock" = UI seulement) :
        // HomeController et EmployeeController::dashboard() en dépendent pour leurs
        // widgets "pointage en cours", qui doivent continuer de fonctionner même si
        // ce bundle est désactivé. Voir src/Bundles/Timeclock/TimeclockBundle.php.
        $this->container->singleton(TimeclockRepositoryInterface::class, fn() => new DatabaseTimeclockRepository());
        $this->container->singleton(UserDashboardPrefsRepositoryInterface::class, fn() => new DatabaseUserDashboardPrefsRepository());
        $this->container->singleton(UserNavPrefsRepositoryInterface::class, fn() => new DatabaseUserNavPrefsRepository());
        // ShiftClaim : voir src/Bundles/ShiftClaim/ShiftClaimBundle.php
        $this->container->singleton(NotificationRepositoryInterface::class, fn() => new DatabaseNotificationRepository());
        $this->container->singleton(ApiTokenRepositoryInterface::class, fn() => new DatabaseApiTokenRepository());
        $this->container->singleton(ImportAliasRepositoryInterface::class, fn() => new DatabaseImportAliasRepository());
        $this->container->singleton(PasswordResetRepositoryInterface::class, fn() => new DatabasePasswordResetRepository());
        $this->container->singleton(AppSettingsRepositoryInterface::class, fn() => new DatabaseAppSettingsRepository());
        $this->container->singleton(CronTokenRepositoryInterface::class, fn() => new DatabaseCronTokenRepository());
        $this->container->singleton(LogRepositoryInterface::class, fn() => new DatabaseLogRepository());
        $this->container->singleton(RoleRepositoryInterface::class, fn() => new DatabaseRoleRepository());
        $this->container->singleton(RoleAssignmentRepositoryInterface::class, fn() => new DatabaseRoleAssignmentRepository());

        // Rapports
        // HiringReportRepositoryInterface reste ici (contrairement à Resignation/Salary,
        // pas dans le bundle) : AdminUserController en dépend directement pour générer
        // automatiquement un rapport d'embauche à la création d'un employé. Voir
        // src/Bundles/HiringReport/HiringReportBundle.php.
        $this->container->singleton(HiringReportRepositoryInterface::class, fn() => new DatabaseHiringReportRepository());
        // Démission : voir src/Bundles/ResignationReport/ResignationReportBundle.php
        // Salaire : voir src/Bundles/SalaryReport/SalaryReportBundle.php

        // Photos : voir src/Bundles/StorePhoto/StorePhotoBundle.php
        // Feedback : voir src/Bundles/Feedback/FeedbackBundle.php

        // Traductions (fichiers JSON, voir lang/languages.json et lang/{code}.json). Chaque
        // bundle peut définir ses propres src/Bundles/<Name>/lang/{code}.json, fusionnés
        // par-dessus ceux du Core (fallback Core si le bundle ne redéfinit pas une clé).
        $this->container->singleton(LanguageRepositoryInterface::class, fn() => new JsonLanguageRepository(BASE_PATH . '/lang'));
        $this->container->singleton(TranslationRepositoryInterface::class, fn() => new JsonTranslationRepository(BASE_PATH . '/lang', BASE_PATH . '/src/Bundles'));
    }
}
