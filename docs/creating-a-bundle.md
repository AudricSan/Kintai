# Creating a bundle

🌐 **English** · [Français](i18n/creating-a-bundle.fr.md) · [日本語](i18n/creating-a-bundle.ja.md)

This document is for anyone writing a Kintai bundle meant to be **distributed independently** — its own Git repository, its own version history, installable from `/admin/bundles/market` without ever touching Kintai's own codebase. It doesn't cover the older, still-supported pattern of dropping a bundle directly into `src/Bundles/` inside the main repo (see `docs/architecture.md` → "Modular Bundles" for that) — that path is still scanned for a bundle dropped in by hand, but every official bundle is distributed the way described here and a new bundle, especially a third-party one, should be too.

`kintai-bundle-notebook` (the team notebook: its own model, repository, migrations, views, dashboard widgets and declarations) is a small, complete, real-world example — worth keeping open in a second tab while reading this.

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

A minimal, real example (`kintai-bundle-notebook`'s actual entry class, trimmed):

```php
<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook;

use kintai\Bundles\Installed\Notebook\Repositories\DatabaseNotebookEntryRepository;
use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Contract\Bundle;
use kintai\Contract\Extension\StoreFeature;

final class NotebookBundle extends Bundle
{
    public function getName(): string { return 'notebook'; }
    public function getVersion(): string { return '1.2.0'; }
    public function getLabel(): string { return __('bundle_notebook'); }
    public function getDescription(): string { return __('bundle_notebook_desc'); }

    public function register(): void
    {
        // The bundle owns its repository (and its table, created by its own migrations).
        $this->app->container()->singleton(
            NotebookEntryRepositoryInterface::class,
            fn() => new DatabaseNotebookEntryRepository()
        );
        $this->loadViewsFrom($this->getPath() . '/Views', 'notebook');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    // What it brings to the Core is declared (see "Extension points" below).
    public function permissions(): array { return ['notebook' => ['view', 'create', 'manage']]; }

    public function storeFeatures(): array
    {
        return [new StoreFeature('notes', 'feature_notes', 'feature_notes_desc', '📝', 90)];
    }
}
```

Your namespace can be anything — by convention, installed bundles use `kintai\Bundles\Installed\{Name}\`, deliberately distinct from the legacy monorepo convention (`kintai\Bundles\{Name}\`) so the two autoloading mechanisms (Composer's own PSR-4 for `src/Bundles/`, and a dedicated `spl_autoload_register` for anything under `storage/bundles/`) can never collide on the same class name.

**A bundle never depends on the Core's repositories or models**: it owns its own (as above) and only uses the `kintai\Contract\…` names listed below. Data the Core needs from it goes through a declared provider.

## The contract surface: `kintai\Contract\…`

A bundle may only use the names below (plus its own classes). Each one is a **stable alias** of the Core class that
implements it today (`src/Contract/surface.php` is the reference list), so the Core can reorganise its own code
without breaking bundles. Import them exactly like any class — type hints, `instanceof`, `catch` and constructor
injection all work, the container resolves an alias to the binding of the class behind it.

```php
use kintai\Contract\Bundle;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreRepositoryInterface;
```

**Stable** names are guaranteed for the whole major version of the contract. **Provisional** names are exposed for
the transition and will be replaced by a later step of the contract plan (see their description); they only go
away with a new major version.

| Name | Status | Role |
|---|---|---|
| `kintai\Contract\Bundle` | stable | Base class of every bundle (entry point). |
| `kintai\Contract\BundleManifest` | stable | Parsed bundle.json. |
| `kintai\Contract\Container` | stable | Dependency container (register your own bindings). |
| `kintai\Contract\Cron\CronJob` | stable | Scheduled job declared by `Bundle::cronJobs()` (contract 1.8). |
| `kintai\Contract\Http\Request` | stable | HTTP request. |
| `kintai\Contract\Http\Response` | stable | HTTP response. |
| `kintai\Contract\Http\Router` | stable | Route declaration (routes.php). |
| `kintai\Contract\Http\Paginator` | stable | API list pagination. |
| `kintai\Contract\Http\Middleware\AuthMiddleware` | stable | Web session required. |
| `kintai\Contract\Http\Middleware\PermissionMiddleware` | stable | Web permission required. |
| `kintai\Contract\Http\Middleware\ApiAuthMiddleware` | stable | API token required. |
| `kintai\Contract\Http\Middleware\ApiPermissionMiddleware` | stable | API permission required. |
| `kintai\Contract\Http\Concerns\HasBaseUrl` | stable | URL prefix (subfolder installs). |
| `kintai\Contract\Http\Concerns\HasAdminAccess` | **provisional** | Manager's store scope; to be replaced by CurrentUser/Gate (step 2). |
| `kintai\Contract\Http\Concerns\HasStoreFeatureCheck` | **provisional** | Per-store feature check; to be replaced by the storeFeatures extension point (step 2). |
| `kintai\Contract\Http\Concerns\HasStaffReportCrud` | **provisional** | Shared HR-report CRUD; moves into the bundles concerned (step 4). |
| `kintai\Contract\Exceptions\NotFoundException` | stable | 404. |
| `kintai\Contract\Exceptions\ForbiddenException` | stable | 403. |
| `kintai\Contract\Exceptions\ValidationException` | stable | 422 with per-field errors. |
| `kintai\Contract\Exceptions\ConflictException` | stable | 409. |
| `kintai\Contract\Auth\PermissionService` | stable | Permission and scope checks. |
| `kintai\Contract\Auth\AuthService` | **provisional** | Session user; to be replaced by CurrentUser (step 2). |
| `kintai\Contract\Auth\UserAnonymizer` | stable | Deleting an account: `anonymize($userId)` erases personal data, deactivates the account and revokes its rights and tokens, the history stays attached to "Deleted user" (contract 1.21; never call `UserRepositoryInterface::delete()` for that). |
| `kintai\Contract\Data\UserRepositoryInterface` | stable | Accounts. |
| `kintai\Contract\Data\StoreRepositoryInterface` | stable | Stores. |
| `kintai\Contract\Data\StoreUserRepositoryInterface` | stable | Store memberships. |
| `kintai\Contract\Data\ShiftRepositoryInterface` | stable | Shifts. |
| `kintai\Contract\Data\ShiftTypeRepositoryInterface` | stable | Shift types. |
| `kintai\Contract\Data\UserShiftTypeRateRepositoryInterface` | stable | Per-user hourly rates. |
| `kintai\Contract\Data\AppSettingsRepositoryInterface` | stable | Instance settings. |
| `kintai\Contract\Database\Migration` | stable | Base class of the bundle's migrations. |
| `kintai\Contract\Services\AuditLogger` | stable | Activity log. |
| `kintai\Contract\Services\NotificationService` | stable | Notifications (and push). |
| `kintai\Contract\Services\TranslationService` | stable | Translations. |
| `kintai\Contract\Services\ImageCompressionService` | stable | Image compression. |
| `kintai\Contract\Services\Mailer` | stable | Sends an e-mail through the instance's configured transport: `send($to, $subject, $html, $attachmentPaths)` (contract 1.15). |
| `kintai\Contract\Services\PdfCjkFontResolver` | stable | Japanese font for mPDF documents: `PdfCjkFontResolver::applyTo($mpdfConfig)` (contract 1.16). |
| `kintai\Contract\Services\Sales` | stable | A store's daily sales, read from the sales providers (contract 1.14, see "Sales" below). |
| `kintai\Contract\Services\ShiftWageCalculator` | stable | Cost of a shift (single source of the payroll computation). |
| `kintai\Contract\Services\ShiftServiceInterface` | **provisional** | Shift creation; to be replaced by a targeted write API (step 3). |
| `kintai\Contract\Services\StoreStatsServiceInterface` | **provisional** | Store statistics; to be replaced by providers (step 3). |
| `kintai\Contract\View\ViewRenderer` | stable | Renders the bundle's views. |
| `kintai\Contract\View\Components\Badge` | stable | UI component (`Badge`). |
| `kintai\Contract\View\Components\Button` | stable | UI component (`Button`). |
| `kintai\Contract\View\Components\Card` | stable | UI component (`Card`). |
| `kintai\Contract\View\Components\EmptyState` | stable | UI component (`EmptyState`). |
| `kintai\Contract\View\Components\FilterBar` | stable | UI component (`FilterBar`). |
| `kintai\Contract\View\Components\Flash` | stable | UI component (`Flash`). |
| `kintai\Contract\View\Components\Form` | stable | UI component (`Form`). |
| `kintai\Contract\View\Components\Modal` | stable | UI component (`Modal`). |
| `kintai\Contract\View\Components\Pagination` | stable | UI component (`Pagination`). |
| `kintai\Contract\View\Components\Table` | stable | UI component (`Table`). |

### Bundle-owned classes

A bundle owns its models, repositories, services and tables: none of them live in the Core any more (step 4 of
the plan is complete for every official bundle). A bundle only imports `kintai\Contract\…`; its contract-usage
test has no exception left.

### Checking your bundle

Kintai's `tests/Support/ContractUsageScanner.php` (loaded by the bundles' PHPUnit harness) reports every
`kintai\Core\…`, `kintai\UI\…` or `kintai\Domain\…` reference outside the contract:

```php
public function testOnlyTheContractIsUsed(): void
{
    $root = dirname(__DIR__);
    $this->assertSame([], ContractUsageScanner::violations(
        [$root . '/src', $root . '/Views', $root . '/routes.php'],
        [/* bundle-owned classes still living in the Core */],
    ));
}
```

## Extension points (contract 1.1+)

The Core never names a bundle: a bundle **declares** what it adds to the interface by overriding methods of its
entry class, and the Core renders it. Each bundle is queried in isolation — an exception or a value of the wrong
type drops its contribution and is logged, the page keeps working. Require `"kintai_contract": "^1.1"` to use them.

### Navigation: `navigation()`

```php
use kintai\Contract\Extension\NavItem;

public function navigation(): array
{
    return [
        new NavItem(
            key: 'timeoff',                       // unique, also the key of the user's "hide this entry" preference
            section: NavItem::REQUESTS,           // MAIN, PLANNING, HR, REQUESTS, REPORTS or SYSTEM
            label: 'timeoff',                     // translation key (manager view)
            managerRoute: 'admin.timeoff',        // route name, or null if managers have no page
            employeeRoute: 'employee.timeoff',    // route name, or null if employees have no page
            icon: 'leaf',
            employeeLabel: 'my_timeoff',
            activePaths: ['/admin/timeoff', '/employee/timeoff'],
            storeFeature: 'timeoff',              // per-store feature switch, if any
            order: 10,                            // Core entries use multiples of 10
            mobile: true,                         // may be picked for the mobile bottom bar
        ),
    ];
}
```

An entry is shown only when the current role has a route, holds the permission declared on that route, and — if
`storeFeature` is set — has that feature enabled. Users can hide it from their navigation preferences.

### Permissions: `permissions()` (contract 1.2)

```php
public function permissions(): array
{
    return [
        'timeoff' => ['view', 'create', 'update', 'delete', 'approve'],  // category => actions
    ];
}
```

Each `category.action` pair becomes a permission key you can put on your routes (`permission: 'timeoff.approve'`)
and that Owners grant from the Roles page while your bundle is active. Names are lowercase `snake_case`. The Core
categories (`employees`, `shifts`, `stores`, `payroll`) can't be redefined, and a category already declared by
another bundle is ignored (logged). The Roles page labels a category with the translation key
`perm_category_{category}` and an action with the Core's label for the common actions (`view`, `create`, `update`,
`delete`, `approve`, …), otherwise with the action name itself as a translation key — ship those keys in your
bundle's `lang/`. Disabling the bundle hides the category from the Roles page but keeps the grants in the
database, so re-enabling it restores them. On the bundle's first install (setup assistant or catalogue), every
declared permission is granted to the Manager role, which uses it on its own stores; updates grant nothing again, so a
right the Owner withdrew stays withdrawn. Require `"kintai_contract": "^1.2"`.

### Per-store features: `storeFeatures()` (contract 1.3)

```php
use kintai\Contract\Extension\StoreFeature;

public function storeFeatures(): array
{
    return [
        new StoreFeature(
            key: 'timeoff',                       // stored in store_features, lowercase snake_case
            label: 'feature_timeoff',             // translation key of the card title
            description: 'feature_timeoff_desc',  // translation key of the card description
            icon: '🏖',
            order: 30,                            // Core features use multiples of 10
        ),
    ];
}
```

While your bundle is active, each feature is offered as a switch in the "Features" card of the store form. Check
it on your employee pages with `HasStoreFeatureCheck::assertFeature($request, 'timeoff')`, and give the same key
as `storeFeature` to your `NavItem`s so they disappear for stores that turned it off. A store that has never saved
its features has every feature on; disabling your bundle keeps each store's choice, so re-enabling it restores
them. A key already declared by the Core or another bundle is ignored (logged). Require
`"kintai_contract": "^1.3"`.

### Dashboard widgets: `dashboardWidgets()` (contract 1.4)

```php
use kintai\Contract\Extension\DashboardWidget;

public function dashboardWidgets(): array
{
    return [
        new DashboardWidget(
            key: 'team_notes',                    // unique on that dashboard, also the user's preference key
            audience: DashboardWidget::MANAGER,   // MANAGER ("/") or EMPLOYEE ("/employee")
            label: 'widget_team_notes',           // translation key shown in the "Customize" panel
            view: 'notebook::widgets.dashboard',  // your namespaced view (loadViewsFrom())
            data: fn(array $ctx): array => [      // variables passed to the view
                'entries' => $this->app->container()->make(NotebookEntryRepositoryInterface::class)
                    ->findVisibleForStores($ctx['store_ids']),
            ],
            permission: 'notebook.view',
            storeFeature: 'notes',
            order: 10,
        ),
    ];
}
```

Your widgets are listed in the dashboard's "Customize" panel (on by default) and rendered after the Core's own
widgets, full width, until the user moves or resizes them (each user's order and widths are saved; a widget the user
never placed goes last), for users holding `permission` whose stores have `storeFeature` on. `data` receives the context: `user`,
`store_ids` (the stores the manager handles, or the employee's own stores), `managed_store_ids` (`null` = every
store, manager side), `stores_map` (id => name) and `today`. Load only what the widget shows — `data` runs on every
dashboard view. An exception in `data` or in the view removes that widget only (logged). Render a full card
(`<div class="card card--mt">…</div>`). Core keys can't be reused. A bundle that takes over a widget the Core used
to render keeps its key so users keep their choices (`notebook` kept `team_notes`, `timeoff` kept `pending_timeoff`,
`shift-swap` kept `pending_swaps` and `timeclock` kept `timeclocks_today` and `timeclock` when they were extracted).
Require `"kintai_contract": "^1.4"`.

A manager can also add their own `EMPLOYEE` widgets to the manager dashboard (off by default, offered in "Add a widget",
never the other way round). There the widget is stored under the key `emp_{key}`, and its `data` receives the same
context as on the employee dashboard: the manager's own `store_ids`, with `managed_store_ids` set to `null`. This is
why widget keys are limited to 41 characters.

#### Size and refresh (contract 1.26)

The grid has 12 columns. Declare how your widget should look with optional arguments (the defaults keep the previous
behaviour: full width, height of the content, never refreshed):

```php
new DashboardWidget(key: 'team_notes', /* … */ defaultWidth: 4, defaultHeight: 2, minWidth: 3, maxWidth: 8, refreshInterval: 120)
```

`defaultWidth` is in columns (1–12), `defaultHeight` is a minimum in rows (1–6), `minWidth`/`maxWidth` bound what the user
can choose, `refreshInterval` is in seconds (0 = never, otherwise at least 15). Design the view for narrow cards (3–4 columns:
truncate lists, link to "see all") as well as wide ones.


### View variables: `viewVariables()` (contract 1.5)

```php
public function viewVariables(): array
{
    return [
        // name => fn(array $user): mixed, shared with every view of authenticated pages
        'unread_messages_count' => fn(array $user): int =>
            $this->app->container()->make(MessageRepositoryInterface::class)->countUnread((int) $user['id']),
    ];
}
```

Use it for the few values your navigation or layout needs on every page — typically the counter named in a
`NavItem`'s `badge`. Each function runs on every authenticated page, so keep it to one cheap query. Names are
lowercase `snake_case`, preferably prefixed with your bundle's name; Core names (`auth_user`, `managed_store_ids`,
`store_features`, …) and names already declared by another bundle are refused, and an exception drops that variable
only (logged). `messaging` shares `unread_messages_count` and `daily-report` `daily_report_staff_stores` this way;
the Core computes none of them itself. Require `"kintai_contract": "^1.5"`.

### Action links: `actions()` (contract 1.6)

```php
use kintai\Contract\Extension\Action;

public function actions(): array
{
    return [
        new Action(
            key: 'salary_report',
            slot: Action::EMPLOYEE_ROW,           // where the link goes, see below
            label: 'salary_report',               // translation key
            url: fn(array $ctx): ?string =>      // null hides the link for this context
                route_url('salary_reports.create', ['id' => $ctx['store_id']]) . '?user_id=' . $ctx['user_id'],
            icon: '💰',
            style: Action::DEFAULT,               // DEFAULT, PRIMARY or DANGER
            permission: 'payroll.generate',
        ),
    ];
}
```

Slots and the context each one passes to `url`:

| Slot | Where | Context |
|---|---|---|
| `Action::EMPLOYEE_ROW` | "⋮" menu of a row of the staff list | `user_id`, `store_id` (main store) |
| `Action::EMPLOYEE_STORE` | "Reports" card of the employee form, once per store | `user_id`, `store_id`, `store_name` |
| `Action::STORE_REPORT` | Header of a store's employee report | `store_id` |
| `Action::STORE_REPORT_ROW` | Row of a store's employee report, and header of that employee's stats page | `store_id`, `user_id`, `period` |
| `Action::SHIFTS_EMPLOYEE` | Employee schedule toolbar (list, calendar, day) | none |

The Core renders the link in the slot's own style, only for users holding `permission` and, if `storeFeature` is set,
with that feature on. A key already used in the slot is ignored; an exception in `url` drops that link (logged).
The Core adds no link of its own: `shift-swap` ships "Request a swap" this way. Require
`"kintai_contract": "^1.6"`.

### Notifications and activity log labels (contract 1.7)

```php
public function notificationTypes(): array
{
    return ['timeoff_approved' => '✓', 'timeoff_refused' => '✗'];   // type => icon
}
```

Declare the types of the notifications your bundle creates, with their icon; the label is the translation
`notif_{type}` from your bundle's `lang/` files. Core types can't be redefined. Notifications already received keep
their translated label when your bundle is disabled, as long as the translation exists.

Activity log entries you write (`AuditLogger`) get their label the same way: ship `audit_{action}` in your `lang/`
files, dots and dashes replaced by underscores (`timeoff.approved` → `audit_timeoff_approved`). Require
`"kintai_contract": "^1.7"` for `notificationTypes()`; the translation conventions need nothing.

### Notification types: category, channels, automatic "read" (contract 1.23)

```php
public function notificationTypes(): array
{
    return [
        'timeoff_approved' => [
            'icon'           => '✓',
            'category'       => 'requests',                       // planning | requests | messages | reports | system
            'channels'       => ['push' => true, 'email' => true], // defaults of the users' preferences
            'reference_type' => 'timeoff',                         // object designated by the id given to notify()
        ],
        'timeoff_refused' => '✗',                                  // old format: still accepted
    ];
}
```

The enriched format groups the type in the user's notification preferences (profile › Notifications) and sets the
**defaults** of the push and e-mail columns; a user's own choice always wins, and the bell always receives everything.
The old `type => icon` format gets the category `system`, push on, e-mail off and no `reference_type`. A malformed
declaration is ignored and logged, it never prevents the bundle from loading.

Always give `notify()` a **precise link** (the item itself, not the list) and the id of the object as `referenceId`:

```php
$this->notifications->notify($userId, 'timeoff_approved', 'notif_timeoff_approved_body', ['name' => $name], $request->id, route_url('timeoff.show', ['id' => $request->id]));
```

When a user opens the detail page of that object, mark its notifications as read:

```php
$this->notifications->markReadFor($currentUserId, 'timeoff', $request->id);   // an id or a list of ids
```

`markReadFor()` is on `kintai\Contract\Services\NotificationService`; it never throws, so a failure cannot prevent the page
from being displayed. The `referenceType` is the one declared by the type. Require `"kintai_contract": "^1.23"`.

When the bundle has no detail page, link to the list with `?highlight={id}` and give the matching `.data-table` row the
class `tr--highlight` (Core styling), then call `markReadFor()` for the rows displayed.

### Global functions you may call (contract 1.24)

A bundle may call only the global functions declared in `src/Contract/functions.php` (`__`, `csrf_field`, `csrf_token`,
`csp_nonce`, `route_url`, `base_url`, `back_url`, `safe_redirect_path`, `store_segment`, `employee_segment`,
`route_segment`, `bundle_asset`, `translation_or`, `user_locale`, `month_label`, `date_label`, `format_currency`,
`currency_symbol`, `store_currency_style`, `style_class`, `inline_styles`; provisional: `bundle_asset_path`,
`storage_path`). Every other global function of the Core is internal and may change without notice.
`ContractUsageScanner::functionViolations()` (used by the bundle's `ContractUsageTest`) reports any call to a function that
is neither PHP's, nor declared, nor defined by the bundle. Don't `include` a Core view partial either: ship your own.

### Translations: your keys, the Core's keys, overrides (contract 1.25)

- Your own keys carry your slug as a prefix (`daily-report` → `daily_report_title`), plus the reserved conventions
  `bundle_{slug}`, `bundle_{slug}_desc`, `notif_{type}`, `audit_{action}` and `cron_job_{name}`. Provide all three languages
  (`fr`, `en`, `ja`) with the same `:name` parameters.
- You may use the Core keys listed in `src/Contract/translation-keys.php` (`save`, `cancel`, `delete`, month names…) without
  redefining them. Any other Core key is internal.
- To change the wording of a Core key, declare it in `lang/overrides/{locale}.json` (same `:name` parameters). Redefining a
  Core key in `lang/{locale}.json` without declaring it is a collision: reported by `TranslationContractChecker`, ignored by the
  Core in strict mode. The Owner's own edits (Languages screen) win over your override, which wins over the Core.
- `TranslationContractChecker::violations($bundleDir, $slug, $coreLangDir)` returns the violations by rule (`parity`,
  `placeholders`, `prefix`, `collisions`, `overrides`, `unknown`): call it from a test of your bundle.

### Start from a generated skeleton

```bash
php scripts/new-bundle.php my-feature --name="My feature"     # creates ../KintaiBundleDev/kintai-bundle-my-feature
cd ../KintaiBundleDev/kintai-bundle-my-feature && composer install && vendor/bin/phpunit
```

The skeleton already follows the contract (prefixed translations, navigation, permission, route, view, CSP-safe) and its
`tests/ContractTest.php` is the single check to run before every release: manifest schema, classes, functions, translations.
Checklist: idea → permissions → tables and migrations → extension points → routes and RBAC → views without inline code →
translations (fr/en/ja) → tests → local registration → install test → tagged GitHub release → registry entry.



### Scheduled jobs: `cronJobs()` (contract 1.8)

```php
use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

final class SendRemindersJob implements CronJob
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function getName(): string
    {
        return 'timeoff-reminders';               // lowercase letters, digits, dashes
    }

    public function run(Request $request): Response
    {
        return Response::json(['ok' => true, 'sent' => $this->reminders->sendDue()]);
    }
}

// In your Bundle class:
public function cronJobs(): array
{
    return [SendRemindersJob::class];             // resolved by the container
}
```

While your bundle is active, an external scheduler (cron-job.org, a server crontab…) runs the job by calling
`/cron/run/{name}` with a cron token the Owner created — preferably in the `Authorization: Bearer <token>` header,
since a `?token=` query string ends up in web server access logs; `?token=` stays accepted for schedulers that can
only call a URL — typical on shared hosting — or, on a server with SSH access, a local crontab runs `php scripts/cron.php {name}` (no token; extra
URL parameters become `--key=value`). Core names (`backup`,
`log-purge`, `license-check`) and names already taken are refused (logged); a class that can't be built is skipped.
`daily-report` ships `auto-validate` this way. Require `"kintai_contract": "^1.8"`.

Since contract 1.20, a job's label (listed by `php scripts/cron.php`) is found by convention in your `lang/` files:
`cron_job_{name}` with dashes turned into underscores (`cron_job_timeoff_reminders`); without it, the name is shown.

### Profile sections: `profileSections()` (contract 1.12)

```php
use kintai\Contract\Extension\ProfileSection;

public function profileSections(): array
{
    return [
        new ProfileSection(
            key: 'directory',
            view: 'team-directory::profile-section',   // rendered with $user (the signed-in user's row)
            flags: ['show_in_directory', 'share_email', 'share_phone', 'share_mobile_phone'],
            order: 10,
        ),
    ];
}
```

The Core renders your view inside the "My information" form of the profile, before its "Save" button, while your
bundle is active. On save, it writes the `flags` you declared (checkboxes, `0`/`1`, a field missing from the request
keeps its value) into the `users` table — only columns the Core opens to bundles are accepted
(`ProfileSections::WRITABLE_USER_COLUMNS`), anything else is ignored and logged; keep other data in your own tables
and routes. Ship a hidden `0` field before each checkbox so unchecking works. A key already taken is ignored; an
exception while rendering drops that section (logged). The Core has no section of its own: `team-directory` ships
the directory checkboxes this way. Require `"kintai_contract": "^1.12"`.

### Core events: `eventListeners()` (contract 1.13)

```php
use kintai\Contract\Event\EmployeeCreated;

public function eventListeners(): array
{
    return [
        EmployeeCreated::class => function (EmployeeCreated $event): void {
            // $event->user (users row, without secrets), $event->storeId (?int), $event->createdBy (?int),
            // $event->source ('form', 'excel_import' or 'api')
        },
    ];
}
```

The Core announces what happens on its side so your bundle can react without the Core knowing about it. One listener
per event class (`kintai\Contract\Event\…`); anything else is ignored and logged. A listener that throws is logged and
never stops the Core action nor the other bundles' listeners. Available events:

| Event | When | Data |
|---|---|---|
| `EmployeeCreated` | right after an account is created: "New employee" form, Excel import quick-create, `POST /api/v1/users` | `user`, `storeId` (store the employee was assigned to, `null` if none — always `null` from the API), `createdBy`, `source` |
| `OpenShiftClosed` (1.17) | a shift published to the open-shift board stops being so (`is_open` 1 → 0) from the shift form or `PUT /api/v1/shifts/{id}` | `shift` (the saved row), `closedBy` |

`hiring-report` creates the employee's hiring report this way, and `shift-claim` withdraws the pending claims of a
closed open shift. Require `"kintai_contract": "^1.13"` (`^1.17` for `OpenShiftClosed`).

### Dashboard stat cards: `dashboardStats()` (contract 1.19)

```php
use kintai\Contract\Extension\DashboardStat;

public function dashboardStats(): array
{
    return [
        new DashboardStat('pending_swaps', 'swaps',
            fn(array $context): int => $this->countPendingFor((int) $context['user']['id']),
            icon: '🔄', tone: 'danger', storeFeature: 'swaps', order: 20),
    ];
}
```

The Core shows your card in the stat row at the top of the employee dashboard, after its own cards (today's and
upcoming shifts), sorted by `order`. `value` receives the same context as dashboard widgets (`user`, `store_ids`,
`stores_map`, `today`) and returns what to display; `tone` is one of `DashboardStat::TONES`. With `storeFeature`, the
card only shows when that feature is on. A key already taken is ignored; an exception drops that card (logged).
The Core has no card of its own for bundles: `timeoff` and `shift-swap` ship theirs this way. Require
`"kintai_contract": "^1.19"`.

### Shift form sections: `shiftFormSections()` (contract 1.17)

```php
use kintai\Contract\Extension\ShiftFormSection;

public function shiftFormSections(): array
{
    return [
        new ShiftFormSection(
            key: 'open_shift',
            view: 'shift-claim::shift-form-section',          // rendered with $shift, $mode ('create'|'edit'), $BASE_URL
            fields: ['is_open', 'open_shift_note'],
            actionsView: 'shift-claim::shift-form-actions',   // edit page only, next to "Save"
            storeFeature: 'open_shifts',
        ),
    ];
}
```

The Core renders your `view` inside the shift form (create page, edit page and the timeline's dialog) and, on the
edit page, your `actionsView` in the action bar. Both sit **inside** the shift form: a button that targets your own
route uses `formaction`/`formmethod` (with `formnovalidate`) — never a nested `<form>`, which browsers ignore. On save,
the Core writes the `fields` you declared into the shift, and only those, among the columns it opens to bundles
(`ShiftFormSections::WRITABLE_SHIFT_COLUMNS`: `is_open`, `open_shift_note`); a field nobody declares is ignored, so a
shift cannot be opened without the bundle. With `storeFeature`, the section only shows when that feature is on. A key
already taken is ignored; an exception while rendering drops that section (logged). The Core has no section of its
own: `shift-claim` ships the open-shift fields and buttons this way. Require `"kintai_contract": "^1.17"`.

## Providers: data the Core reads from your bundle (contract 1.9+)

Some Core pages need data your bundle owns. Instead of reading your tables, the Core asks a **provider** your bundle
implements. Without an active provider the Core simply shows less — never an error.

### Pending requests: `pendingRequestsProviders()` (contract 1.9)

```php
use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;

final class PendingLeaveProvider implements PendingRequestsProvider
{
    public function __construct(private readonly LeaveRepository $leaves) {}

    public function key(): string { return 'timeoff'; }
    public function label(): string { return 'pending_timeoff'; }         // section title (translation key)
    public function emptyLabel(): string { return 'no_pending_request'; }
    public function permission(): string { return 'timeoff.view'; }
    public function storeFeature(): ?string { return 'timeoff'; }
    public function order(): int { return 10; }
    public function manageUrl(): string { return route_url('admin.timeoff'); }

    public function pending(?array $storeIds): array                      // null = every store
    {
        return array_map(fn(array $r) => new PendingRequest(
            id: (int) $r['id'], storeId: (int) $r['store_id'], userId: (int) $r['user_id'], createdAt: $r['created_at'],
            columns: [
                ['label' => 'user', 'value' => (int) $r['user_id'], 'style' => PendingRequest::USER],
                ['label' => 'type', 'value' => $r['type'], 'style' => PendingRequest::BADGE],
                ['label' => 'from', 'value' => $r['start_date']],
            ],
        ), $this->leaves->pendingForStores($storeIds));
    }
}

// In your Bundle class:
public function pendingRequestsProviders(): array
{
    return [new PendingLeaveProvider($this->app->container()->make(LeaveRepository::class))];
}
```

The Core uses it for the "Requests" page (one section per provider, a table built from your columns), the
dashboard's "Pending requests" counter and stale-request alerts — only for users holding `permission`. Column
styles: `TEXT`, `BADGE`, `USER` (user id, shown as a name), `STORE` (store id, shown as a name), `TIME` (`HH:MM`).
Return requests in display order. An exception drops your section only (logged). The Core has no provider of its
own. Require `"kintai_contract": "^1.9"`.
The manager dashboard's "stale requests" alert also reads them: a request pending for more than three days is
listed with its provider's `label()`.

### Absences: `absenceProviders()` (contract 1.10)

```php
use kintai\Contract\Provider\Absence;
use kintai\Contract\Provider\AbsenceProvider;

final class LeaveAbsences implements AbsenceProvider
{
    public function absences(?array $userIds, ?array $storeIds): array   // null = no filter
    {
        return array_map(fn(array $r) => new Absence(
            (int) $r['id'], (int) $r['user_id'], (int) $r['store_id'],
            $r['start_date'], $r['end_date'],                              // Y-m-d, end included
            $r['type'], $r['status'],                                      // Absence::APPROVED, PENDING, REFUSED…
        ), $this->leaves->find($userIds, $storeIds));
    }
}
```

Return absences of **every** status: the Core filters dates and statuses itself. It uses them to flag a shift
planned during an approved absence, to show absences on the schedule, in the employee's iCal feed and in the
absenteeism statistics. Absences of every declared provider are added up; a failing provider is skipped (logged).
Without any provider (`timeoff` disabled or uninstalled), the Core sees no absence. Require
`"kintai_contract": "^1.10"`.

### Attendance: `attendanceProviders()` (contract 1.11)

```php
use kintai\Contract\Provider\Attendance;
use kintai\Contract\Provider\AttendanceProvider;

final class ClockAttendance implements AttendanceProvider
{
    public function attendance(?array $userIds, ?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        return array_map(fn(array $r) => new Attendance(
            (int) $r['id'], (int) $r['user_id'], (int) $r['store_id'],
            $r['shift_date'],                  // Y-m-d, the day worked ($from/$to filter on it, inclusive)
            $r['clock_in'],                    // Y-m-d H:i:s
            $r['clock_out'],                   // Y-m-d H:i:s, or null while still clocked in
        ), $this->clocks->find($userIds, $storeIds, $from, $to));
    }
}
```

The Core uses it on the manager dashboard (clock-ins left open, late arrivals compared with the planned shift).
Entries of every declared provider are added up; a failing provider is skipped (logged). Without any provider
(`timeclock` disabled or uninstalled), the Core sees no clock-in. Require `"kintai_contract": "^1.11"`.

### Sales: `salesProviders()` (contract 1.14)

```php
use kintai\Contract\Provider\DailySales;
use kintai\Contract\Provider\SalesProvider;

final class ReportSales implements SalesProvider
{
    public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
    {
        return array_map(fn(array $r) => new DailySales(
            $r['report_date'],                 // Y-m-d ($from/$to inclusive)
            $r['status'],                      // filter on $statuses (when given) before anything else
            (float) $r['sales_total'],         // the day's value, not a running total
            (float) $r['labor_cost'],
            (int) $r['customer_count'],
            (float) $r['waste_total'],
        ), $this->reports->forStore($storeId, $from, $to, $statuses));
    }
}
```

The Core reads it for a store's profitability page, and bundles read it through `kintai\Contract\Services\Sales`
(`dailySales($storeId, $from, $to, $statuses)` returns rows sorted by date: `report_date`, `status`, `sales_total`,
`labor_cost`, `customer_count`, `waste_total`) — `salary-report` pre-fills its sales total this way. If your data is
entered as running totals, convert it to the day's values yourself, after the status filter. Days of every declared
provider are merged; a failing provider is skipped (logged). Without any active provider there are no sales, and the
Core hides a store's profitability page. `daily-report` is the sales provider. Require `"kintai_contract": "^1.14"`.

### Shift swaps: `swapProviders()` (contract 1.18)

```php
use kintai\Contract\Provider\Swap;
use kintai\Contract\Provider\SwapProvider;

final class RequestSwaps implements SwapProvider
{
    public function swaps(?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        return array_map(fn(array $r) => new Swap(
            (int) $r['id'], (int) $r['store_id'],
            (int) $r['requester_id'], (int) $r['target_user_id'],
            $r['created_at'],                  // Y-m-d H:i:s ($from/$to filter on its date, inclusive)
            $r['status'],                      // every status, the Core filters what it needs
        ), $this->requests->find($storeIds, $from, $to));
    }
}
```

The Core counts an employee's swaps with it ("Swaps" column of a store's employee report, employee stats page).
Swaps of every declared provider are merged; a failing provider is skipped (logged). Without any provider (`shift-swap`
disabled or uninstalled), there is no swap to count. Require `"kintai_contract": "^1.18"`.

## The RBAC scope contract: `managed_store_ids`

Every Web route your bundle registers under `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` must declare a `permission:` — either a precise key from `PermissionCatalog`, or `'public'` (a route intentionally left ungated, e.g. self-service/aggregate pages; omitting `permission:` entirely behaves the same way). `PermissionMiddleware` resolves this into a request attribute, `managed_store_ids`, that every controller down the line is expected to read rather than re-deriving:

- **`null`** — unrestricted. Either the user is Owner, or their role grants your route's permission key with the "all stores" scope checked (`role_permissions.scope = 'global'`) — which can happen even when the *assignment* linking them to that role is itself store-scoped to their home store. Both cases mean the exact same thing: no store filtering.
- **`int[]`** — restricted to exactly these store IDs.

**The one mistake that reintroduces this bug every time**: writing `$request->getAttribute('managed_store_ids') ?? []` instead of branching on `=== null`. It looks harmless because several Core repository methods treat an *empty* filter array as "no filter" — so a list query downstream can keep "working" by coincidence — but the next thing reading the same collapsed value (a store-picker dropdown, an `in_array($storeId, $managedIds)` access check) sees "zero stores", not "every store", and breaks. This exact mistake shipped in `kintai-bundle-daily-report`'s `DailyReportController::indexAll()` (fixed in `fix/global-scope-permission-store-filter`) — always handle `null` as its own branch, mirroring `is_admin`, not as something a `??` fallback can absorb.

Don't re-derive this resolution logic yourself. Reuse the contract's `kintai\Contract\Http\Concerns\HasAdminAccess` trait — `managedIds(Request $request): ?array`, `availableStores(?array $managedIds): array`, `assertStoreAccess(Request $request, int $storeId): void`, `assertAnyStoreAccess(Request $request, array $storeIds): void` — the same trait every official bundle's admin controller uses (see `StorePhotoController` in `kintai-bundle-store-photos` for a real example). It already gets the `null` case right; a hand-rolled equivalent in your own controller is exactly how this class of bug gets reintroduced, one bundle at a time.

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
- **Requires a Core that ships typed route parameters** (see its CHANGELOG): require a `kintai_contract` that such a Core provides, since an older Core would read `{id:store}` as a literal segment.

## Database migrations

A bundle can own its own table(s) — a dedicated PR against Kintai's own repository is **not** required just to create a schema anymore. Drop an optional `database/migrations/` directory at your bundle's root (a sibling of `src/`, same rule as `Views/`/`routes.php`), containing files in the **exact same format** as Kintai's own Core migrations (`database/migrations/php/*.php`):

```php
<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
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

Name files `YYYY_MM_DD_NNNNNN_description.php` — alphabetical sort is execution order, exactly like Core migrations. Always guard `up()` with `hasTable()`/`hasColumn()` (as above): migrations must be idempotent, since they can be replayed by a manual re-sync (see below). Always go through `$this->schema()`: it gives your tables the same portable schema as the Core's under SQLite and MySQL (signed primary keys matching the `integer()` columns that reference them). A default value on a `text()` column must use `$this->textDefault('…')` — MySQL refuses a plain literal there.

**When they run**: automatically, right after your bundle's files land on disk during install or update from `/admin/bundles/market` (`BundleInstallerService::activate()`) — before the bundle is marked active, so a failing migration aborts the whole install/update rather than activating a bundle with an incomplete schema. They can also be re-synced manually, for every installed bundle at once, with `php scripts/db-migrate.php` (`--dry-run` previews what's pending, same flag as for Core migrations).

**Tracking**: applied bundle migrations are recorded in a `bundle_migrations` table (`bundle_slug` + `migration` name, unique together) — separate from Core's own `migrations` table, so two different bundles can never collide on a migration file name, and so `BundleMigrationRunner::forgetBundle()` can clear just your bundle's tracking rows on uninstall. Business tables themselves are **not** dropped on uninstall (see Limitations) — only the tracking rows are, so a later reinstall replays your `up()` methods cleanly (their `hasTable()` guards make that a no-op if the tables are still there).

Your repository interface, Eloquent model and tables live in your bundle's own namespace, like every official bundle's (see "Bundle-owned classes" above).

## Assets

If your bundle needs its own CSS or JS, don't ask for it to be added to Kintai's own `public/assets/` — that was the situation for every bundle before this mechanism existed, and it defeated the entire point of distributing bundles as separate repositories (a CSS tweak meant a Core PR). Ship it yourself, from your own `public/` directory (a sibling of `src/`, `Views/`, `routes.php` — same rule as everything else in the required layout above):

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
    "slug": "notebook",
    "name": "Carnet de notes",
    "version": "1.0.0",
    "description": "Team notebook — notes shown on the manager and employee dashboards, pinning, automatic expiry.",
    "author": { "name": "Your Name", "email": "you@example.com", "url": "https://example.com" },
    "kintai_contract": "^1.19",
    "requires_bundles": {},
    "namespace": "kintai\\Bundles\\Installed\\Notebook",
    "entry_class": "kintai\\Bundles\\Installed\\Notebook\\NotebookBundle",
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
| `kintai_contract` | Yes | Semver constraint on the **bundle contract** version (see [Versioned bundle contract](#versioned-bundle-contract)), e.g. `^1.19`. It is the only compatibility check: a bundle without it predates the versioned contract and is refused at install time and never loaded at boot. |
| `kintai_core.min`/`.max` | No — **informational** | Ignored by the compatibility check since the contract became mandatory (bundles published before it declared an open range and broke when the Core removed internal classes). |
| `requires_bundles` | No (defaults `{}`) | Slug → version constraint. **Informational only for now** — see Limitations. |

## Versioned bundle contract

The bundle contract has **its own version**, independent of the Core's: `kintai\Contract\Contract::VERSION` (currently `1.23.0`). It follows semver — a minor version only adds things (a class, an optional method, an extension point), a major version removes or changes a signature and is announced in a dedicated "Bundle contract" section of the CHANGELOG.

Declare the contract version your bundle needs in `bundle.json`:

```json
"kintai_contract": "^1.0"
```

Supported constraints (Composer rules): `^1.2` (>= 1.2.0, < 2.0.0), `~1.2` / `~1.2.3`, `>=1.0 <2.0` (space = AND), `^1.0 || ^2.0` (OR), `*`, and an exact version.

The Core checks it **twice**:

- **at install time** (market): an incompatible bundle is refused before anything is written to disk;
- **at boot**: an installed bundle that became incompatible (e.g. after a Core update that raised the contract's major version) is **not loaded**, even if enabled — the application keeps running, the event is logged (`bundle_incompatible_not_loaded`) and the Bundles page shows the reason so the Owner can install a compatible version;
- **before a Core update**: `/admin/update` reads the contract version of the offered release and lists the installed bundles it would no longer load.

A bundle without `kintai_contract` is treated as incompatible everywhere, including one dropped by hand into `src/Bundles/{Name}/` (which needs a `bundle.json` next to its class).

`kintai_core.min`/`max` is informational only (see the manifest table above): `kintai_contract` is the only compatibility check.

## Publishing a release

1. Bump `version` in `bundle.json` (and `getVersion()` in your entry class — they must agree).
2. Tag the commit `vX.Y.Z` and push the tag.
3. Create a GitHub Release for that tag (`gh release create vX.Y.Z --generate-notes`, or a CI workflow that does the same on tag push — see `kintai-bundle-notebook`'s `.github/workflows/release.yml`). Nothing to build: the Release's auto-generated `zipball_url` is exactly what `BundleInstallerService` downloads.

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

**Version requirement.** The `data-*` actions and `csp_nonce()` exist from **Kintai Core 0.3.0**. Every Core that provides the bundle contract (1.0 shipped with Core 0.3.6) has them, so declaring `kintai_contract` — now mandatory — is enough: an older Core refuses the bundle instead of installing controls that do nothing. Conversely, this is a **breaking change for existing bundles**: a bundle written before 0.3.0 that still uses inline handlers keeps working on a Core older than 0.3.0, but its buttons, selects and confirmations stop working on 0.3.0 and later until it is migrated. `tests/Unit/Security/NoInlineScriptGuardTest.php` in Kintai fails as soon as a Core view reintroduces an inline handler; do the same check on your own views.

### Dynamic styles: no `style="…"`

The same rule applies to styles since contract 1.22: the CSP sends `style-src 'self' 'nonce-…'` (no `'unsafe-inline'`), so a `style="…"` attribute is ignored by the browser and a `<style>` block only applies with the nonce — silently, like scripts. **Breaking for bundles written before 1.22** that still use `style="…"`: their colours, widths and positions are lost until migrated. Static presentation belongs in your bundle's CSS. A value only known at render time (a colour picked by the user, a bar's height, a percentage) goes through `style_class()` (contract 1.22), which returns a class carrying those declarations:

```php
<div class="bar <?= style_class(['height' => $h . 'px', 'background' => $color]) ?>"></div>
<span class="avatar-chip <?= style_class(['--chip-bg' => $user['color']]) ?>"></span>
```

The rules are collected during the request and emitted in a single `<style nonce="…">` by the layout. A page rendered **without** the layout (print view, PDF preview) must emit them itself with `<?= inline_styles() ?>` just before `</body>`, and put the nonce on its own `<style>` blocks: `<style nonce="<?= csp_nonce() ?>">`. Values are filtered (letters, digits, `#`, `%`, `.`, `,`, `-`, spaces and parentheses only): anything else is dropped, so user data cannot escape the rule. Setting `element.style.x` from JavaScript is not affected by the CSP and stays allowed; building HTML with a `style="…"` attribute in JavaScript is not.

### Migrating an existing bundle

1. Find the offenders: `grep -rnE "[[:space:]]on(click|change|submit|input)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|javascript:|<script>" Views src`.
2. Replace each with the matching `data-*` attribute from the table above. A `confirm()` becomes `data-confirm`; a handler that called `event.stopPropagation()` needs `data-stop-propagation`, otherwise a delegated ancestor fires too.
3. Make sure every function called through `data-on-*` is defined **on `window`**: a function declaration in a classic script, or `window.f = …`. A top-level `const f = …` or `let f = …` is not a property of `window` and is refused.
4. Put the nonce on the inline `<script>` blocks you keep: `<script nonce="<?= csp_nonce() ?>">`.
5. Declare `kintai_contract` and add the CI check below.

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
- name: No inline styles (Kintai CSP)
  run: |
    set -euo pipefail
    if grep -rnE "[[:space:]]style[[:space:]]*=[[:space:]]*[\"'\\\\]|<style>" Views; then
      echo "::error::style=\"...\" attributes and <style> without nonce are ignored by Kintai's CSP"
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
- `bundle.json` inside your repository remains the actual source of truth for compatibility (`kintai_contract`) and everything else read at install time — `versions` here is only ever a hint for the catalog UI about what's installable and on which channel.
- `commits` (optional, added to schema 2 without a version bump — older Kintai versions ignore it) maps each version to the full 40-character sha of the commit its tag points to, e.g. `"commits": {"1.0.2": "7f99e084…"}`. When present, Kintai checks the downloaded archive against it **before extracting anything** (a GitHub zipball carries its commit in the ZIP comment and in the name of its root folder) and refuses the install on a mismatch, so a tag moved to another commit (compromised repository or account) can't slip a different code base past the registry. The official registry fills it in automatically: the same hourly `sync-versions.js` run pins each new release's commit, never rewrites an existing pin, and fails loudly if a tag has moved — the pin only reaches `main` through the pull request a maintainer reviews. Kintai reads the expected commit from the registry server-side (never from the request), and refuses to install if the registry can't be reached to check it. A registry that doesn't publish `commits` keeps working as before (a warning is logged). This is not a signature: it relies on GitHub generating the archive and on HTTPS, and the registry itself remains the trust anchor. If you maintain your own registry, `sync-versions.js` from `AudricSan/KintaiBundle` can be reused as is.

Two ways to get discovered:
- **Your own registry** — write and host a `registry.json` yourself (anywhere reachable over HTTPS), then anyone can add its URL from `/admin/bundles/registries`. No approval needed from anyone.
- **The official Kintai registry** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — open a pull request adding an entry to its `registry.json`. Being listed there does **not** make your bundle "official" — see below.

## Official vs. third-party

`config/official-bundles.php`, inside Kintai's own repository, is the **only** source of truth for which slugs are maintained by the Kintai project itself — a bundle can't self-declare as official, whichever registry lists it. Anything else is flagged "third-party" in the catalog UI, and installing or updating it requires an explicit `confirm_third_party` checkbox, enforced server-side (not just hidden by JavaScript) — expect your users to see a warning, and design your bundle assuming they'll read it before proceeding.

## Known limitations (as of this writing)

- **One process, one Composer autoloader.** There's no per-bundle `composer.json` or dependency isolation — your bundle runs in the same PHP process and namespace tree as Kintai's own `kintai\` root. Depend only on the `kintai\Contract\…` names listed above: they are the only Core classes guaranteed across Kintai versions.
- **`requires_bundles` isn't enforced yet.** Declaring a dependency on another bundle's slug/version is accepted and stored, but nothing currently blocks installation if it's missing or too old — treat it as documentation for now, not a guarantee.
- **No uninstall flow for business data.** `/admin/bundles/market` can install, update, and uninstall a bundle's files/manifest entry/migration tracking rows — but the tables your migrations created (and their data) are never dropped or touched on uninstall. Cleaning those up, if desired, is a manual DB operation for now.
- **Inline scripts and event handlers don't work.** From Kintai Core 0.3.0 the Content-Security-Policy has no `'unsafe-inline'` for scripts: a bundle view with `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, a `javascript:` link or a nonce-less `<script>` is silently blocked by the browser. Since contract 1.22 the same goes for styles: a `style="…"` attribute or a nonce-less `<style>` is ignored (use `style_class()`). See [Content Security Policy](#content-security-policy-no-inline-scripts).
- **GitHub only.** `repository_url` must point at a GitHub repository — no GitLab, no self-hosted Git server, no non-Git archive source.

## Reference implementation

[`kintai-bundle-notebook`](https://github.com/AudricSan/kintai-bundle-notebook) is a small, complete example of everything above: its own model, repository and migrations, declared permissions, store feature, dashboard widgets and notification type, tests running against the real Core. The former pilot, `kintai-bundle-feedback`, has been retired (the Core no longer loads it).
