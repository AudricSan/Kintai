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

## Version scheme: X.Y.Z, cascading precision

The version number is always a plain `X.Y.Z` — no prerelease suffix, no week letter:

- **X** — major version. Bumped **by hand**, only for a breaking change. Bumping `X` resets `Y` and `Z` to `0`.
- **Y** — release line. Bumped **by hand**, once, when opening the *first* alpha of a new line — typically right after the previous line shipped to `main`. Bumping `Y` resets `Z` to `0`.
- **Z** — iteration counter within the current `X.Y` line, shared and cumulative across `alpha` and `beta` (never reset between the two channels): the line's first alpha publish is `X.Y.1`, the next alpha or beta publish (whichever comes first) is `X.Y.2`, and so on. **`Z` is computed automatically by `.github/workflows/release.yml`** from the highest existing `vX.Y.*` tag — never entered by hand.
- **`Z = 0` is reserved exclusively for the stable release published on `main`.** Because alpha/beta always start a line at `Z = 1` and only ever count up, `X.Y.0` never collides with an earlier alpha/beta tag from the same line.

Example for line `0.13`:

```
alpha  -> v0.13.1, v0.13.2
beta   -> v0.13.3, v0.13.4   (same counter, picks up where alpha left off)
main   -> v0.13.0            (stable, tagged once the line is ready)
```

The next line starts at `0.14.1` (`Y` bumped by hand, `Z` back to its `0` placeholder in `composer.json`/`config/app.php` until the workflow computes the real first `Z`).

This replaces the previous `X.Y.Z-<week letter><sub-version>` suffix scheme (e.g. `0.12.0-ak23`), which encoded three independent per-channel counters plus an ISO-week letter that was hard to read at a glance on `/admin/update`.

## Which number to bump

Unlike a classic semver MAJOR/MINOR/PATCH, `Z` is never bumped by hand — only `X`/`Y` are, and only in these two situations:

- **Opening the first alpha of a new line** → bump **Y** in `composer.json`/`config/app.php` (leave `Z` at its `.0` placeholder — the real per-publish `Z` is computed by the workflow, never stored here).
- **Breaking change** → bump **X** instead (this also resets `Y` to `0`).
- **Every later alpha or beta publish on that line** → nothing to bump by hand; `composer.json` keeps reading `X.Y.0`, only `CHANGELOG.md`'s `[Unreleased]` section grows.
- **Publishing the stable release on `main`** → nothing to bump either; `composer.json` should already read `X.Y.0` from the line's first alpha. The workflow tags exactly `vX.Y.0`, skipped with a log message if that tag already exists (no version bump happened since the last stable release).

## Publishing a new version (recommended flow)

The base version number (`X.Y` in `composer.json`/`config/app.php`/`CHANGELOG.md`, always written as `X.Y.0`) is still bumped **by hand**, exactly as before, but only when opening a new line (see "Which number to bump" above) — not on every alpha/beta publish. What's automated by `.github/workflows/release.yml` on every push to `alpha`, `beta`, or `main` is *computing `Z` and creating the Git tag + GitHub Release* — you never tag or `gh release create` yourself.

1. On a regular working branch, bump the version if you're opening a new line (see "Manual procedure" below, or run `scripts/release.ps1 -DryRun` to preview the CHANGELOG notes — its automated tag/push/`gh release create` steps are superseded by the Action and will simply fail against a protected branch, so don't run it without `-DryRun` anymore).
2. Open a PR targeting the channel branch you want to publish to (`alpha`, `beta`, or `main`), and merge it once CI is green (required by branch protection).
3. `.github/workflows/release.yml` runs on the resulting push and:
   - reads the `X.Y` line from `composer.json`;
   - on `alpha`/`beta`, finds the highest existing `vX.Y.*` tag, computes `Z+1`, and tags `vX.Y.Z`, marked as a prerelease — every publish produces a distinct, ever-increasing tag;
   - on `main`, tags `vX.Y.0` as a normal (non-prerelease) Release — skipped with a log message if that exact tag already exists (i.e. the line was already shipped stable);
   - extracts the release notes from `CHANGELOG.md` (the dated `## [X.Y.0]` section for `main`, the `## [Unreleased]` section for `alpha`/`beta`).

To promote a version from one channel to the next (alpha → beta → release), merge the corresponding branch forward (e.g. `alpha` into `beta`, then `beta` into `main`) via PR, same as any other branch promotion.

## Manual procedure (bumping the version)

Only needed when opening a new line (or a new major) — see "Which number to bump" above; skip this for every other alpha/beta publish.

1. On a working branch, rename `## [Unreleased]` to `## [0.13.0] - 2026-08-04` in `CHANGELOG.md` (the `X.Y.0` line version — `Z` here is always `0`, the real per-publish `Z` is computed by the workflow) and add a new empty `## [Unreleased]` section right above it.
2. Update the version in `composer.json` (`"version": "0.13.0"`) and `config/app.php` (`env('APP_VERSION', '0.13.0')`), bumping `Y` (or `X` for a breaking change) and resetting the rest to `0`.
3. Commit, push the branch, and open a PR into `alpha` (new lines always start there):
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "core(release): v0.13.0"
   git push -u origin <your-branch>
   gh pr create --base alpha
   ```
4. Once merged, `.github/workflows/release.yml` computes the real `Z` (`1` for this first publish) and creates the tag (`v0.13.1`) and GitHub Release automatically — nothing left to do manually. Every later alpha/beta publish on this line is just a normal PR (no version bump) into `alpha` or `beta`.

## After publishing

- On each instance, the owner sees "Update available" on `/admin/update` (for the channel it's configured to follow) and can click "Update now".
- Before applying anything, the instance automatically creates a database + uploads backup and a code snapshot in `storage/backups/`.
- If `composer.lock` changed, the instance attempts `composer install` automatically (best-effort); on failure or unavailability, a warning prompts a manual run over SSH.

## If something goes wrong

- **Release published by mistake / broken:** `gh release delete vX.Y.Z`, then `git push --delete origin vX.Y.Z` and `git tag -d vX.Y.Z` locally. Instances that already applied the update are **not** rolled back automatically — restore from the DB+uploads backup and code snapshot created in `storage/backups/` just before the update.
- **The Action fails to publish:** check the `Release` workflow run in the Actions tab; the most common cause is a `composer.json` version that doesn't match any `## [...]` section in `CHANGELOG.md` yet — the workflow deliberately fails the build in that case rather than publishing a release with no notes. Add the missing CHANGELOG entry and push again. You can still tag and publish manually with `gh release create` if needed.
- **The repo goes private one day:** set `GITHUB_UPDATE_TOKEN` (see `.env.example`) on each instance so `GithubUpdateService` can keep polling the API; the `Release` workflow itself already has repo access via its built-in `GITHUB_TOKEN`, nothing to change there.
