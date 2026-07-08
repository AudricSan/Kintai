# Changelog

🌐 **English** · [Français](CHANGELOG.fr.md) · [日本語](CHANGELOG.ja.md)

All notable changes to Kintai are documented here.

## [0.0.3-beta] - 2026-07-08

### Added
- **Initial public beta.** Full shift scheduling (list/calendar/week/day/Gantt timeline, drag & drop, bulk actions, print, overlap-conflict detection, open shifts + shift bidding, Excel import with visual analysis and confidence scoring); time & attendance (clock-in/out with live timer, admin editing, automatic wage estimation); employee self-service (leave requests, shift swaps, weekly availabilities, iCal feed, feedback widget); internal threaded messaging with live (SSE) updates and a notification center; daily KPI reports with a draft → submitted → validated workflow, PDF export (CJK-safe), scheduled auto-validation and email delivery; a versioned REST API (`/api/v1`, Bearer auth, pagination) covering every resource; one-click self-update from GitHub Releases with progress tracking and automatic backup/rollback; SQLite and MySQL support via a single, driver-agnostic Eloquent migration system; FR/EN/JA translations.