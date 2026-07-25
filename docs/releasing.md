# Cutting a release

🌐 **English** · [Français](i18n/releasing.fr.md) · [日本語](i18n/releasing.ja.md)

This document describes how to publish a new Kintai version on GitHub so that deployed instances can detect and apply it automatically from `/admin/update` (see `GithubUpdateService`).

## How it works

Auto-update (`GithubUpdateService::checkLatestRelease()`) polls `GET /repos/{GITHUB_UPDATE_REPO}/releases` (the full list, not just the latest one) and downloads the source archive (`zipball_url`) GitHub generates automatically for the selected release's tag. **Nothing to build or upload manually** — a GitHub Release tagged `vX.Y.Z` is enough.

Each instance follows one of three **update channels**, chosen by the Owner on `/admin/update`:
- **Release** — stable tags only (`vX.Y.Z`, not marked prerelease on GitHub). Built from `main`.
- **Beta** — stable tags and `-beta` tags. Built from the `beta` branch.
- **Alpha** — every tag, including `-alpha` ones. Built from the `alpha` branch.

Within the releases visible to its channel, the instance picks the highest version (semver-aware, so `1.0.0-beta.3` < `1.0.0`). `alpha`, `beta`, and `main` are protected branches (PR + passing CI required, no direct push) — see `.github/workflows/release.yml`.

Consequences:
- Only **GitHub Releases** count (not bare tags, not commits). Until a matching Release exists for the instance's channel, `checkLatestRelease()` returns `null`.
- The version number follows [semver](https://semver.org/) (`MAJOR.MINOR.PATCH[-alpha|beta.N]`), without a `v` prefix in config files (the `v` prefix only exists on the Git tag — `GithubUpdateService` strips it before comparing versions).

## Choosing MAJOR vs MINOR vs PATCH

- **PATCH** (`0.7.9` → `0.7.10`): bugfix-only changes — nothing in the bump adds or changes user-facing behavior beyond "it now works as intended".
- **MINOR** (`0.7.9` → `0.8.0`): any release that includes a new feature, even a small one, or a behavior change — bump MINOR even if the same release also bundles fixes. Don't ride a feature in on a PATCH bump just because a fix happened to land the same day.
- **MAJOR**: reserved for breaking changes (unlikely in this app's normal lifecycle, but keep the option open).

If in doubt between PATCH and MINOR, bump MINOR — it costs nothing and keeps the version number meaningful (a stale `0.6.0` on `main` being compared against a `0.7.9` on `beta`, when in fact most of that gap is feature work, undersells how far behind the stable channel actually is).

## Publishing a new version (recommended flow)

The base version number (`X.Y.Z` in `composer.json`/`config/app.php`/`CHANGELOG.md`) is still bumped **by hand**, exactly as before. What changed is *who creates the Git tag and GitHub Release*: that part is now automated by `.github/workflows/release.yml` whenever a bump lands on `alpha`, `beta`, or `main` — you no longer tag or `gh release create` yourself.

1. On a regular working branch, bump the version (see "Manual procedure" below, or run `scripts/release.ps1 -DryRun` to preview the CHANGELOG notes — its automated tag/push/`gh release create` steps are superseded by the Action and will simply fail against a protected branch, so don't run it without `-DryRun` anymore).
2. Open a PR targeting the channel branch you want to publish to (`alpha`, `beta`, or `main`), and merge it once CI is green (required by branch protection).
3. `.github/workflows/release.yml` runs on the resulting push and:
   - reads the base version from `composer.json`;
   - on `alpha`/`beta`, tags `vX.Y.Z-{channel}` — a **rolling tag**: if that tag/Release already exists (e.g. a follow-up push on the same `X.Y.Z` still in progress), it's deleted and recreated pointing at the new commit, instead of accumulating `vX.Y.Z-{channel}.1`, `.2`, `.3`... Marked as a prerelease. **Fails the build** if `vX.Y.Z` (no suffix) already exists as a stable Release — publishing a prerelease with the same base version as an already-shipped stable one would be semver-*lower* than that stable tag and therefore invisible to the channel; bump the base version first;
   - on `main`, tags `vX.Y.Z` as a normal (non-prerelease) Release — skipped with a log message if that exact tag already exists (i.e. no version bump happened since the last stable release);
   - extracts the release notes from `CHANGELOG.md` (the dated `## [X.Y.Z]` section for `main`, the `## [Unreleased]` section for `alpha`/`beta`).

To promote a version from one channel to the next (alpha → beta → release), merge the corresponding branch forward (e.g. `alpha` into `beta`, then `beta` into `main`) via PR, same as any other branch promotion.

## Manual procedure (bumping the version)

1. On a working branch, rename `## [Unreleased]` to `## [0.1.0-beta] - 2026-07-08` in `CHANGELOG.md` and add a new empty `## [Unreleased]` section right above it.
2. Update the version in `composer.json` (`"version": "0.1.0-beta"`) and `config/app.php` (`env('APP_VERSION', '0.1.0-beta')`).
3. Commit, push the branch, and open a PR into `alpha`, `beta`, or `main` as appropriate:
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "chore(release): v0.1.0-beta"
   git push -u origin <your-branch>
   gh pr create --base beta
   ```
4. Once merged, `.github/workflows/release.yml` creates the tag and GitHub Release automatically — nothing left to do manually.

## After publishing

- On each instance, the owner sees "Update available" on `/admin/update` (for the channel it's configured to follow) and can click "Update now".
- Before applying anything, the instance automatically creates a database + uploads backup and a code snapshot in `storage/backups/`.
- If `composer.lock` changed, the instance attempts `composer install` automatically (best-effort); on failure or unavailability, a warning prompts a manual run over SSH.

## If something goes wrong

- **Release published by mistake / broken:** `gh release delete vX.Y.Z`, then `git push --delete origin vX.Y.Z` and `git tag -d vX.Y.Z` locally. Instances that already applied the update are **not** rolled back automatically — restore from the DB+uploads backup and code snapshot created in `storage/backups/` just before the update.
- **The Action fails to publish:** check the `Release` workflow run in the Actions tab; the most common cause is a `composer.json` version that doesn't match any `## [...]` section in `CHANGELOG.md` yet — the workflow deliberately fails the build in that case rather than publishing a release with no notes. Add the missing CHANGELOG entry and push again. You can still tag and publish manually with `gh release create` if needed.
- **The repo goes private one day:** set `GITHUB_UPDATE_TOKEN` (see `.env.example`) on each instance so `GithubUpdateService` can keep polling the API; the `Release` workflow itself already has repo access via its built-in `GITHUB_TOKEN`, nothing to change there.
