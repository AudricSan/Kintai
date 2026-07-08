# Database Strategy: Kintai

🌐 **English** · [Français](i18n/database.fr.md) · [日本語](i18n/database.ja.md)

## 📊 Overview
Kintai uses **Eloquent ORM** (`illuminate/database` ^13.19) as its sole data access layer. Supports SQLite (default, zero-config) and MySQL/MariaDB (production scale) — driver-agnostic, selected via `config/database.php`.

## 🛤 Eloquent ORM

### Initialization
Bootstrapped by `DatabaseServiceProvider` (`src/Core/Database/DatabaseServiceProvider.php`) via `Illuminate\Database\Capsule\Manager`.

### Models (`src/Domain/Eloquent/`)
36 `final` Eloquent models, all following the same pattern: `$guarded = []`, `$timestamps = false`, no behavior beyond relationships/casts. Grouped by domain:

- **Tenancy:** `User`, `Store`, `StoreUser`
- **Scheduling:** `Shift`, `ShiftType`, `Availability`, `ShiftClaim`, `ShiftSwapRequest`
- **Time & pay:** `Timeclock`, `TimeoffRequest`, `UserShiftTypeRate`, `StoreDeductionSetting`
- **Daily reports & HR:** `DailyReport`, `HiringReport`, `ResignationReport`, `SalaryReport`, `Feedback`
- **Communication:** `MessageThread`, `ThreadMessage`, `ThreadParticipant`, `Notification`
- **System & settings:** `ActivityEntry`, `AppSetting`, `ApiToken`, `CronToken`, `IcalToken`, `PasswordResetToken`, `ImportAlias`, `StoreFeature`, `StoreImportSetting`, `StorePhotoImage`, `StorePhotoSubmission`, `UserDashboardPref`, `UserNavPref`
- **i18n:** `Language`, `Translation` — legacy models, kept for reference but no longer read at runtime: translations are served from `lang/*.json` via `JsonLanguageRepository`/`JsonTranslationRepository`.

The full, current list is always the source of truth: `ls src/Domain/Eloquent/`.

### Repository Pattern
30 repository interfaces in `src/Core/Repositories/`, bound to their implementation in `RepositoryServiceProvider`. Controllers and services must **never** use Eloquent models directly — only via injected repository interfaces. Almost all implementations wrap Eloquent models; the `Language`/`Translation` interfaces are the exception, bound to JSON-file-backed repositories instead (their `Database*Repository` counterparts still exist on disk but are unused dead code, predating the JSON migration).

## 🔄 Migration System
PHP-based, unified — one migration file covers both SQLite and MySQL, no raw SQL and no per-driver duplication.

- **Location:** `database/migrations/php/` (41 migrations)
- **Runner:** `php scripts/db-migrate.php` (`--dry-run` to preview)
- **Base class:** `kintai\Core\Database\Migration`
- **Idempotency:** guarded with `$this->schema()->hasTable()` — safe to run repeatedly

## 🗄 Supported Drivers
- **SQLite:** Default. Zero-config, file-based — good fit for a single-tenant deployment.
- **MySQL / MariaDB:** For higher write concurrency or existing hosting infrastructure. Note: SQLite here does not enforce `ON DELETE CASCADE` — dependent rows are deleted explicitly in repository code, so behavior stays consistent across both drivers.

## 💾 Backup & Portability
- Every instance can trigger a full SQL dump (`BackupService`) covering both database and uploaded files.
- Because each tenant is a single, self-contained database, moving to self-hosting is just the DB dump + the Kintai source — no extraction step needed.
