# Changelog

🌐 **English** · [Français](docs/i18n/CHANGELOG.fr.md) · [日本語](docs/i18n/CHANGELOG.ja.md)

All notable changes to Kintai are documented here.

## [Unreleased]

### Changed
- Moved all translated documents (`*.fr.md`, `*.ja.md`) into `docs/i18n/`, including the ones formerly living next to their English source in `docs/`. `docs/security.md` was renamed to `docs/security-overview.md` to avoid a case-insensitive filename clash with the root `SECURITY.md` translations. `scripts/check-translations.php` now always looks for translations under `docs/i18n/` regardless of the source document's folder.
- `docs/database.md` updated to reflect the actual `illuminate/database` version (^13.19, not ^11.0) and clarified that the `Language`/`Translation` repository interfaces are now bound to JSON-file-backed implementations, not Eloquent — their `Database*Repository` counterparts are unused dead code left over from before the i18n JSON migration.
- Split the combined "Backups & Updates" settings tab into two separate tabs, **Backups** and **Updates**. The GitHub self-update UI (version, pending migrations, one-click update with progress) now lives on its own page at `/admin/update`, while `/admin/backup` only handles create/restore/delete. The underlying `POST /admin/backup/update(/stream)` and `/migrate` routes moved to `/admin/update/apply`, `/admin/update/stream`, and `/admin/update/migrate`.
- `UpdateService::getCurrentVersion()` now reads the installed version straight from `config/app.php` (bumped on each release and synced by the self-update process) instead of tracking a separate `storage/app/version.json` `version` field. That file still records `installed_at`/`updated_at`/`duration_seconds`, but is no longer the source of truth for the app version.

### Fixed
- The installer no longer fails with "Database file at path [...] does not exist." when `storage/app/database.sqlite` is missing (e.g. after an incomplete or deleted previous install) — the file and its directory are now created automatically before the framework boots.

## [0.0.3-beta] - 2026-07-08

### Added
- **Initial public beta.** Full shift scheduling (list/calendar/week/day/Gantt timeline, drag & drop, bulk actions, print, overlap-conflict detection, open shifts + shift bidding, Excel import with visual analysis and confidence scoring); time & attendance (clock-in/out with live timer, admin editing, automatic wage estimation); employee self-service (leave requests, shift swaps, weekly availabilities, iCal feed, feedback widget); internal threaded messaging with live (SSE) updates and a notification center; daily KPI reports with a draft → submitted → validated workflow, PDF export (CJK-safe), scheduled auto-validation and email delivery; a versioned REST API (`/api/v1`, Bearer auth, pagination) covering every resource; one-click self-update from GitHub Releases with progress tracking and automatic backup/rollback; SQLite and MySQL support via a single, driver-agnostic Eloquent migration system; FR/EN/JA translations.