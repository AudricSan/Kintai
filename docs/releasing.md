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

## `develop`: the non-release integration branch

`.github/workflows/release.yml` only triggers on a push to `alpha`, `beta`, or `main` — nothing else. `develop` is a fourth long-lived branch, based on `alpha`, that sits outside that trigger on purpose: it's where day-to-day feature/fix branches merge by default (see [CONTRIBUTING.md](../CONTRIBUTING.md)), so a normal batch of PRs during a work session never tags and publishes a release. It isn't a protected release-channel branch — no branch protection, no CI-gated merge requirement beyond what the team chooses to enforce by convention.

When a batch of work on `develop` is ready to actually ship, promote it forward with an ordinary PR from `develop` into `alpha` (same "merge the branch forward" mechanic described below for alpha → beta → main) — *that* merge is what starts the release cascade. `develop` itself never gets a Git tag or a GitHub Release.

Consequences:
- Only **GitHub Releases** count (not bare tags, not commits). Until a matching Release exists for the instance's channel, `checkLatestRelease()` returns `null`.
- No `v` prefix in config files (the `v` prefix only exists on the Git tag — `GithubUpdateService` strips it before comparing versions).

## Version scheme: X.Y.Z, one digit per channel

The version number is always a plain `X.Y.Z` — no prerelease suffix, no week letter. **Each channel owns exactly one digit** and resets the ones to its right:

| Channel | Publishing on it… | Effect | Example |
|---|---|---|---|
| `alpha` | increments **Z** | `X.Y.Z` → `X.Y.(Z+1)` | `0.3.0` → `0.3.1` |
| `beta` | increments **Y**, resets `Z` | `X.Y.Z` → `X.(Y+1).0` | `0.3.4` → `0.4.0` |
| `main` | increments **X**, resets `Y` and `Z` | `X.Y.Z` → `(X+1).0.0` | `0.4.2` → `1.0.0` |

So **only `main` can change the first digit, only `beta` the second, only `alpha` the third.** Nothing is ever entered by hand: `.github/workflows/release.yml` takes the **highest existing `vX.Y.Z` tag** (whichever channel published it), applies the rule of the channel being published, and creates the Git tag + GitHub Release. With no tag at all it starts from `0.0.0`.

Example, starting from `v0.3.0`:

```
alpha  -> v0.3.1, v0.3.2, v0.3.3
beta   -> v0.4.0                  (Y + 1, Z back to 0)
alpha  -> v0.4.1                  (the counter keeps going from the highest tag)
main   -> v1.0.0                  (X + 1, Y and Z back to 0)
```

`config/app.php`'s `version` field is a plain string literal (no environment-variable indirection) and it is **what the running Core reports** (`UpdateService::getCurrentVersion()`), including for the bundle installer's `kintai_core.min` check. Nobody bumps it by hand: on each release, `.github/workflows/release.yml` runs `scripts/set-release-version.php` with the computed version and records it in `config/app.php` of the commit the tag points to, so the release archive (and therefore a fresh install from it) reports its real version. That commit is referenced **only by the tag** — it is never pushed to the protected channel branch — so the literal on `develop`/`alpha`/`beta`/`main` stays a baseline that lags behind the latest tag. Once an instance applies a self-update, `GithubUpdateService::applyUpdate()` rewrites the same field with the exact tag it just applied. This is the only file that tracks the version; there's no separate `storage/app/version.json`. Keep the field a plain literal: the script refuses to publish if it can't rewrite it.

This replaces the previous scheme (`Y` bumped by hand, a shared cumulative `Z`, `Z = 0` reserved for `main`) and, before it, the `X.Y.Z-<week letter><sub-version>` suffix scheme (e.g. `0.12.0-ak23`).

## Publishing a new version (recommended flow)

There is no version to bump: promoting a branch is what decides which digit moves. What's automated by `.github/workflows/release.yml` on every push to `alpha`, `beta`, or `main` is *computing the next version and creating the Git tag + GitHub Release* — you never tag or `gh release create` yourself.

1. Add the changes to `CHANGELOG.md` under `## [Unreleased]` on your working branch (as for any change).
2. Open a PR targeting the channel branch you want to publish to (`alpha`, `beta`, or `main`), and merge it once CI is green (required by branch protection).
3. `.github/workflows/release.yml` runs on the resulting push and:
   - finds the highest existing `vX.Y.Z` tag;
   - applies the channel's rule from the table above and tags the result — marked as a prerelease on `alpha`/`beta`, as a normal Release on `main`;
   - extracts the release notes from `CHANGELOG.md` (the `## [Unreleased]` section, or, if it is empty, the `## [X.Y.Z]` section of the version just computed).

To promote a version forward (develop → alpha → beta → release), merge the corresponding branch forward (e.g. `develop` into `alpha`, then `alpha` into `beta`, then `beta` into `main`) via PR, same as any other branch promotion. Only the `alpha`/`beta`/`main` steps actually publish a release — merging into `develop` itself never does (see "`develop`: the non-release integration branch" above). Remember that each promotion moves a digit: a `beta` publish bumps `Y`, a `main` publish bumps `X`.
## After publishing

- On each instance, the owner sees "Update available" on `/admin/update` (for the channel it's configured to follow) and can click "Update now".
- Before applying anything, the instance automatically creates a database + uploads backup and a code snapshot in `storage/backups/`.
- If `composer.lock` changed, the instance attempts `composer install` automatically (best-effort); on failure or unavailability, a warning prompts a manual run over SSH.

## If something goes wrong

- **Release published by mistake / broken:** `gh release delete vX.Y.Z`, then `git push --delete origin vX.Y.Z` and `git tag -d vX.Y.Z` locally. Instances that already applied the update are **not** rolled back automatically — restore from the DB+uploads backup and code snapshot created in `storage/backups/` just before the update.
- **The Action fails to publish:** check the `Release` workflow run in the Actions tab; the most common cause is an empty `## [Unreleased]` section in `CHANGELOG.md` with no `## [X.Y.Z]` section for the computed version either — the workflow deliberately fails the build in that case rather than publishing a release with no notes. Add the missing CHANGELOG entry and push again. You can still tag and publish manually with `gh release create` if needed.
- **The repo goes private one day:** set `GITHUB_UPDATE_TOKEN` (see `.env.example`) on each instance so `GithubUpdateService` can keep polling the API; the `Release` workflow itself already has repo access via its built-in `GITHUB_TOKEN`, nothing to change there.
