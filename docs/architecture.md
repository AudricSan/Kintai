# Architecture: Kintai

🌐 **English** · [Français](i18n/architecture.fr.md) · [日本語](i18n/architecture.ja.md)

## 🏗 High-Level Design
Kintai follows a modular MVC (Model-View-Controller) architecture, designed for extreme portability and isolation.

### 1. The "Orchestrated Single-Tenant" Model
Kintai is not a shared-infrastructure SaaS.
- **Data Plane:** Each Owner (Tenant) has a dedicated instance of the application and a dedicated database.
- **Control Plane:** A central orchestration layer (Kintai SaaS) manages the lifecycle of these instances (provisioning, updates, backups).

### 2. Core Components
- **Core Application:** Custom PHP 8.3 framework using PSR-12 and SOLID principles.
- **Router:** Custom regex-based router handling Web, API, and Cron routes.
- **Container:** Lightweight Dependency Injection (DI) container for service management.
- **Middleware:** A pipeline for cross-cutting concerns (Auth, I18n, Security).

## 📊 Data Layer (Eloquent ORM)
Kintai uses **Standalone Eloquent** (`illuminate/database`) as its sole ORM.
- **Repositories** wrap Eloquent models to maintain domain decoupling; controllers never use models directly.
- **Drivers:** SQLite (default) and MySQL.
- **Migrations:** PHP-based, unified (`database/migrations/php/`) — no more raw SQL files.
- Legacy `PersistenceDriverInterface` and JsonDB have been removed.

## 🧩 Modular Bundles
Features beyond the always-on core ship as bundles, either still in the monorepo (`src/Bundles/*/`) or distributed independently (see "Distributed bundles" below) — Core is down to Shift, User, and Store plus cross-cutting infrastructure (auth, i18n, notifications, settings, cron, ...). Time Off, Shift Swap, Timeclock, and Daily Report are partial exceptions: `TimeoffRequestRepositoryInterface`, `ShiftSwapRequestRepositoryInterface`, `TimeclockRepositoryInterface`, and `DailyReportRepositoryInterface` all stay Core services (several Core components — store stats, the scheduling service, the admin shift controller, iCal export, `HomeController`, the employee dashboard, `DailyReportNavMiddleware`, the auto-validate cron job — read that data for calculations that must keep working regardless), so disabling or uninstalling any of these four bundles removes its management UI but not the underlying data or those Core calculations. Daily Report goes further than the other three: its permission/PDF/mail/auto-validate services (`DailyReportPermissionService`/`PdfService`/`MailService`/`AutoValidateService`) are Core-bound too (`AppServiceProvider`), not just its repository — found the hard way when extracting it out of the monorepo broke every authenticated page (`DailyReportNavMiddleware` is global middleware, not gated by the bundle at all). The existing per-store `timeclock` feature toggle also gates on the bundle via `AdminStoreController::FEATURE_BUNDLE_MAP`, same as `messages`/`daily_reports`/`photos`. Open Shifts is different: no Core component reads shift-claim data directly, so `ShiftClaimRepositoryInterface` moved into the bundle itself rather than staying a Core service — disabling it removes the feature entirely, data included. Extracting it also meant splitting the six open-shifts/publish/claim methods out of `AdminShiftController` (Core) and `EmployeeController` (Core), which otherwise mixed shift-claim logic into the always-on shift-scheduling controllers. Hiring, resignation, and salary reports used to share one `AdminReportController` with an internal `repo(string $type)` dispatch; that controller is now gone entirely, replaced by three bundle controllers (`AdminResignationReportController`, `AdminSalaryReportController`, `AdminHiringReportController`) sharing the CRUD/PDF logic via a reusable `HasStaffReportCrud` trait. Salary's `calculateSalaryPreset()` reads `DailyReportRepositoryInterface` (Core-bound, see above) to pre-fill the month's sales total. Unlike resignation/salary, `HiringReportRepositoryInterface` stays a Core service instead of moving into its bundle: `AdminUserController` reads/writes it directly to auto-generate a hiring report on every employee creation (standard form and Excel quick-create), a Core user-management workflow that must keep working even when the hiring-report bundle is disabled — disabling it removes only the browse/edit/PDF UI, not that auto-generation or the underlying data. Feedback is a full extraction (like Store Photos): no other Core component reads feedback data, so `FeedbackRepositoryInterface` is registered by the bundle itself. One wrinkle: the feedback submission modal is included directly by the shared `app.php` layout for every employee-facing page (not routed through the bundle's own views), so `AuthMiddleware` shares a `feedback_enabled` flag that the layout checks before including it — otherwise the modal would keep rendering, and POSTing to it would 404, once the bundle is unavailable.

Every view-level bundle gate (`bundle_enabled()`/`feat_bundle()` in `src/Core/helpers.php`, used throughout the nav/sidebar/topbar/bottomnav partials, not just the feedback modal) reads `BundleManager::isActive()` — the bundles actually registered after boot — never `FeatureManager::isEnabled()` alone, which only reflects the stored per-instance setting. The two can disagree when a bundle is enabled in existing settings but no longer present on disk: exactly what happens the moment a bundle is extracted out of the monorepo (Feedback, then Daily Report) while an instance's `app_settings.enabled_bundles` still lists it — every nav partial that unconditionally built a `route_url()` to that bundle's pages broke production the moment `bundle_enabled()` used to fail open on the stale flag. `AuthMiddleware`/`bundle_enabled()` both went through this the hard way; any future bundle extraction inherits the fix for free.
- **Discovery:** `BundleDiscoveryService` merges two sources at boot time — the legacy monorepo scan (`src/Bundles/*/`, every `{Name}\{Name}Bundle` class extending `Bundle`, same PSR-4 convention as always) and dynamically-installed bundles (`storage/bundles/{slug}/{version}/`, resolved from each one's own `bundle.json`, see "Distributed bundles" below). Nothing is hardcoded in a registry for either source; a slug discovered by both (which shouldn't normally happen) resolves to the legacy one, logged as a warning.
- **Stable contract:** `kintai\Core\BundleContract\Bundle` is the frozen, independently-versioned base class a bundle author should extend — a breaking change there is a documented, CHANGELOG-announced event, unlike the rest of `src/Core/`, which can change between minor versions without notice. `kintai\Core\Bundle` (the historical location) was a deprecated compatibility alias; it has been removed ahead of V1.0 now that every monorepo bundle has migrated to extend `kintai\Core\BundleContract\Bundle` directly — a third-party bundle still extending the old alias needs updating.
- **Distributed bundles:** beyond the monorepo pattern above, a bundle can now live in its own Git repository, versioned independently, and be installed from `/admin/bundles/market` without ever touching Kintai's own codebase — the same idea as Home Assistant's add-on repositories. An Owner adds one or more **registries** (`/admin/bundles/registries` — a URL to a static `registry.json` listing, fetched over plain HTTP by `BundleRegistryClient`, never cloned; the official one is seeded by default and can't be removed), then installs/updates a listed bundle through `BundleInstallerService`, which downloads a tagged GitHub Release's zipball (`HttpFetcher`, the same curl → PHP-stream-wrapper fallback as `GithubUpdateService` — never `git clone`/`pull`, since PHP on shared hosting can't be assumed to have `git` available), verifies its `bundle.json` (matching slug, Core version compatibility, entry class actually present) before writing anything, and activates it into `storage/bundles/{slug}/{version}/` — outside `src/Bundles/`, so it never touches this Git repository. A dry-run mode runs every check without installing anything. Bundles migrate out of `src/Bundles/` this way one at a time, each into its own repository (`kintai-bundle-{slug}`) distributed through the official registry — whether a given bundle has moved yet is simply whether it still has a folder under `src/Bundles/`. See `docs/creating-a-bundle.md` for the full guide, aimed at anyone — internal or third-party — writing a bundle this way.
- **Feature Flags:** Whether a discovered bundle actually loads is decided by `FeatureManager`, fed by `LicenseServiceProvider` — a deployment concern, not a licensing one. Owners toggle bundles per-instance from `/admin/bundles` (stored in `app_settings.enabled_bundles`), which falls back to `config/license.php`'s `enabled_features` when unset. Within an enabled bundle, each store can further opt in/out via its own feature toggles (store edit page).
- **Official vs. third-party:** `config/official-bundles.php` lists the slugs of bundles actually developed and maintained by the Kintai project. It's the only source of truth for that distinction — a bundle can't self-declare as official. `/admin/bundles` flags anything discovered but absent from that list as third-party, with a warning that it isn't maintained by the main project.
- **Hook System:** The Core provides extension points for Bundles to inject UI elements, API routes, and logic.

## 🌐 Multi-Tenancy
Multi-tenancy is achieved at the **deployment level**, not the code level.
- **Isolation:** Physical isolation per Owner.
- **Cross-Store Reporting:** Handled natively as all stores belonging to one owner share the same database instance.

## 🖥 Frontend Strategy
- **Server-Side Rendering (SSR):** Using native PHP views for speed, simplicity, and ease of deployment.
- **Vanilla JS & CSS:** No heavy build steps or frontend frameworks, ensuring the application remains lightweight and easy to customize.
- **Mobile First:** Responsive design catering to employees checking schedules on the go.

## 📡 API & Integrations
- **API V1:** A RESTful API allowing integration with third-party tools.
- **iCal:** Personal calendars for employees, secured via tokens.

## ⏱ Ops & CLI Scripts
Beyond `scripts/db-migrate.php` (see [Database Strategy](database.md)) and `scripts/check-translations.php` (see [Contributing](../CONTRIBUTING.md)), a few standalone CLI scripts back recurring maintenance jobs — each boots the full `Application` and is meant to be run headless (cron, a scheduled task, or manually):
- **`photo-retention.php`** — purges store photo uploads past the configurable retention window (`photo_retention_days`/`photo_cleanup_delay` app settings).
- **`consolidate-daily-photo-reports.php`** — retroactively merges same-day/same-store photo submissions into a single report (`--dry-run` supported); see `StorePhotoConsolidationService`.
- **`auto-validate-reports.php`** — auto-validates daily reports left unvalidated past their store's cutoff time; also exposed as a token-protected HTTP endpoint (`/cron/auto-validate`) for external schedulers.
- **`migrate-roles.php`** — backfills `role_assignments` from the legacy `users.is_admin`/`store_user.role` columns (`--dry-run` supported), part of the ongoing RBAC migration.
- **`create-cron-token.php`** — issues a token for the generic cron runner (`/cron/run/{job}`).
- **`seed-demo-data.php`** — generates realistic sample data across every bundle for local testing (`--force` to reseed).
