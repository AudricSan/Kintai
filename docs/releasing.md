# Cutting a release

🌐 **English** · [Français](i18n/releasing.fr.md) · [日本語](i18n/releasing.ja.md)

This document describes how to publish a new Kintai version on GitHub so that deployed instances can detect and apply it automatically from `/admin/backup` (see `GithubUpdateService`).

## How it works

Auto-update (`GithubUpdateService::checkLatestRelease()`) polls `GET /repos/{GITHUB_UPDATE_REPO}/releases/latest` and downloads the source archive (`zipball_url`) GitHub generates automatically for the release's tag. **Nothing to build or upload manually** — a GitHub Release tagged `vX.Y.Z` on `main` is enough.

Consequences:
- Only **GitHub Releases** count (not bare tags, not commits on `main`). Until a Release exists, `checkLatestRelease()` returns `null`.
- The tag must be created from `main` — that's the branch production instances follow.
- The version number follows [semver](https://semver.org/) (`MAJOR.MINOR.PATCH`), without a `v` prefix in config files (the `v` prefix only exists on the Git tag — `GithubUpdateService` strips it before comparing versions). During the beta phase, versions are `0.0.x-beta`.

## Prerequisites

- [GitHub CLI](https://cli.github.com/) (`gh`) installed and authenticated (`gh auth login`).
- On `main`, up to date with `origin/main`, clean working tree (`git status` empty).
- The `## [Unreleased]` section of `CHANGELOG.md` reflects everything about to ship — it becomes the release notes.

## Automated procedure (recommended)

```powershell
# Preview only, nothing changed or published
.\scripts\release.ps1 -Version 0.1.0-beta -DryRun

# Actual publication
.\scripts\release.ps1 -Version 0.1.0-beta
```

`scripts/release.ps1`:

1. Checks prerequisites (`gh` installed/authenticated, on `main`, clean tree, not behind `origin/main`, tag doesn't already exist).
2. Turns `## [Unreleased]` into `## [0.1.0-beta] - YYYY-MM-DD` in `CHANGELOG.md` and re-adds an empty `## [Unreleased]` above it.
3. Updates the version number in `composer.json` and the default `APP_VERSION` in `config/app.php`.
4. Commits the bump (`chore(release): v0.1.0-beta`), creates the annotated tag `v0.1.0-beta`, pushes the branch and tag.
5. Creates the GitHub Release (`gh release create`) using the content that was under `## [Unreleased]` as notes.

## Manual procedure (if the script can't be used)

1. Check out `main`, up to date:
   ```bash
   git checkout main
   git pull
   ```
2. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [0.1.0-beta] - 2026-07-08` and add a new empty `## [Unreleased]` section right above it.
3. Update the version in `composer.json` (`"version": "0.1.0-beta"`) and `config/app.php` (`env('APP_VERSION', '0.1.0-beta')`).
4. Commit and push:
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "chore(release): v0.1.0-beta"
   git push
   ```
5. Create the tag and release:
   ```bash
   git tag -a v0.1.0-beta -m "Kintai v0.1.0-beta"
   git push origin v0.1.0-beta
   gh release create v0.1.0-beta --title "Kintai v0.1.0-beta" --notes-file notes.txt
   ```
   where `notes.txt` contains the changelog section just added.

## After publishing

- On each instance, the owner sees "Update available" on `/admin/backup` and can click "Update now".
- Before applying anything, the instance automatically creates a database + uploads backup and a code snapshot in `storage/backups/`.
- If `composer.lock` changed, the instance attempts `composer install` automatically (best-effort); on failure or unavailability, a warning prompts a manual run over SSH.

## If something goes wrong

- **Release published by mistake / broken:** `gh release delete vX.Y.Z`, then `git push --delete origin vX.Y.Z` and `git tag -d vX.Y.Z` locally. Instances that already applied the update are **not** rolled back automatically — restore from the DB+uploads backup and code snapshot created in `storage/backups/` just before the update.
- **`gh` not authenticated:** `gh auth login`, then retry.
- **The repo goes private one day:** set `GITHUB_UPDATE_TOKEN` (see `.env.example`) on each instance; `gh release create` already works with local `gh` auth, nothing to change in the script.
