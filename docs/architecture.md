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
Features are split into **Core** (always on) and **Bundles** (`src/Bundles/*/`) — both are part of this AGPL-3.0 repository, nothing is gated behind a separate license. Any feature beyond the always-on core is expected to ship as a bundle: today that's Messaging, Daily Reports, and Store Photos. Time off, shift swaps, open shifts (shift claims), and the advanced HR reports (hiring, resignation, payroll) currently live in Core rather than as bundles — they are candidates for a future migration into their own bundles.
- **Feature Flags:** Bundles are toggled per-deployment via `config/bundles.php` / `config/license.php` (`enabled_features`) — a deployment concern, not a licensing one.
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
