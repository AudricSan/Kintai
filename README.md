# 🕒 Kintai

🌐 **English** · [Français](docs/i18n/README.fr.md) · [日本語](docs/i18n/README.ja.md)

**Open-source shift, attendance and workforce management for multi-store businesses.**

[![Version](https://img.shields.io/badge/version-0.1.1-purple.svg)](CHANGELOG.md)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![Tests](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml/badge.svg)](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml)
[![Architecture](https://img.shields.io/badge/architecture-custom%20MVC-orange.svg)]()

Kintai is the bridge between a spreadsheet and an enterprise ERP: scheduling, clock-in/out, leave, shift swaps, open-shift bidding, daily reports and payroll estimates for retail and hospitality businesses running multiple stores — self-hosted, on your own infrastructure, with your own data.

> **Status: pre-1.0 (`0.1.1`).** Kintai is functional, but the API and data schema may still evolve before a stable `1.0.0`. See [CHANGELOG.md](CHANGELOG.md) and [docs/releasing.md](docs/releasing.md) for the version scheme.

---

## Why Kintai

- **You own your data.** No multi-tenant black box: each deployment is a dedicated instance with its own database. Self-host it on your own server with a `git clone` — no vendor lock-in.
- **Built for real multi-store operations.** Store-level timezones, currencies, break rules, understaffing thresholds, and per-store feature toggles — not a single-location tool stretched to fit.
- **Japan-ready, globally usable.** CJK-safe PDF exports, `姓 名` name ordering, JPY/EUR/USD display, and FR/EN/JA translations out of the box — designed around Japanese retail conventions but usable anywhere.
- **Modular by design.** A lean custom PHP 8.3 core (no Laravel, no Symfony, Eloquent as the only borrowed piece) handles scheduling, users, stores and auth; everything else — daily reports, messaging, shift swaps, payroll — ships as a self-contained, independently toggleable bundle. Disable what you don't need, keep the instance light. See [docs/architecture.md](docs/architecture.md).
- **Actually tested.** 1,500+ PHPUnit tests across the core, bundles, repositories and controllers, run on every push via GitHub Actions.

---

## Features

**Scheduling & shifts** — CRUD shifts/shift types (multi-store shift types via a pivot table), list/calendar/week/day/Gantt-timeline views, drag & drop, bulk actions, print-friendly timeline export, overlap-conflict detection, Excel import with visual analysis and confidence scoring.

**Time & attendance** — Digital clock-in/out with live timer, admin timeclock editing, automatic wage estimation from shifts/breaks/hourly rates/deductions via a single shared calculator.

**Open shifts & shift swaps** — Employees bid on unassigned open shifts or request a direct swap with a colleague; managers approve/reject with automatic conflict checks.

**Leave & availability** — Weekly availability declarations and leave requests, with manager approval workflow.

**Employee self-service** — Personalized dashboard, iCal feed, floating feedback widget, self clock-in/out.

**HR paperwork** — Auto-generated hiring reports on employee creation, resignation/offboarding reports, and salary reports with period autofill and PDF export.

**Store photos** — Weekly sales-floor photo submissions and tracking, with automatic same-day consolidation.

**Communication** — Internal threaded messaging (live updates via SSE), notification center with toasts, read/unread state, and optional push notifications (Firebase Cloud Messaging).

**Daily reports** — Store-level KPI reports (sales, customers, labor cost) with a draft → submitted → validated workflow, PDF export (mPDF, CJK fonts), scheduled auto-validation, and email delivery.

**Access control** — Database-backed roles and permissions (RBAC), assignable per store or globally, checked by dedicated middleware on both web and API routes.

**REST API** — Versioned (`/api/v1`), Bearer-token auth, pagination, covering every core resource plus each bundle's own endpoints. Reference: [.wiki/API-Reference.md](.wiki/API-Reference.md).

**Installable PWA** — Add-to-home-screen support with an offline fallback page and a precached app shell for spotty connections.

**Operations** — One-click self-update from GitHub Releases (with progress bar and automatic backup/rollback safety net), SQLite/MySQL backup & restore, token-protected cron endpoints for external schedulers.

Full breakdown in [docs/architecture.md](docs/architecture.md) and [docs/database.md](docs/database.md).

---

## Roles & workflows

| Role | Scope |
| :--- | :--- |
| Owner | All stores, audit log, global configuration, bundle & role management |
| Store manager/admin | Planning, staff, leave, swaps, reports and stats for their store(s) |
| Staff | Own schedule, clock-in/out, leave, swaps, open shifts, messages, feedback |

Fine-grained permissions on top of these roles are configurable per instance — see the RBAC note above.

Typical flow: shifts created or imported → conflicts checked → employees notified → attendance tracked → daily reports validated → payroll estimated.

---

## Getting started

### Requirements

- PHP 8.3+, Composer 2.x
- `pdo_sqlite` or `pdo_mysql`, `mbstring`, `gd`, `intl`

### Local install

```bash
git clone https://github.com/AudricSan/Kintai.git
cd Kintai
composer install
php -S 127.0.0.1:8000 -t public
```

Then open `http://127.0.0.1:8000/install.php` — the web installer creates `config/database.local.php`, runs migrations, and creates the admin account.

### Updating

```bash
php scripts/db-migrate.php --dry-run   # preview pending migrations
php scripts/db-migrate.php             # apply them
```

Owners can also trigger a one-click update from `/admin/update`, choosing a release channel (stable/beta/alpha); it pulls the matching GitHub Release, backs up the database and code first, and shows live progress.

---

## Tech stack

Custom PHP 8.3 MVC — no Laravel, no Symfony — with `illuminate/database` (Eloquent) as the only ORM dependency. SQLite or MySQL, interchangeable via config. Server-rendered PHP views, modular vanilla CSS/JS, no bundler.

```
public/index.php        → front controller
src/Core/                → framework: DI container, router, middleware, repositories, services
src/Bundles/*/           → self-contained, feature-flagged modules (DailyReport, Messaging, ShiftSwap, TimeOff, ...)
src/UI/                  → Web + API controllers, PHP views
public/assets/           → modular CSS/JS, no build step
database/migrations/php/ → Eloquent migrations, SQLite + MySQL from one schema
```

Deeper dives: [docs/architecture.md](docs/architecture.md) · [docs/database.md](docs/database.md) · [docs/multi-tenancy.md](docs/multi-tenancy.md)

---

## Contributing

Contributions are welcome — bug reports, feature ideas, and pull requests alike.

```bash
composer install
./vendor/bin/phpunit
```

Read [CONTRIBUTING.md](CONTRIBUTING.md) for code conventions (strict types, constructor injection, git workflow) before opening a PR, and [SECURITY.md](SECURITY.md) to report a vulnerability privately rather than via a public issue.

---

## Roadmap

**Next**
- [ ] Native mobile app for employees and managers — the push-notification API (device tokens, Firebase Cloud Messaging relay) is already in place to support it
- [ ] Visual conflict overlay directly on the scheduling timeline
- [ ] Targeted tests on payroll, swaps, open shifts and notifications

**Later**
- [ ] Payroll CSV/XML export
- [ ] Outbound webhooks (Slack, LINE, Teams)
- [ ] Additional web UI languages (currently FR/EN/JA)
- [ ] Multi-store reporting and analytics dashboard

Full history in [CHANGELOG.md](CHANGELOG.md).

---

## License

[GNU Affero General Public License v3.0](LICENSE). You're free to use, modify and redistribute Kintai, including commercially — but any modified version you run as a network service must make its source available. See [LICENSE](LICENSE) for the full text.
