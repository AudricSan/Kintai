# Changelog

All notable changes to Kintai are documented here.

## [1.0.1] — 2026-03-29

### Added
- Demo seeder accounts not enabled by default (`SEED_DEMO_DATA=false`) — 1 admin + 6 staff users created on first boot
- `render.yaml`: new `SEED_DEMO_DATA` environment variable to toggle demo seeding on/off
- `DEMO_SETUP.md` documenting all demo credentials and seeder configuration
- README: new "Comptes de Démonstration" section listing all seeder accounts with credentials

### Fixed
- Demo account password hashes corrected across all three drivers (SQLite, MySQL, JsonDB)
- `render.yaml`: admin environment variables (`ADMIN_*`) restored alongside `SEED_DEMO_DATA`

## [1.0.0] — 2026-03-20

### Added
- Multi-store shift management (create, edit, delete, bulk actions)
- Visual calendar views: monthly, weekly, timeline
- Printable timeline (A4)
- Shift conflict detection and bulk resolution
- Employee space: dashboard, shift history, availability, swap requests, time-off requests
- Shift swap workflow (propose, accept, approve)
- Time-off request workflow (submit, approve, reject)
- Excel shift import (4-phase pipeline: visual detection, business qualification, deduplication)
- PDF payslip generation (mPDF) with deductions
- Employee reports and statistics
- Store statistics with KPI dashboard
- Audit log (full traceability of all actions)
- Internationalization: French, English, Japanese
- Mobile-optimized interface with automatic device detection
- Multi-driver persistence: SQLite, MySQL, JsonDB
- Custom MVC framework (PHP 8.3+, no Laravel/Symfony)
- REST API for all resources
- Web installer (WordPress-style setup)
