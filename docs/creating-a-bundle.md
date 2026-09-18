# Creating a bundle

🌐 **English** · [Français](i18n/creating-a-bundle.fr.md) · [日本語](i18n/creating-a-bundle.ja.md)

This document is for anyone writing a Kintai bundle meant to be **distributed independently** — its own Git repository, its own version history, installable from `/admin/bundles/market` without ever touching Kintai's own codebase. It doesn't cover the older, still-supported pattern of dropping a bundle directly into `src/Bundles/` inside the main repo (see `docs/architecture.md` → "Modular Bundles" for that) — that path still works for the 11 bundles that ship with Kintai today, but a new bundle, especially a third-party one, should use the model described here.

`kintai-bundle-feedback` (the "Feedback" bundle, extracted from Kintai's own monorepo as the pilot for this whole mechanism) is a complete, real-world example — worth keeping open in a second tab while reading this.

## Why a separate repository, and why no `git clone`

Kintai never runs `git clone`/`git pull` to fetch a bundle — the installer (`BundleInstallerService`) downloads a **tagged GitHub Release's zipball** over plain HTTP (with a curl → PHP stream-wrapper fallback, see `HttpFetcher`), the exact same mechanism Kintai already uses to update itself (`GithubUpdateService`). This is deliberate: a lot of shared hosting (the kind Kintai targets) doesn't guarantee a `git` binary is reachable from PHP, but an outbound HTTPS request always works.

Consequences that shape everything below:
- Your bundle needs **at least one GitHub Release tagged `vX.Y.Z`** — a bare tag isn't enough, `zipball_url` only exists on an actual Release.
- The zipball's single root directory becomes the bundle's installed root — whatever GitHub names it (`{owner}-{repo}-{sha}`) doesn't matter, only what's inside does.
- Nothing needs building or uploading as a release asset: GitHub computes `zipball_url` automatically from the tagged commit.

## Required layout

```
your-bundle/
  bundle.json                 # manifest — see below, required
  src/
    YourBundle.php            # entry class, extends kintai\Core\BundleContract\Bundle
    Controllers/Web/...
    Controllers/Api/...
  Views/                      # optional — loaded via loadViewsFrom()
  lang/{en,fr,ja}.json         # optional — bundle-specific translation keys
  routes.php                  # optional — loaded via loadRoutesFrom()
  README.md
  LICENSE
```

Two things are easy to get wrong here:
- **Only your entry class (and anything under the same namespace) goes under `src/`.** `routes.php`, `Views/`, and `lang/` live at the bundle's root, as siblings of `src/` — not inside it. This mirrors `bundle.json`'s `namespace` field, which is a PSR-4 root for `src/` only.
- `Bundle::getPath()` returns the bundle's **root** (the directory containing `bundle.json`), regardless of which of the two directories your class file happens to live in — `loadRoutesFrom($this->getPath() . '/routes.php')` and `loadViewsFrom($this->getPath() . '/Views', 'your-namespace')` both resolve correctly as long as you follow the layout above.

## The stable contract: `kintai\Core\BundleContract\Bundle`

Your entry class extends this abstract class — the one part of `kintai\Core\*` that follows its own semantic versioning, announced in the CHANGELOG when it changes (adding an optional method is minor; changing or removing one is a documented major change). Nothing else under `kintai\Core\` comes with that guarantee.

```php
abstract class Bundle
{
    abstract public function getName(): string;      // slug — must match bundle.json's "slug"
    abstract public function register(): void;        // wire services, routes, views

    public function getVersion(): string;              // must match bundle.json's "version"
    public function getLabel(): string;                 // shown in /admin/bundles(/market)
    public function getDescription(): string;
    public function boot(): void;                       // runs once every bundle is registered
    public function getPath(): string;                  // bundle root, see above

    protected function loadRoutesFrom(string $path): void;
    protected function loadViewsFrom(string $path, string $namespace): void;
}
```

A minimal, real example (`kintai-bundle-feedback`'s actual entry class, trimmed):

```php
<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Feedback;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\DatabaseFeedbackRepository;

final class FeedbackBundle extends Bundle
{
    public function getName(): string { return 'feedback'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getLabel(): string { return __('bundle_feedback'); }
    public function getDescription(): string { return __('bundle_feedback_desc'); }

    public function register(): void
    {
        $this->app->container()->singleton(
            FeedbackRepositoryInterface::class,
            fn() => new DatabaseFeedbackRepository()
        );
        $this->loadViewsFrom($this->getPath() . '/Views', 'feedback');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
```

Your namespace can be anything — by convention, installed bundles use `kintai\Bundles\Installed\{Name}\`, deliberately distinct from the legacy monorepo convention (`kintai\Bundles\{Name}\`) so the two autoloading mechanisms (Composer's own PSR-4 for `src/Bundles/`, and a dedicated `spl_autoload_register` for anything under `storage/bundles/`) can never collide on the same class name.

**A repository and interface pair from `src/Core/Repositories/*Interface.php` is also part of the stable surface** you're allowed to depend on (e.g. `FeedbackRepositoryInterface` above) — those are already a stable contract by construction, unrelated to `BundleContract`.

## The `bundle.json` manifest

Required at the bundle's repository root:

```json
{
    "slug": "feedback",
    "name": "Retours utilisateurs",
    "version": "1.0.0",
    "description": "Employee feedback (bugs, suggestions) — submission modal, admin list and deletion.",
    "author": { "name": "Your Name", "email": "you@example.com", "url": "https://example.com" },
    "kintai_core": { "min": "0.1.0", "max": "999.999.999" },
    "requires_bundles": {},
    "namespace": "kintai\\Bundles\\Installed\\Feedback",
    "entry_class": "kintai\\Bundles\\Installed\\Feedback\\FeedbackBundle",
    "license": "AGPL-3.0-only",
    "homepage": "https://github.com/you/your-bundle"
}
```

| Field | Required | Notes |
|---|---|---|
| `slug` | Yes | Must equal `getName()`'s return value — checked at install time, mismatched slugs are rejected before anything is written to disk. |
| `name` | Yes | Human-readable, shown in the catalog. |
| `version` | Yes | Strict semver (`X.Y.Z`), compared with `version_compare()`. Must equal `getVersion()`'s return value. |
| `namespace` | Yes | PSR-4 root for everything under `src/`. |
| `entry_class` | Yes | Fully-qualified entry class name; its file must exist under `src/` once resolved from `namespace` (checked before activation). |
| `description`, `author`, `license`, `homepage` | No | Informational only. |
| `kintai_core.min`/`.max` | No (defaults `0.0.0`/`999.999.999`) | The installer refuses to activate a bundle outside these bounds against the running instance's Core version. |
| `requires_bundles` | No (defaults `{}`) | Slug → version constraint. **Informational only for now** — see Limitations. |

## Publishing a release

1. Bump `version` in `bundle.json` (and `getVersion()` in your entry class — they must agree).
2. Tag the commit `vX.Y.Z` and push the tag.
3. Create a GitHub Release for that tag (`gh release create vX.Y.Z --generate-notes`, or a CI workflow that does the same on tag push — see `kintai-bundle-feedback`'s `.github/workflows/release.yml` for a minimal one). Nothing to build: the Release's auto-generated `zipball_url` is exactly what `BundleInstallerService` downloads.

## Getting listed in a registry

A **registry** is nothing more than a static `registry.json` file served over plain HTTPS (a GitHub repo's `raw.githubusercontent.com` URL works well, and is how the official one is served) — Kintai never clones it either, just fetches it with a GET (`BundleRegistryClient`).

```json
{
    "schema_version": 1,
    "name": "My registry",
    "bundles": [
        {
            "slug": "your-bundle",
            "name": "Your Bundle",
            "description": "...",
            "repository_url": "https://github.com/you/your-bundle",
            "versions": ["1.0.0"]
        }
    ]
}
```

- `schema_version` must be `1` — an instance that doesn't understand a newer schema rejects the listing cleanly (with a log entry) instead of misinterpreting it.
- `repository_url` must be a plain `https://github.com/{owner}/{repo}` — the installer derives the GitHub API release-lookup URL from it.
- `versions` is a hint for the catalog UI (which versions to offer); `bundle.json` inside your repository remains the actual source of truth read at install time.

Two ways to get discovered:
- **Your own registry** — write and host a `registry.json` yourself (anywhere reachable over HTTPS), then anyone can add its URL from `/admin/bundles/registries`. No approval needed from anyone.
- **The official Kintai registry** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — open a pull request adding an entry to its `registry.json`. Being listed there does **not** make your bundle "official" — see below.

## Official vs. third-party

`config/official-bundles.php`, inside Kintai's own repository, is the **only** source of truth for which slugs are maintained by the Kintai project itself — a bundle can't self-declare as official, whichever registry lists it. Anything else is flagged "third-party" in the catalog UI, and installing or updating it requires an explicit `confirm_third_party` checkbox, enforced server-side (not just hidden by JavaScript) — expect your users to see a warning, and design your bundle assuming they'll read it before proceeding.

## Known limitations (as of this writing)

- **One process, one Composer autoloader.** There's no per-bundle `composer.json` or dependency isolation — your bundle runs in the same PHP process and namespace tree as Kintai's own `kintai\` root. Depend only on `BundleContract\Bundle`, `src/Core/Repositories/*Interface.php`, and other Core classes you're comfortable coupling to across Kintai versions.
- **`requires_bundles` isn't enforced yet.** Declaring a dependency on another bundle's slug/version is accepted and stored, but nothing currently blocks installation if it's missing or too old — treat it as documentation for now, not a guarantee.
- **No uninstall flow yet.** `/admin/bundles/market` can install and update; removing a bundle's files and its database rows isn't wired up yet.
- **GitHub only.** `repository_url` must point at a GitHub repository — no GitLab, no self-hosted Git server, no non-Git archive source.

## Reference implementation

[`kintai-bundle-feedback`](https://github.com/AudricSan/kintai-bundle-feedback) is the pilot bundle this whole mechanism was built to validate — a complete, working, minimal example of everything above, extracted from Kintai's own `src/Bundles/Feedback/` without any behavior change.
