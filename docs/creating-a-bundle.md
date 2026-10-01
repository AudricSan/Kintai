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
  database/migrations/        # optional — see "Database migrations" below
  Views/                      # optional — loaded via loadViewsFrom()
  public/{css,js}/...         # optional — see "Assets" below, loaded via loadAssetsFrom()
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
    protected function loadAssetsFrom(string $relativeDir): void;  // see "Assets" below
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

## The RBAC scope contract: `managed_store_ids`

Every Web route your bundle registers under `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` must declare a `permission:` — either a precise key from `PermissionCatalog`, or `'public'` (a route intentionally left ungated, e.g. self-service/aggregate pages; omitting `permission:` entirely behaves the same way). `PermissionMiddleware` resolves this into a request attribute, `managed_store_ids`, that every controller down the line is expected to read rather than re-deriving:

- **`null`** — unrestricted. Either the user is Owner, or their role grants your route's permission key with the "all stores" scope checked (`role_permissions.scope = 'global'`) — which can happen even when the *assignment* linking them to that role is itself store-scoped to their home store. Both cases mean the exact same thing: no store filtering.
- **`int[]`** — restricted to exactly these store IDs.

**The one mistake that reintroduces this bug every time**: writing `$request->getAttribute('managed_store_ids') ?? []` instead of branching on `=== null`. It looks harmless because several Core repository methods treat an *empty* filter array as "no filter" — so a list query downstream can keep "working" by coincidence — but the next thing reading the same collapsed value (a store-picker dropdown, an `in_array($storeId, $managedIds)` access check) sees "zero stores", not "every store", and breaks. This exact mistake shipped in `kintai-bundle-daily-report`'s `DailyReportController::indexAll()` (fixed in `fix/global-scope-permission-store-filter`) — always handle `null` as its own branch, mirroring `is_admin`, not as something a `??` fallback can absorb.

Don't re-derive this resolution logic yourself. Reuse Core's `kintai\UI\Controller\Web\HasAdminAccess` trait — `managedIds(Request $request): ?array`, `availableStores(?array $managedIds): array`, `assertStoreAccess(Request $request, int $storeId): void`, `assertAnyStoreAccess(Request $request, array $storeIds): void` — the same trait every official bundle's admin controller uses (see `StorePhotoController` in `kintai-bundle-store-photos` for a real example). It already gets the `null` case right; a hand-rolled equivalent in your own controller is exactly how this class of bug gets reintroduced, one bundle at a time.

If your bundle needs to serve an **uploaded file** (an image, a PDF, an attachment) behind this same authorization, don't build your own file-serving route for it — link into Kintai's own `/storage/{path*}` (route name `storage.file`), which already applies `managed_store_ids` (`StorageFileController::assertPathStoreAccess()`) plus its own upload-path confinement and MIME allow-list. A parallel file-serving route duplicates authorization logic Core already owns, outside of Core's own test coverage.

## Readable URLs: typed route parameters

Core's web routes no longer show database ids for stores and employees (`/admin/stores/所沢東町店/edit`, `/admin/users/057/edit`, see `docs/architecture.md`, "Readable URLs"). Your bundle gets the same thing by typing its route parameters — nothing to register:

```php
// routes.php
$r->get('/stores/{id:store}/photos',               [StorePhotoController::class, 'index'], name: 'store_photos.index', permission: 'photos.view');
$r->get('/stores/{id:store}/reports/{uid:employee}', [ReportController::class, 'show'],    name: 'reports.show',       permission: 'reports.view');
```

- **Your controllers don't change:** `$request->param('id')` still returns the numeric id. The segment is resolved before `PermissionMiddleware`, so `store_param` rules and `assertStoreAccess()` keep working unchanged.
- **Generate links with `route_url()`, passing the id** (`route_url('store_photos.index', ['id' => $storeId])`): it emits the readable, encoded segment. For a URL you build by hand, use `store_segment($storeId)` / `employee_segment($userId)` instead of the raw id — never the employee's name.
- **Old links keep working:** a numeric id is resolved and a GET is redirected `301` to the readable URL (a POST is served as is), so bookmarks, e-mails and notifications sent before your bundle migrated are not broken.
- **Only type what designates a store or an employee.** A report, a photo or a membership id stays a plain `{id}`.
- **Requires a Core that ships typed route parameters** (see its CHANGELOG): set `kintai_core.min` in `bundle.json` accordingly, since an older Core would read `{id:store}` as a literal segment.

## Database migrations

A bundle can own its own table(s) — a dedicated PR against Kintai's own repository is **not** required just to create a schema anymore. Drop an optional `database/migrations/` directory at your bundle's root (a sibling of `src/`, same rule as `Views/`/`routes.php`), containing files in the **exact same format** as Kintai's own Core migrations (`database/migrations/php/*.php`):

```php
<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Core\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('your_bundle_table')) {
            return;
        }
        $this->schema()->create('your_bundle_table', function (Blueprint $table) {
            $table->increments('id');
            // ...
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('your_bundle_table');
    }
};
```

Name files `YYYY_MM_DD_NNNNNN_description.php` — alphabetical sort is execution order, exactly like Core migrations. Always guard `up()` with `hasTable()`/`hasColumn()` (as above): migrations must be idempotent, since they can be replayed by a manual re-sync (see below).

**When they run**: automatically, right after your bundle's files land on disk during install or update from `/admin/bundles/market` (`BundleInstallerService::activate()`) — before the bundle is marked active, so a failing migration aborts the whole install/update rather than activating a bundle with an incomplete schema. They can also be re-synced manually, for every installed bundle at once, with `php scripts/db-migrate.php` (`--dry-run` previews what's pending, same flag as for Core migrations).

**Tracking**: applied bundle migrations are recorded in a `bundle_migrations` table (`bundle_slug` + `migration` name, unique together) — separate from Core's own `migrations` table, so two different bundles can never collide on a migration file name, and so `BundleMigrationRunner::forgetBundle()` can clear just your bundle's tracking rows on uninstall. Business tables themselves are **not** dropped on uninstall (see Limitations) — only the tracking rows are, so a later reinstall replays your `up()` methods cleanly (their `hasTable()` guards make that a no-op if the tables are still there).

This mechanism is deliberately Core-agnostic: your repository interface and Eloquent model can live in your own bundle's namespace, or in `src/Core/Repositories/*Interface.php` if you'd rather follow the convention every official bundle currently uses (see "The stable contract" above) — the migration itself doesn't care either way.

## Assets

**Requires `kintai_core.min: "0.2.0"` or later.** If your bundle needs its own CSS or JS, don't ask for it to be added to Kintai's own `public/assets/` — that was the situation for every bundle before this mechanism existed, and it defeated the entire point of distributing bundles as separate repositories (a CSS tweak meant a Core PR). Ship it yourself, from your own `public/` directory (a sibling of `src/`, `Views/`, `routes.php` — same rule as everything else in the required layout above):

```
your-bundle/
  public/
    css/your-bundle.css
    js/your-bundle.js
```

Register it in `register()`, exactly like `loadViewsFrom()`/`loadRoutesFrom()`:

```php
public function register(): void
{
    $this->loadViewsFrom($this->getPath() . '/Views', 'your-namespace');
    $this->loadRoutesFrom($this->getPath() . '/routes.php');
    $this->loadAssetsFrom('public');
}
```

From any of your bundle's views, reference the file through the `bundle_asset()` helper — never a hardcoded path, since the actual filesystem location depends on which version is currently installed:

```php
<?php if ($css = bundle_asset('your-slug', 'css/your-bundle.css')): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endif; ?>
```

`bundle_asset()` returns `null` (never throws) when your bundle isn't active — always guard the `<link>`/`<script>` with an `if`, as above, rather than assuming it always resolves. The URL it builds (`GET /bundle-assets/{slug}/{path}?v={version}`) is served by a dedicated, unauthenticated route (`BundleAssetController`) — these are public static files, not gated behind `AuthMiddleware`/`PermissionMiddleware` like your bundle's own pages — confined to your declared `public/` directory and limited to a fixed extension whitelist (`css`, `js`, `svg`, `png`, `webp`). Anything outside that whitelist, or any attempt to traverse above your `public/` directory, is rejected (`403`); a request for a bundle that's inactive, or one that never called `loadAssetsFrom()`, is a plain `404`.

If a view needs the file's **contents** directly rather than a URL — the common case being a PDF export that inlines its stylesheet via `file_get_contents()` — use `bundle_asset_path()` instead, which resolves to the same file's absolute filesystem path (again `null` if inactive):

```php
$css = file_get_contents(bundle_asset_path('your-slug', 'css/pdf-your-bundle.css') ?? '');
```

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
| `kintai_core.min`/`.max` | No (defaults `0.0.0`/`999.999.999`) | The installer refuses to activate a bundle outside these bounds against the running instance's Core version. A bundle whose views use the `data-*` actions or `csp_nonce()` must set `min` to `0.3.0` — see [Content Security Policy](#content-security-policy-no-inline-scripts). |
| `requires_bundles` | No (defaults `{}`) | Slug → version constraint. **Informational only for now** — see Limitations. |

## Publishing a release

1. Bump `version` in `bundle.json` (and `getVersion()` in your entry class — they must agree).
2. Tag the commit `vX.Y.Z` and push the tag.
3. Create a GitHub Release for that tag (`gh release create vX.Y.Z --generate-notes`, or a CI workflow that does the same on tag push — see `kintai-bundle-feedback`'s `.github/workflows/release.yml` for a minimal one). Nothing to build: the Release's auto-generated `zipball_url` is exactly what `BundleInstallerService` downloads.

## Content Security Policy: no inline scripts

Kintai sends `script-src 'self' 'nonce-…'` (`SecurityHeadersMiddleware`): the browser only runs scripts served from Kintai itself, or an inline `<script>` carrying the nonce of the current request. **Inline event attributes (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…) and `javascript:` links are blocked**, silently — the button just does nothing, with no error on the server. A bundle view must therefore not use them.

- **Inline `<script>`** — only when really needed, with the request's nonce: `<script nonce="<?= csp_nonce() ?>">…</script>`. A `<script type="application/json">` data block is not executed and needs no nonce. Prefer a file in your bundle's `assets/js/` (loaded with `bundle_asset()`), which needs nothing.
- **Event attributes** — replace them with declarative `data-*` attributes, handled by `public/assets/js/modules/csp-actions.js` (loaded by the app layout):

| Instead of | Write |
|---|---|
| `onclick="doThing()"` | `data-on-click="doThing"` |
| `onclick="doThing('a', 2)"` | `data-on-click="doThing" data-args='["a", 2]'` |
| `onchange="doThing(this.value)"` | `data-on-change="doThing" data-args='["@value"]'` (`"@this"`, `"@value"`, `"@checked"` are replaced at call time) |
| `onchange="this.form.submit()"` | `data-submit-on-change` |
| `onchange="document.getElementById('f').submit()"` | `data-submit-form="f"` |
| `onclick="location.href='/x'"` | `data-goto="/x"` (http/https only) |
| `onclick="event.stopPropagation()"` | `data-stop-propagation` |
| `onclick="window.print()"` | `data-on-click="@print"` (also `@close`, `@select`, `@removeParent`, `@copy`) |
| `onsubmit="return confirm('…')"`, `onclick="return confirm('…')"` | `data-confirm="…"` on the `<form>` or on the submit button (global confirmation modal) |
| `Button::attrs(['onclick' => 'f()'])` | `Button::attrs(['data-on-click' => 'f'])` |

`data-on-*` only calls a **function you defined yourself** on `window` (a function declaration in your script, or `window.f = …`): browser built-ins such as `eval` or `setTimeout` are refused on purpose, so that an injected attribute cannot become a way to run code. `Button`/`Modal` attribute values are already HTML-escaped by the component — pass the raw value, not `htmlspecialchars()` output.

**Version requirement.** The `data-*` actions and `csp_nonce()` exist from **Kintai Core 0.3.0**. A bundle that uses them must declare `"kintai_core": { "min": "0.3.0", … }` in its `bundle.json`, so the installer refuses it on an older Core instead of installing controls that do nothing. Conversely, this is a **breaking change for existing bundles**: a bundle written before 0.3.0 that still uses inline handlers keeps working on a Core older than 0.3.0, but its buttons, selects and confirmations stop working on 0.3.0 and later until it is migrated. `tests/Unit/Security/NoInlineScriptGuardTest.php` in Kintai fails as soon as a Core view reintroduces an inline handler; do the same check on your own views.

### Migrating an existing bundle

1. Find the offenders: `grep -rnE "[[:space:]]on(click|change|submit|input)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|javascript:|<script>" Views src`.
2. Replace each with the matching `data-*` attribute from the table above. A `confirm()` becomes `data-confirm`; a handler that called `event.stopPropagation()` needs `data-stop-propagation`, otherwise a delegated ancestor fires too.
3. Make sure every function called through `data-on-*` is defined **on `window`**: a function declaration in a classic script, or `window.f = …`. A top-level `const f = …` or `let f = …` is not a property of `window` and is refused.
4. Put the nonce on the inline `<script>` blocks you keep: `<script nonce="<?= csp_nonce() ?>">`.
5. Raise `kintai_core.min` to `0.3.0` and add the CI check below.

### Checking in CI

The server sees no error when the browser blocks an inline handler, so nothing else will catch a regression. The official bundles run this step in `.github/workflows/tests.yml`:

```yaml
- name: No inline event handlers or nonce-less scripts (Kintai CSP)
  run: |
    set -euo pipefail
    if grep -rnE "[[:space:]]on(click|change|submit|input|load|error|focus|blur|dblclick|keyup|keydown)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|(href|src|action)[[:space:]]*=[[:space:]]*[\"']javascript:|<script>" Views src; then
      echo "::error::Inline handlers, javascript: URLs and <script> without nonce are blocked by Kintai's CSP"
      exit 1
    fi
```

A **registry** is nothing more than a static `registry.json` file served over plain HTTPS (a GitHub repo's `raw.githubusercontent.com` URL works well, and is how the official one is served) — Kintai never clones it either, just fetches it with a GET (`BundleRegistryClient`).

```json
{
    "schema_version": 2,
    "name": "My registry",
    "bundles": [
        {
            "slug": "your-bundle",
            "name": "Your Bundle",
            "description": "...",
            "repository_url": "https://github.com/you/your-bundle",
            "versions": {
                "release": ["1.0.0"],
                "beta": ["1.1.0", "1.0.0"],
                "alpha": ["1.1.0", "1.0.0"]
            }
        }
    ]
}
```

- `schema_version` — Kintai currently understands `1` and `2`; an instance that doesn't understand a newer schema rejects the listing cleanly (with a log entry) instead of misinterpreting it.
- `repository_url` must be a plain `https://github.com/{owner}/{repo}` — the installer derives the GitHub API release-lookup URL from it.
- `versions` (schema 2) is keyed by update channel — `release` (only non-prerelease releases published from `main`), `beta` (`main` or `beta`, excludes `alpha`), `alpha` (everything) — each a list of installable versions, newest first. This mirrors the channel an Owner picks once for every installed bundle on `/admin/bundles/market` (`AppSettingsService::bundleUpdateChannel()`, independent of the Core's own `/admin/update` channel): whichever list matches that channel is what the catalog UI offers as "latest" and what a plain update installs. **Schema 1** (`versions` as a flat array, no channel key) is still accepted for backward compatibility — Kintai then offers that same flat list on every channel, since there's no way to tell which release came from which branch. The official registry computes schema 2's three lists automatically from each bundle's actual GitHub Releases (`target_commitish`/`prerelease`, same rule as Kintai's own release channels) — see [`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)'s `scripts/sync-versions.js`, which runs hourly and opens a PR whenever a new release changes any bundle's version lists; you don't hand-edit `versions` yourself.
- `bundle.json` inside your repository remains the actual source of truth for compatibility (`kintai_core.min`/`max`) and everything else read at install time — `versions` here is only ever a hint for the catalog UI about what's installable and on which channel.
- `commits` (optional, added to schema 2 without a version bump — older Kintai versions ignore it) maps each version to the full 40-character sha of the commit its tag points to, e.g. `"commits": {"1.0.2": "7f99e084…"}`. When present, Kintai checks the downloaded archive against it **before extracting anything** (a GitHub zipball carries its commit in the ZIP comment and in the name of its root folder) and refuses the install on a mismatch, so a tag moved to another commit (compromised repository or account) can't slip a different code base past the registry. The official registry fills it in automatically: the same hourly `sync-versions.js` run pins each new release's commit, never rewrites an existing pin, and fails loudly if a tag has moved — the pin only reaches `main` through the pull request a maintainer reviews. Kintai reads the expected commit from the registry server-side (never from the request), and refuses to install if the registry can't be reached to check it. A registry that doesn't publish `commits` keeps working as before (a warning is logged). This is not a signature: it relies on GitHub generating the archive and on HTTPS, and the registry itself remains the trust anchor. If you maintain your own registry, `sync-versions.js` from `AudricSan/KintaiBundle` can be reused as is.

Two ways to get discovered:
- **Your own registry** — write and host a `registry.json` yourself (anywhere reachable over HTTPS), then anyone can add its URL from `/admin/bundles/registries`. No approval needed from anyone.
- **The official Kintai registry** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — open a pull request adding an entry to its `registry.json`. Being listed there does **not** make your bundle "official" — see below.

## Official vs. third-party

`config/official-bundles.php`, inside Kintai's own repository, is the **only** source of truth for which slugs are maintained by the Kintai project itself — a bundle can't self-declare as official, whichever registry lists it. Anything else is flagged "third-party" in the catalog UI, and installing or updating it requires an explicit `confirm_third_party` checkbox, enforced server-side (not just hidden by JavaScript) — expect your users to see a warning, and design your bundle assuming they'll read it before proceeding.

## Known limitations (as of this writing)

- **One process, one Composer autoloader.** There's no per-bundle `composer.json` or dependency isolation — your bundle runs in the same PHP process and namespace tree as Kintai's own `kintai\` root. Depend only on `BundleContract\Bundle`, `src/Core/Repositories/*Interface.php`, and other Core classes you're comfortable coupling to across Kintai versions.
- **`requires_bundles` isn't enforced yet.** Declaring a dependency on another bundle's slug/version is accepted and stored, but nothing currently blocks installation if it's missing or too old — treat it as documentation for now, not a guarantee.
- **No uninstall flow for business data.** `/admin/bundles/market` can install, update, and uninstall a bundle's files/manifest entry/migration tracking rows — but the tables your migrations created (and their data) are never dropped or touched on uninstall. Cleaning those up, if desired, is a manual DB operation for now.
- **Inline scripts and event handlers don't work.** From Kintai Core 0.3.0 the Content-Security-Policy has no `'unsafe-inline'` for scripts: a bundle view with `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, a `javascript:` link or a nonce-less `<script>` is silently blocked by the browser. See [Content Security Policy](#content-security-policy-no-inline-scripts).
- **GitHub only.** `repository_url` must point at a GitHub repository — no GitLab, no self-hosted Git server, no non-Git archive source.

## Reference implementation

[`kintai-bundle-feedback`](https://github.com/AudricSan/kintai-bundle-feedback) is the pilot bundle this whole mechanism was built to validate — a complete, working, minimal example of everything above, extracted from Kintai's own `src/Bundles/Feedback/` without any behavior change.
