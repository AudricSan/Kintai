# Cutting a release

🌐 **English** · [Français](i18n/releasing.fr.md) · [日本語](i18n/releasing.ja.md)

This document describes how to publish a new Kintai version on GitHub so that deployed instances can detect and apply it automatically from `/admin/update` (see `GithubUpdateService`).

## How it works

Auto-update (`GithubUpdateService::checkLatestRelease()`) polls `GET /repos/{GITHUB_UPDATE_REPO}/releases` (the full list, not just the latest one) and downloads the source archive (`zipball_url`) GitHub generates automatically for the selected release's tag. **Nothing to build or upload manually** — a GitHub Release tagged `vX.Y.Z` is enough.

Each instance follows one of three **update channels**, chosen by the Owner on `/admin/update`:
- **Release** — releases published from `main`, not marked prerelease on GitHub.
- **Beta** — releases published from `main` or `beta`.
- **Alpha** — every release, whichever channel it came from.

A release's channel is determined by its `target_commitish` (the source branch, set by `.github/workflows/release.yml` — see `GithubUpdateService::selectReleaseForChannel()`), not by inspecting the tag. Within the releases visible to its channel, the instance picks the highest version (semver-aware). `alpha`, `beta`, and `main` are protected branches (PR + passing CI required, no direct push) — see `.github/workflows/release.yml`.

Consequences:
- Only **GitHub Releases** count (not bare tags, not commits). Until a matching Release exists for the instance's channel, `checkLatestRelease()` returns `null`.
- No `v` prefix in config files (the `v` prefix only exists on the Git tag — `GithubUpdateService` strips it before comparing versions).

## Version scheme: X.Y.Z-\<week letter>\<sub-version>

The version number is `X.Y.Z` for a stable release (no suffix), or `X.Y.Z-LN` for a prerelease (`L` = letter, `N` = number):

- **X** — cumulative count of **stable** releases published on `main`.
- **Y** — cumulative count of **beta** releases published on `beta`.
- **Z** — cumulative count of **alpha** releases published on `alpha`.

Each of the three counters is bumped **by hand**, +1, only on the channel you're publishing to (the other two stay unchanged) — see "Publishing a new version" below.

- **L** — letter for the current ISO week, computed automatically by `.github/workflows/release.yml` (bijective base-26 encoding, spreadsheet-column style: `a` = week 1, `b` = week 2 ... `z` = week 26, `aa` = week 27...).
- **N** — sub-version: a counter reset to 1 at the start of each new ISO week, auto-incremented on every alpha/beta publish during that week (across both channels), derived from the tags already published that week.

`L` and `N` are **never** entered by hand — the workflow computes them at publish time. Only `alpha`/`beta` releases carry this suffix; a stable (`main`) release stays `vX.Y.Z` with no suffix.

## Which counter to bump (X, Y, or Z)

Which counter you bump depends only on the **channel you're publishing to**, not on the scope of the change (unlike a classic semver MAJOR/MINOR/PATCH):

- Publishing to `alpha` → bump **Z** (`config/app.php`/`composer.json`).
- Publishing to `beta` → bump **Y**.
- Publishing to `main` (stable release) → bump **X**.

## Publishing a new version (recommended flow)

The base version number (`X.Y.Z` in `composer.json`/`config/app.php`/`CHANGELOG.md`) is still bumped **by hand**, exactly as before. What's automated by `.github/workflows/release.yml` whenever a bump lands on `alpha`, `beta`, or `main` is *creating the Git tag and GitHub Release* — you no longer tag or `gh release create` yourself.

1. On a regular working branch, bump the version (see "Manual procedure" below, or run `scripts/release.ps1 -DryRun` to preview the CHANGELOG notes — its automated tag/push/`gh release create` steps are superseded by the Action and will simply fail against a protected branch, so don't run it without `-DryRun` anymore).
2. Open a PR targeting the channel branch you want to publish to (`alpha`, `beta`, or `main`), and merge it once CI is green (required by branch protection).
3. `.github/workflows/release.yml` runs on the resulting push and:
   - reads the base version from `composer.json`;
   - on `alpha`/`beta`, computes the week letter and sub-version (see above) and tags `vX.Y.Z-LN`, marked as a prerelease — every publish produces a distinct tag (no more rolling tag rewritten on each push);
   - on `main`, tags `vX.Y.Z` as a normal (non-prerelease) Release — skipped with a log message if that exact tag already exists (i.e. no version bump happened since the last stable release);
   - extracts the release notes from `CHANGELOG.md` (the dated `## [X.Y.Z]` section for `main`, the `## [Unreleased]` section for `alpha`/`beta`).

To promote a version from one channel to the next (alpha → beta → release), merge the corresponding branch forward (e.g. `alpha` into `beta`, then `beta` into `main`) via PR, same as any other branch promotion.

## Manual procedure (bumping the version)

1. On a working branch, rename `## [Unreleased]` to `## [0.11.0] - 2026-08-04` in `CHANGELOG.md` (the base `X.Y.Z`, without the `-LN` suffix the workflow will compute) and add a new empty `## [Unreleased]` section right above it.
2. Update the version in `composer.json` (`"version": "0.11.0"`) and `config/app.php` (`env('APP_VERSION', '0.11.0')`), incrementing only the counter for the channel you're targeting (X for `main`, Y for `beta`, Z for `alpha`).
3. Commit, push the branch, and open a PR into `alpha`, `beta`, or `main` as appropriate:
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "core(release): v0.11.0"
   git push -u origin <your-branch>
   gh pr create --base beta
   ```
4. Once merged, `.github/workflows/release.yml` creates the tag (with the `-LN` suffix on `alpha`/`beta`) and GitHub Release automatically — nothing left to do manually.

## After publishing

- On each instance, the owner sees "Update available" on `/admin/update` (for the channel it's configured to follow) and can click "Update now".
- Before applying anything, the instance automatically creates a database + uploads backup and a code snapshot in `storage/backups/`.
- If `composer.lock` changed, the instance attempts `composer install` automatically (best-effort); on failure or unavailability, a warning prompts a manual run over SSH.

## If something goes wrong

- **Release published by mistake / broken:** `gh release delete vX.Y.Z`, then `git push --delete origin vX.Y.Z` and `git tag -d vX.Y.Z` locally. Instances that already applied the update are **not** rolled back automatically — restore from the DB+uploads backup and code snapshot created in `storage/backups/` just before the update.
- **The Action fails to publish:** check the `Release` workflow run in the Actions tab; the most common cause is a `composer.json` version that doesn't match any `## [...]` section in `CHANGELOG.md` yet — the workflow deliberately fails the build in that case rather than publishing a release with no notes. Add the missing CHANGELOG entry and push again. You can still tag and publish manually with `gh release create` if needed.
- **The repo goes private one day:** set `GITHUB_UPDATE_TOKEN` (see `.env.example`) on each instance so `GithubUpdateService` can keep polling the API; the `Release` workflow itself already has repo access via its built-in `GITHUB_TOKEN`, nothing to change there.
