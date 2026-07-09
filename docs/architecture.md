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
Features are split into **Core** (always on) and **Bundles** (`src/Bundles/*/`) — both are part of this AGPL-3.0 repository, nothing is gated behind a separate license. Any feature beyond the always-on core is expected to ship as a bundle: today that's Messaging, Daily Reports, Store Photos, Time Off, Shift Swaps, Open Shifts (shift claims), the Resignation Report, the Salary Report, the Hiring Report, Feedback, and Timeclock — Core is now down to Shift, User, and Store plus cross-cutting infrastructure (auth, i18n, notifications, settings, cron, ...). Time Off, Shift Swap, and Timeclock are partial exceptions: `TimeoffRequestRepositoryInterface`, `ShiftSwapRequestRepositoryInterface`, and `TimeclockRepositoryInterface` all stay Core services (several Core components — store stats, the scheduling service, the admin shift controller, iCal export, `HomeController`, the employee dashboard — read that data for calculations that must keep working regardless), so disabling any of these three bundles removes its management UI but not the underlying data or those Core calculations. The existing per-store `timeclock` feature toggle now also gates on the bundle via `AdminStoreController::FEATURE_BUNDLE_MAP`, same as `messages`/`daily_reports`/`photos`. Open Shifts is different: no Core component reads shift-claim data directly, so `ShiftClaimRepositoryInterface` moved into the bundle itself rather than staying a Core service — disabling it removes the feature entirely, data included. Extracting it also meant splitting the six open-shifts/publish/claim methods out of `AdminShiftController` (Core) and `EmployeeController` (Core), which otherwise mixed shift-claim logic into the always-on shift-scheduling controllers. Hiring, resignation, and salary reports used to share one `AdminReportController` with an internal `repo(string $type)` dispatch; that controller is now gone entirely, replaced by three bundle controllers (`AdminResignationReportController`, `AdminSalaryReportController`, `AdminHiringReportController`) sharing the CRUD/PDF logic via a reusable `HasStaffReportCrud` trait. Salary's `calculateSalaryPreset()` reads `DailyReportRepositoryInterface` (the DailyReport bundle) to pre-fill the month's sales total — a bundle-to-bundle dependency, so salary-report creation assumes daily-report stays enabled. Unlike resignation/salary, `HiringReportRepositoryInterface` stays a Core service instead of moving into its bundle: `AdminUserController` reads/writes it directly to auto-generate a hiring report on every employee creation (standard form and Excel quick-create), a Core user-management workflow that must keep working even when the hiring-report bundle is disabled — disabling it removes only the browse/edit/PDF UI, not that auto-generation or the underlying data. Feedback is a full extraction (like Store Photos): no other Core component reads feedback data, so `FeedbackRepositoryInterface` is registered by the bundle itself. One wrinkle: the feedback submission modal is included directly by the shared `app.php` layout for every employee-facing page (not routed through the bundle's own views), so `AuthMiddleware` shares a `feedback_enabled` flag (from `FeatureManager`) that the layout checks before including it — otherwise the modal would keep rendering, and POSTing to it would 404, once the bundle is disabled.
- **Discovery:** `BundleDiscoveryService` scans `src/Bundles/*/` at boot time and finds every `{Name}\{Name}Bundle` class that extends `Bundle` — nothing is hardcoded in a registry, so a third-party bundle only needs to be dropped into `src/Bundles/` (same PSR-4 convention: `kintai\Bundles\{Name}\{Name}Bundle`) to be picked up, no core code change required.
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
