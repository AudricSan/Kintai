# 🕒 Kintai

🌐 **English** · [Français](docs/i18n/README.fr.md) · [日本語](docs/i18n/README.ja.md)

**Open-source shift, attendance and workforce management for multi-store businesses.**

[![Version](https://img.shields.io/badge/version-0.10.5-purple.svg)](CHANGELOG.md)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![Tests](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml/badge.svg)](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml)
[![Architecture](https://img.shields.io/badge/architecture-custom%20MVC-orange.svg)]()

Kintai is the bridge between a spreadsheet and an enterprise ERP: scheduling, clock-in/out, leave, shift swaps, daily reports and payroll estimates for retail and hospitality businesses running multiple stores — self-hosted, on your own infrastructure, with your own data.

> **Status: beta (`0.7.9`).** Kintai is functional and used daily in the demo/reference deployment below, but the API and data schema may still evolve before a stable `1.0.0`. See [CHANGELOG.md](CHANGELOG.md).

---

## 🌍 Try it live

**Demo:** [kintai-lv1b.onrender.com](https://kintai-lv1b.onrender.com)
*(Free-tier instance — first load can take 30–60s, data resets on restart.)*

| Role | Email | Password |
| :--- | :--- | :--- |
| Super admin | `admin@kintai.local` | `Admin1234!` |
| Employee | `alice.martin@kintai.local` | `Staff1234!` |

---

## Why Kintai

- **You own your data.** No multi-tenant black box: each deployment is a dedicated instance with its own database. Moving from the hosted demo to your own server is a database dump and a `git clone` away — no vendor lock-in.
- **Built for real multi-store operations.** Store-level timezones, currencies, break rules, understaffing thresholds, and per-store feature toggles — not a single-location tool stretched to fit.
- **Japan-ready, globally usable.** CJK-safe PDF exports, `姓 名` name ordering, JPY/EUR/USD display, and FR/EN/JA translations out of the box — designed around Japanese retail conventions but usable anywhere.
- **No framework tax.** A ~600-file custom PHP 8.3 core (no Laravel, no Symfony) with Eloquent as the only borrowed piece. It runs on a €5 VPS or shared hosting just as well as Docker — see [docs/architecture.md](docs/architecture.md).
- **Actually tested.** 589 PHPUnit tests across the core, repositories and controllers, run on every push via GitHub Actions.

---

## Features

**Scheduling & shifts** — CRUD shifts/shift types, list/calendar/week/day/Gantt-timeline views, drag & drop, bulk actions, print, overlap-conflict detection, open shifts + shift bidding, Excel import with visual analysis and confidence scoring.

**Time & attendance** — Digital clock-in/out with live timer, admin timeclock editing, automatic wage estimation from shifts/breaks/hourly rates/deductions.

**Employee self-service** — Personalized dashboard, weekly availabilities, leave requests, shift swaps, iCal feed, floating feedback widget.

**Communication** — Internal threaded messaging (SSE live updates), notification center with toasts and read/unread state.

**Daily reports** — Store-level KPI reports (sales, customers, labor cost) with a draft → submitted → validated workflow, PDF export (mPDF, CJK fonts), scheduled auto-validation, and email delivery.

**REST API** — Versioned (`/api/v1`), Bearer-token auth, pagination, covering every core resource. Reference: [.wiki/API.md](https://github.com/AudricSan/Kintai/wiki/API) *(wiki being ported from the pre-beta repository)*.

**Operations** — One-click self-update from GitHub Releases (with progress bar and automatic backup/rollback safety net), SQLite/MySQL backup & restore, token-protected cron endpoints for external schedulers.

Full breakdown in [docs/architecture.md](docs/architecture.md) and [docs/database.md](docs/database.md).

---

## Roles & workflows

| Role | Scope |
| :--- | :--- |
| Super admin | All stores, audit log, global configuration |
| Store manager/admin | Planning, staff, leave, swaps, reports and stats for their store(s) |
| Staff | Own schedule, clock-in/out, leave, swaps, messages, feedback |

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

### Docker

```bash
docker build -t kintai .
docker run -p 8080:80 kintai
```

`scripts/docker-setup.php` prepares SQLite, runs migrations, and optionally seeds demo data (`SEED_DEMO_DATA=true`). See the [Dockerfile](Dockerfile) for all environment variables.

### Updating

```bash
php scripts/db-migrate.php --dry-run   # preview pending migrations
php scripts/db-migrate.php             # apply them
```

Owners can also trigger a one-click update from `/admin/backup`, which pulls the latest GitHub Release, backs up the database and code first, and shows live progress.

---

## Tech stack

Custom PHP 8.3 MVC — no Laravel, no Symfony — with `illuminate/database` (Eloquent) as the only ORM dependency. SQLite or MySQL, interchangeable via config. Server-rendered PHP views, modular vanilla CSS/JS, no bundler.

```
public/index.php        → front controller
src/Core/                → framework: DI container, router, middleware, repositories, services
src/Bundles/*/           → self-contained, feature-flagged modules (DailyReport, Messaging)
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

Read [CONTRIBUTING.md](CONTRIBUTING.md) for code conventions (strict types, constructor injection, migration rules) before opening a PR, and [SECURITY.md](SECURITY.md) to report a vulnerability privately rather than via a public issue.

---

## Roadmap

**Next**
- [ ] Visual conflict overlay directly on the scheduling timeline
- [ ] Deeper security audit of POST web routes and exposed API endpoints
- [ ] Targeted tests on payroll, swaps, open shifts and notifications

**Later**
- [ ] Installable PWA with partial offline mode
- [ ] Payroll CSV/XML export
- [ ] Outbound webhooks (Slack, LINE, Teams)
- [ ] Multi-language support for the web interface (currently FR/EN/JA only)
- [ ] Multi-store reporting and analytics dashboard
- [ ] Multi-store scheduling with cross-store shift swaps
- [ ] Multi-store payroll and labor cost optimization
- [ ] Multi-store inventory and sales integration (POS, ERP, e-commerce)
- [ ] Multi-store employee performance tracking and gamification
- [ ] Multi-store compliance and labor law enforcement (overtime, breaks, holidays)
- [ ] Multi-store mobile app for employees and managers (iOS, Android)

Full history in [CHANGELOG.md](CHANGELOG.md).

---

## License

[GNU Affero General Public License v3.0](LICENSE). You're free to use, modify and redistribute Kintai, including commercially — but any modified version you run as a network service must make its source available. See [LICENSE](LICENSE) for the full text.
