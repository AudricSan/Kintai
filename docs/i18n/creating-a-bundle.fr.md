# Créer un bundle

🌐 [English](../creating-a-bundle.md) · **Français** · [日本語](creating-a-bundle.ja.md)

Ce document s'adresse à quiconque écrit un bundle Kintai destiné à être **distribué indépendamment** — son propre dépôt Git, son propre historique de versions, installable depuis `/admin/bundles/market` sans jamais toucher au code de Kintai lui-même. Il ne couvre pas l'ancien modèle, toujours pris en charge, consistant à déposer un bundle directement dans `src/Bundles/` du dépôt principal (voir `docs/architecture.md` → « Modular Bundles ») — ce dossier est toujours scanné pour un bundle déposé à la main, mais chaque bundle officiel est distribué selon le modèle décrit ici, et un nouveau bundle, surtout tiers, devrait l'être aussi.

`kintai-bundle-notebook` (le carnet d'équipe : son propre modèle, son dépôt, ses migrations, ses vues, ses widgets de tableau de bord et ses déclarations) est un exemple réel, petit et complet — utile à garder ouvert dans un second onglet pendant la lecture.

## Pourquoi un dépôt séparé, et pourquoi pas de `git clone`

Kintai ne lance jamais `git clone`/`git pull` pour récupérer un bundle — l'installeur (`BundleInstallerService`) télécharge le **zipball d'une Release GitHub étiquetée** en HTTP simple (avec repli curl → flux PHP, voir `HttpFetcher`), exactement le mécanisme que Kintai utilise déjà pour se mettre à jour (`GithubUpdateService`). C'est voulu : beaucoup d'hébergements mutualisés (ceux que Kintai vise) ne garantissent pas qu'un binaire `git` soit accessible depuis PHP, alors qu'une requête HTTPS sortante fonctionne toujours.

Conséquences qui structurent tout ce qui suit :
- Votre bundle a besoin d'**au moins une Release GitHub étiquetée `vX.Y.Z`** — une simple étiquette ne suffit pas, `zipball_url` n'existe que sur une vraie Release.
- L'unique dossier racine du zipball devient la racine du bundle installé — son nom donné par GitHub (`{owner}-{repo}-{sha}`) n'a aucune importance, seul son contenu compte.
- Rien à construire ni à joindre à la release : GitHub calcule `zipball_url` automatiquement à partir du commit étiqueté.

## Arborescence requise

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

Deux pièges faciles :
- **Seules votre classe d'entrée (et ce qui partage son espace de noms) vont sous `src/`.** `routes.php`, `Views/` et `lang/` sont à la racine du bundle, à côté de `src/` — pas dedans. Cela suit le champ `namespace` de `bundle.json`, qui est une racine PSR-4 pour `src/` uniquement.
- `Bundle::getPath()` renvoie la **racine** du bundle (le dossier qui contient `bundle.json`), quel que soit le dossier où se trouve le fichier de votre classe — `loadRoutesFrom($this->getPath() . '/routes.php')` et `loadViewsFrom($this->getPath() . '/Views', 'your-namespace')` se résolvent correctement tant que vous suivez l'arborescence ci-dessus.

## Le contrat stable : `kintai\Core\BundleContract\Bundle`

Votre classe d'entrée étend cette classe abstraite — la seule partie de `kintai\Core\*` qui suit son propre versionnage sémantique, annoncé dans le CHANGELOG quand elle change (ajouter une méthode facultative est mineur ; en modifier ou en retirer une est un changement majeur documenté). Rien d'autre sous `kintai\Core\` n'offre cette garantie.

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

Un exemple réel et minimal (la vraie classe d'entrée de `kintai-bundle-notebook`, raccourcie) :

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

Votre espace de noms est libre — par convention, les bundles installés utilisent `kintai\Bundles\Installed\{Name}\`, volontairement distinct de la convention de l'ancien monorepo (`kintai\Bundles\{Name}\`) pour que les deux mécanismes de chargement (le PSR-4 de Composer pour `src/Bundles/`, et un `spl_autoload_register` dédié pour tout ce qui est sous `storage/bundles/`) ne puissent jamais se disputer le même nom de classe.

**Un bundle ne dépend jamais des dépôts ni des modèles du Core** : il possède les siens (comme ci-dessus) et n'utilise que les noms `kintai\Contract\…` listés ci-dessous. Les données dont le Core a besoin passent par un fournisseur déclaré.

## La surface du contrat : `kintai\Contract\…`

Un bundle ne peut utiliser que les noms ci-dessous (plus ses propres classes). Chacun est un **alias stable** de la classe du Core qui l'implémente aujourd'hui (`src/Contract/surface.php` est la liste de référence), si bien que le Core peut réorganiser son propre code sans casser les bundles. Importez-les exactement comme n'importe quelle classe — indications de type, `instanceof`, `catch` et injection par constructeur fonctionnent tous ; le conteneur résout un alias vers la liaison de la classe qu'il désigne.

```php
use kintai\Contract\Bundle;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreRepositoryInterface;
```

Les noms **stables** sont garantis pour toute la version majeure du contrat. Les noms **provisoires** sont exposés pour la transition et seront remplacés par une étape ultérieure du plan du contrat (voir leur description) ; ils ne disparaissent qu'avec une nouvelle version majeure.

| Nom | Statut | Rôle |
|---|---|---|
| `kintai\Contract\Bundle` | stable | Classe de base de tout bundle (point d'entrée). |
| `kintai\Contract\BundleManifest` | stable | bundle.json analysé. |
| `kintai\Contract\Container` | stable | Conteneur de dépendances (enregistrez vos propres liaisons). |
| `kintai\Contract\Cron\CronJob` | stable | Tâche planifiée déclarée par `Bundle::cronJobs()` (contrat 1.8). |
| `kintai\Contract\Http\Request` | stable | Requête HTTP. |
| `kintai\Contract\Http\Response` | stable | Réponse HTTP. |
| `kintai\Contract\Http\Router` | stable | Déclaration des routes (routes.php). |
| `kintai\Contract\Http\Paginator` | stable | Pagination des listes de l'API. |
| `kintai\Contract\Http\Middleware\AuthMiddleware` | stable | Session web requise. |
| `kintai\Contract\Http\Middleware\PermissionMiddleware` | stable | Permission web requise. |
| `kintai\Contract\Http\Middleware\ApiAuthMiddleware` | stable | Jeton d'API requis. |
| `kintai\Contract\Http\Middleware\ApiPermissionMiddleware` | stable | Permission d'API requise. |
| `kintai\Contract\Http\Concerns\HasBaseUrl` | stable | Préfixe d'URL (installation en sous-dossier). |
| `kintai\Contract\Http\Concerns\HasAdminAccess` | **provisoire** | Périmètre de magasins du gérant ; remplacé par CurrentUser/Gate (étape 2). |
| `kintai\Contract\Http\Concerns\HasStoreFeatureCheck` | **provisoire** | Vérification d'une fonctionnalité par magasin ; remplacée par le point d'extension storeFeatures (étape 2). |
| `kintai\Contract\Http\Concerns\HasStaffReportCrud` | **provisoire** | CRUD partagé des rapports RH ; déplacé dans les bundles concernés (étape 4). |
| `kintai\Contract\Exceptions\NotFoundException` | stable | 404. |
| `kintai\Contract\Exceptions\ForbiddenException` | stable | 403. |
| `kintai\Contract\Exceptions\ValidationException` | stable | 422 avec erreurs par champ. |
| `kintai\Contract\Exceptions\ConflictException` | stable | 409. |
| `kintai\Contract\Auth\PermissionService` | stable | Vérification des permissions et des périmètres. |
| `kintai\Contract\Auth\AuthService` | **provisoire** | Utilisateur de la session ; remplacé par CurrentUser (étape 2). |
| `kintai\Contract\Auth\UserAnonymizer` | stable | Suppression d'un compte : `anonymize($userId)` efface les données personnelles, désactive le compte et retire ses droits et jetons, l'historique reste rattaché à « Utilisateur supprimé » (contrat 1.21 ; n'appelez jamais `UserRepositoryInterface::delete()` pour cela). |
| `kintai\Contract\Data\UserRepositoryInterface` | stable | Comptes. |
| `kintai\Contract\Data\StoreRepositoryInterface` | stable | Magasins. |
| `kintai\Contract\Data\StoreUserRepositoryInterface` | stable | Adhésions aux magasins. |
| `kintai\Contract\Data\ShiftRepositoryInterface` | stable | Shifts. |
| `kintai\Contract\Data\ShiftTypeRepositoryInterface` | stable | Types de shift. |
| `kintai\Contract\Data\UserShiftTypeRateRepositoryInterface` | stable | Taux horaires individuels. |
| `kintai\Contract\Data\AppSettingsRepositoryInterface` | stable | Paramètres de l'instance. |
| `kintai\Contract\Database\Migration` | stable | Classe de base des migrations du bundle. |
| `kintai\Contract\Services\AuditLogger` | stable | Journal d'activité. |
| `kintai\Contract\Services\NotificationService` | stable | Notifications (et push). |
| `kintai\Contract\Services\TranslationService` | stable | Traductions. |
| `kintai\Contract\Services\ImageCompressionService` | stable | Compression d'images. |
| `kintai\Contract\Services\Mailer` | stable | Envoie un e-mail par le transport configuré de l'instance : `send($to, $subject, $html, $attachmentPaths)` (contrat 1.15). |
| `kintai\Contract\Services\PdfCjkFontResolver` | stable | Police japonaise des documents mPDF : `PdfCjkFontResolver::applyTo($mpdfConfig)` (contrat 1.16). |
| `kintai\Contract\Services\Sales` | stable | Ventes quotidiennes d'un magasin, lues auprès des fournisseurs de ventes (contrat 1.14, voir « Ventes » ci-dessous). |
| `kintai\Contract\Services\ShiftWageCalculator` | stable | Coût d'un shift (source unique du calcul de paie). |
| `kintai\Contract\Services\ShiftServiceInterface` | **provisoire** | Création de shifts ; remplacée par une API d'écriture ciblée (étape 3). |
| `kintai\Contract\Services\StoreStatsServiceInterface` | **provisoire** | Statistiques de magasin ; remplacées par des fournisseurs (étape 3). |
| `kintai\Contract\View\ViewRenderer` | stable | Rend les vues du bundle. |
| `kintai\Contract\View\Components\Badge` | stable | Composant d'interface (`Badge`). |
| `kintai\Contract\View\Components\Button` | stable | Composant d'interface (`Button`). |
| `kintai\Contract\View\Components\Card` | stable | Composant d'interface (`Card`). |
| `kintai\Contract\View\Components\EmptyState` | stable | Composant d'interface (`EmptyState`). |
| `kintai\Contract\View\Components\FilterBar` | stable | Composant d'interface (`FilterBar`). |
| `kintai\Contract\View\Components\Flash` | stable | Composant d'interface (`Flash`). |
| `kintai\Contract\View\Components\Form` | stable | Composant d'interface (`Form`). |
| `kintai\Contract\View\Components\Modal` | stable | Composant d'interface (`Modal`). |
| `kintai\Contract\View\Components\Pagination` | stable | Composant d'interface (`Pagination`). |
| `kintai\Contract\View\Components\Table` | stable | Composant d'interface (`Table`). |

### Classes propres au bundle

Un bundle possède ses modèles, ses dépôts, ses services et ses tables : aucun ne vit plus dans le Core (l'étape 4 du plan est terminée pour chaque bundle officiel). Un bundle n'importe que `kintai\Contract\…` ; son test d'utilisation du contrat n'a plus aucune exception.

### Vérifier votre bundle

`tests/Support/ContractUsageScanner.php` de Kintai (chargé par le harnais PHPUnit des bundles) signale chaque référence à `kintai\Core\…`, `kintai\UI\…` ou `kintai\Domain\…` hors du contrat :

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

## Points d'extension (contrat 1.1+)

Le Core ne nomme jamais un bundle : un bundle **déclare** ce qu'il ajoute à l'interface en redéfinissant des méthodes de sa classe d'entrée, et le Core l'affiche. Chaque bundle est interrogé isolément — une exception ou une valeur du mauvais type retire sa contribution et est journalisée, la page continue de fonctionner. Exigez `"kintai_contract": "^1.1"` pour les utiliser.

### Navigation : `navigation()`

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

Une entrée ne s'affiche que si le rôle courant a une route, détient la permission déclarée sur cette route et — si `storeFeature` est renseigné — a cette fonctionnalité activée. Les utilisateurs peuvent la masquer dans leurs préférences de navigation.

### Permissions : `permissions()` (contrat 1.2)

```php
public function permissions(): array
{
    return [
        'timeoff' => ['view', 'create', 'update', 'delete', 'approve'],  // category => actions
    ];
}
```

Chaque paire `catégorie.action` devient une clé de permission que vous pouvez poser sur vos routes (`permission: 'timeoff.approve'`) et que l'Owner accorde depuis la page des rôles tant que votre bundle est actif. Les noms sont en `snake_case` minuscule. Les catégories du Core (`employees`, `shifts`, `stores`, `payroll`) ne peuvent pas être redéfinies, et une catégorie déjà déclarée par un autre bundle est ignorée (journalisé). La page des rôles affiche une catégorie avec la clé de traduction `perm_category_{catégorie}` et une action avec le libellé du Core pour les actions courantes (`view`, `create`, `update`, `delete`, `approve`, …), sinon avec le nom de l'action lui-même comme clé de traduction — fournissez ces clés dans le `lang/` de votre bundle. Désactiver le bundle masque la catégorie de la page des rôles mais conserve les droits en base : le réactiver les restaure. À la première installation du bundle (assistant de configuration ou catalogue), chaque permission déclarée est accordée au rôle Manager, qui l'exerce sur ses propres magasins ; les mises à jour n'accordent plus rien, donc un droit retiré par l'Owner reste retiré. Exigez `"kintai_contract": "^1.2"`.

### Fonctionnalités par magasin : `storeFeatures()` (contrat 1.3)

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

Tant que votre bundle est actif, chaque fonctionnalité est proposée comme interrupteur dans la carte « Fonctionnalités » du formulaire de magasin. Vérifiez-la dans vos pages employé avec `HasStoreFeatureCheck::assertFeature($request, 'timeoff')`, et donnez la même clé comme `storeFeature` à vos `NavItem` pour qu'ils disparaissent des magasins qui l'ont désactivée. Un magasin qui n'a jamais enregistré ses fonctionnalités les a toutes activées ; désactiver votre bundle conserve le choix de chaque magasin, que sa réactivation restaure. Une clé déjà déclarée par le Core ou un autre bundle est ignorée (journalisé). Exigez `"kintai_contract": "^1.3"`.

### Widgets du tableau de bord : `dashboardWidgets()` (contrat 1.4)

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

Vos widgets sont listés dans le panneau « Personnaliser » du tableau de bord (activés par défaut) et rendus après ceux du Core, en pleine largeur, jusqu'à ce que l'utilisateur les déplace ou les redimensionne (l'ordre et les largeurs sont enregistrés par utilisateur ; un widget jamais placé va en dernier), pour les utilisateurs qui détiennent `permission` et dont les magasins ont `storeFeature` activé. `data` reçoit le contexte : `user`, `store_ids` (les magasins gérés, ou ceux de l'employé), `managed_store_ids` (`null` = tous les magasins, côté gérant), `stores_map` (id => nom) et `today`. Ne chargez que ce que le widget affiche — `data` s'exécute à chaque affichage du tableau de bord. Une exception dans `data` ou dans la vue ne retire que ce widget (journalisé). Rendez une carte complète (`<div class="card card--mt">…</div>`). Les clés du Core ne peuvent pas être réutilisées. Un bundle qui reprend un widget autrefois rendu par le Core garde sa clé pour que les utilisateurs conservent leurs choix (`notebook` a gardé `team_notes`, `timeoff` `pending_timeoff`, `shift-swap` `pending_swaps` et `timeclock` `timeclocks_today` et `timeclock` lors de leur extraction). Exigez `"kintai_contract": "^1.4"`.

Un gérant peut aussi ajouter ses propres widgets `EMPLOYEE` à son tableau de bord gérant (désactivés par défaut, proposés dans « Ajouter un widget », jamais l'inverse). Le widget y est enregistré sous la clé `emp_{clé}` et son `data` reçoit le même contexte que sur le tableau de bord employé : les `store_ids` du gérant lui-même, avec `managed_store_ids` à `null`. C'est pourquoi les clés de widget sont limitées à 41 caractères.

### Variables de vue : `viewVariables()` (contrat 1.5)

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

Utilisez-le pour les quelques valeurs dont votre navigation ou votre mise en page a besoin sur chaque page — typiquement le compteur nommé dans le `badge` d'un `NavItem`. Chaque fonction s'exécute sur chaque page authentifiée : limitez-vous à une requête légère. Les noms sont en `snake_case` minuscule, de préférence préfixés par le nom de votre bundle ; les noms du Core (`auth_user`, `managed_store_ids`, `store_features`, …) et ceux déjà déclarés par un autre bundle sont refusés, et une exception ne retire que cette variable (journalisé). `messaging` partage ainsi `unread_messages_count` et `daily-report` `daily_report_staff_stores` ; le Core n'en calcule aucune lui-même. Exigez `"kintai_contract": "^1.5"`.

### Liens d'action : `actions()` (contrat 1.6)

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

Emplacements et contexte que chacun passe à `url` :

| Emplacement | Où | Contexte |
|---|---|---|
| `Action::EMPLOYEE_ROW` | Menu « ⋮ » d'une ligne de la liste du personnel | `user_id`, `store_id` (magasin principal) |
| `Action::EMPLOYEE_STORE` | Carte « Rapports » du formulaire employé, une fois par magasin | `user_id`, `store_id`, `store_name` |
| `Action::STORE_REPORT` | En-tête du rapport du personnel d'un magasin | `store_id` |
| `Action::STORE_REPORT_ROW` | Ligne du rapport du personnel d'un magasin, et en-tête de la page de statistiques de cet employé | `store_id`, `user_id`, `period` |
| `Action::SHIFTS_EMPLOYEE` | Barre d'outils du planning de l'employé (liste, calendrier, jour) | aucun |

Le Core affiche le lien dans le style de l'emplacement, seulement pour les utilisateurs qui détiennent `permission` et, si `storeFeature` est renseigné, avec cette fonctionnalité activée. Une clé déjà utilisée dans l'emplacement est ignorée ; une exception dans `url` retire ce lien (journalisé). Le Core n'ajoute aucun lien lui-même : `shift-swap` fournit ainsi « Demander un échange ». Exigez `"kintai_contract": "^1.6"`.

### Libellés des notifications et du journal d'activité (contrat 1.7)

```php
public function notificationTypes(): array
{
    return ['timeoff_approved' => '✓', 'timeoff_refused' => '✗'];   // type => icon
}
```

Déclarez les types des notifications que crée votre bundle, avec leur icône ; le libellé est la traduction `notif_{type}` des fichiers `lang/` de votre bundle. Les types du Core ne peuvent pas être redéfinis. Les notifications déjà reçues gardent leur libellé traduit quand votre bundle est désactivé, tant que la traduction existe.

Les entrées du journal d'activité que vous écrivez (`AuditLogger`) obtiennent leur libellé de la même façon : fournissez `audit_{action}` dans vos fichiers `lang/`, points et tirets remplacés par des soulignés (`timeoff.approved` → `audit_timeoff_approved`). Exigez `"kintai_contract": "^1.7"` pour `notificationTypes()` ; les conventions de traduction n'exigent rien.

### Types de notification : catégorie, canaux, « lu » automatique (contrat 1.23)

```php
public function notificationTypes(): array
{
    return [
        'timeoff_approved' => [
            'icon'           => '✓',
            'category'       => 'requests',                       // planning | requests | messages | reports | system
            'channels'       => ['push' => true, 'email' => true], // valeurs par défaut des préférences
            'reference_type' => 'timeoff',                         // objet désigné par l'id passé à notify()
        ],
        'timeoff_refused' => '✗',                                  // ancien format : toujours accepté
    ];
}
```

Le format enrichi range le type dans les préférences de notification de l'utilisateur (profil › Notifications) et fixe
les **valeurs par défaut** des colonnes push et e-mail ; le choix de l'utilisateur l'emporte toujours, et la cloche reçoit
toujours tout. L'ancien format `type => icône` reçoit la catégorie `system`, push activé, e-mail désactivé et aucun
`reference_type`. Une déclaration mal formée est ignorée et journalisée, elle n'empêche jamais le bundle de se charger.

Donnez toujours à `notify()` un **lien précis** (l'élément lui-même, pas la liste) et l'identifiant de l'objet en `referenceId` :

```php
$this->notifications->notify($userId, 'timeoff_approved', 'notif_timeoff_approved_body', ['name' => $name], $request->id, route_url('timeoff.show', ['id' => $request->id]));
```

Quand un utilisateur ouvre la page de détail de cet objet, marquez ses notifications comme lues :

```php
$this->notifications->markReadFor($currentUserId, 'timeoff', $request->id);   // un id ou une liste d'ids
```

`markReadFor()` est sur `kintai\Contract\Services\NotificationService` ; elle ne lève jamais d'exception, une panne ne peut donc
pas empêcher l'affichage de la page. Le `referenceType` est celui déclaré par le type. Exigez `"kintai_contract": "^1.23"`.

Si le bundle n'a pas de page de détail, faites pointer le lien vers la liste avec `?highlight={id}` et donnez la classe
`tr--highlight` (style du Core) à la ligne `.data-table` correspondante, puis appelez `markReadFor()` pour les lignes affichées.
### Tâches planifiées : `cronJobs()` (contrat 1.8)

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

Tant que votre bundle est actif, un planificateur externe (cron-job.org, une crontab serveur…) lance la tâche en appelant `/cron/run/{nom}` avec un jeton de cron créé par l'Owner — de préférence dans l'en-tête `Authorization: Bearer <jeton>`, car un paramètre `?token=` finit dans les journaux d'accès du serveur web ; `?token=` reste accepté pour les planificateurs qui ne savent appeler qu'une URL — cas courant en hébergement mutualisé — ou, sur un serveur avec accès SSH, une crontab locale lance `php scripts/cron.php {nom}` (sans jeton ; les paramètres d'URL supplémentaires deviennent `--clé=valeur`). Les noms du Core (`backup`, `log-purge`, `license-check`) et ceux déjà pris sont refusés (journalisé) ; une classe impossible à construire est ignorée. `daily-report` fournit ainsi `auto-validate`. Exigez `"kintai_contract": "^1.8"`.

Depuis le contrat 1.20, le libellé d'une tâche (listé par `php scripts/cron.php`) est trouvé par convention dans vos fichiers `lang/` : `cron_job_{nom}`, tirets remplacés par des soulignés (`cron_job_timeoff_reminders`) ; à défaut, le nom est affiché.

### Sections du profil : `profileSections()` (contrat 1.12)

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

Le Core rend votre vue dans le formulaire « Mes informations » du profil, avant son bouton « Enregistrer », tant que votre bundle est actif. À l'enregistrement, il écrit les `flags` déclarés (cases à cocher, `0`/`1` ; un champ absent de la requête garde sa valeur) dans la table `users` — seules les colonnes que le Core ouvre aux bundles sont acceptées (`ProfileSections::WRITABLE_USER_COLUMNS`), le reste est ignoré et journalisé ; gardez vos autres données dans vos propres tables et routes. Placez un champ caché `0` avant chaque case pour que décocher fonctionne. Une clé déjà prise est ignorée ; une exception au rendu retire cette section (journalisé). Le Core n'a aucune section à lui : `team-directory` fournit ainsi les cases de l'annuaire. Exigez `"kintai_contract": "^1.12"`.

### Événements du Core : `eventListeners()` (contrat 1.13)

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

Le Core annonce ce qui se passe de son côté pour que votre bundle puisse réagir sans que le Core le connaisse. Un écouteur par classe d'événement (`kintai\Contract\Event\…`) ; le reste est ignoré et journalisé. Un écouteur qui lève une exception est journalisé et n'arrête jamais l'action du Core ni les écouteurs des autres bundles. Événements disponibles :

| Événement | Quand | Données |
|---|---|---|
| `EmployeeCreated` | juste après la création d'un compte : formulaire « Nouvel employé », création rapide de l'import Excel, `POST /api/v1/users` | `user`, `storeId` (magasin auquel l'employé a été rattaché, `null` sinon — toujours `null` depuis l'API), `createdBy`, `source` |
| `OpenShiftClosed` (1.17) | un shift publié dans la bourse cesse de l'être (`is_open` 1 → 0) depuis le formulaire de shift ou `PUT /api/v1/shifts/{id}` | `shift` (la ligne enregistrée), `closedBy` |

`hiring-report` crée ainsi le rapport d'embauche de l'employé, et `shift-claim` retire les candidatures en attente d'un shift ouvert fermé. Exigez `"kintai_contract": "^1.13"` (`^1.17` pour `OpenShiftClosed`).

### Cartes de statistiques du tableau de bord : `dashboardStats()` (contrat 1.19)

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

Le Core affiche votre carte dans la rangée de statistiques en haut du tableau de bord employé, après ses propres cartes (shifts du jour et à venir), triées par `order`. `value` reçoit le même contexte que les widgets (`user`, `store_ids`, `stores_map`, `today`) et renvoie ce qu'il faut afficher ; `tone` est l'une des valeurs de `DashboardStat::TONES`. Avec `storeFeature`, la carte n'apparaît que si cette fonctionnalité est activée. Une clé déjà prise est ignorée ; une exception retire cette carte (journalisé). Le Core n'a aucune carte à lui pour les bundles : `timeoff` et `shift-swap` fournissent ainsi les leurs. Exigez `"kintai_contract": "^1.19"`.

### Sections du formulaire de shift : `shiftFormSections()` (contrat 1.17)

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

Le Core rend votre `view` dans le formulaire de shift (page de création, page de modification et fenêtre de la frise) et, sur la page de modification, votre `actionsView` dans la barre d'actions. Les deux sont **à l'intérieur** du formulaire de shift : un bouton qui vise votre propre route utilise `formaction`/`formmethod` (avec `formnovalidate`) — jamais un `<form>` imbriqué, que les navigateurs ignorent. À l'enregistrement, le Core écrit dans le shift les `fields` déclarés, et eux seuls, parmi les colonnes qu'il ouvre aux bundles (`ShiftFormSections::WRITABLE_SHIFT_COLUMNS` : `is_open`, `open_shift_note`) ; un champ que personne ne déclare est ignoré, donc un shift ne peut pas être ouvert sans le bundle. Avec `storeFeature`, la section n'apparaît que si cette fonctionnalité est activée. Une clé déjà prise est ignorée ; une exception au rendu retire cette section (journalisé). Le Core n'a aucune section à lui : `shift-claim` fournit ainsi les champs et boutons du shift ouvert. Exigez `"kintai_contract": "^1.17"`.

## Fournisseurs : les données que le Core lit dans votre bundle (contrat 1.9+)

Certaines pages du Core ont besoin de données que possède votre bundle. Au lieu de lire vos tables, le Core interroge un **fournisseur** que votre bundle implémente. Sans fournisseur actif, le Core affiche simplement moins de choses — jamais une erreur.

### Demandes en attente : `pendingRequestsProviders()` (contrat 1.9)

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

Le Core l'utilise pour la page « Demandes » (une section par fournisseur, un tableau construit à partir de vos colonnes), le compteur « Demandes en attente » du tableau de bord et les alertes de demandes anciennes — uniquement pour les utilisateurs qui détiennent `permission`. Styles de colonne : `TEXT`, `BADGE`, `USER` (identifiant d'utilisateur, affiché comme un nom), `STORE` (identifiant de magasin, affiché comme un nom), `TIME` (`HH:MM`). Renvoyez les demandes dans l'ordre d'affichage. Une exception ne retire que votre section (journalisé). Le Core n'a aucun fournisseur à lui. Exigez `"kintai_contract": "^1.9"`.
L'alerte « demandes anciennes » du tableau de bord gérant les lit aussi : une demande en attente depuis plus de trois jours est listée avec le `label()` de son fournisseur.

### Absences : `absenceProviders()` (contrat 1.10)

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

Renvoyez les absences de **tous** les statuts : le Core filtre lui-même dates et statuts. Il s'en sert pour signaler un shift planifié pendant une absence approuvée, pour afficher les absences sur le planning, dans le flux iCal de l'employé et dans les statistiques d'absentéisme. Les absences de tous les fournisseurs déclarés s'additionnent ; un fournisseur en échec est ignoré (journalisé). Sans aucun fournisseur (`timeoff` désactivé ou désinstallé), le Core ne voit aucune absence. Exigez `"kintai_contract": "^1.10"`.

### Présence : `attendanceProviders()` (contrat 1.11)

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

Le Core l'utilise sur le tableau de bord gérant (pointages restés ouverts, retards comparés au shift planifié). Les entrées de tous les fournisseurs déclarés s'additionnent ; un fournisseur en échec est ignoré (journalisé). Sans aucun fournisseur (`timeclock` désactivé ou désinstallé), le Core ne voit aucun pointage. Exigez `"kintai_contract": "^1.11"`.

### Ventes : `salesProviders()` (contrat 1.14)

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

Le Core le lit pour la page de rentabilité d'un magasin, et les bundles le lisent via `kintai\Contract\Services\Sales` (`dailySales($storeId, $from, $to, $statuses)` renvoie des lignes triées par date : `report_date`, `status`, `sales_total`, `labor_cost`, `customer_count`, `waste_total`) — `salary-report` pré-remplit ainsi son total des ventes. Si vos données sont saisies en cumul, convertissez-les vous-même en valeurs du jour, après le filtre sur les statuts. Les jours de tous les fournisseurs déclarés sont fusionnés ; un fournisseur en échec est ignoré (journalisé). Sans aucun fournisseur actif, il n'y a pas de ventes et le Core masque la page de rentabilité du magasin. `daily-report` est le fournisseur de ventes. Exigez `"kintai_contract": "^1.14"`.

### Échanges de shifts : `swapProviders()` (contrat 1.18)

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

Le Core compte avec lui les échanges d'un employé (colonne « Échanges » du rapport du personnel d'un magasin, page de statistiques de l'employé). Les échanges de tous les fournisseurs déclarés sont fusionnés ; un fournisseur en échec est ignoré (journalisé). Sans aucun fournisseur (`shift-swap` désactivé ou désinstallé), il n'y a aucun échange à compter. Exigez `"kintai_contract": "^1.18"`.

## Le contrat de périmètre RBAC : `managed_store_ids`

Chaque route web que votre bundle enregistre sous `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` doit déclarer une `permission:` — soit une clé précise de `PermissionCatalog`, soit `'public'` (une route volontairement sans contrôle, par ex. une page en libre-service ou agrégée ; omettre `permission:` revient au même). `PermissionMiddleware` en déduit un attribut de requête, `managed_store_ids`, que chaque contrôleur en aval doit lire plutôt que de le recalculer :

- **`null`** — sans restriction. Soit l'utilisateur est Owner, soit son rôle accorde la permission de votre route avec la portée « tous les magasins » cochée (`role_permissions.scope = 'global'`) — ce qui peut arriver même quand l'*affectation* qui le lie à ce rôle est elle-même limitée à son magasin. Les deux cas veulent dire exactement la même chose : aucun filtrage par magasin.
- **`int[]`** — limité exactement à ces identifiants de magasin.

**L'erreur qui réintroduit ce bogue à chaque fois** : écrire `$request->getAttribute('managed_store_ids') ?? []` au lieu de tester `=== null`. Cela paraît anodin parce que plusieurs méthodes de dépôt du Core traitent un filtre *vide* comme « pas de filtre » — si bien qu'une requête de liste en aval peut continuer à « marcher » par hasard — mais la lecture suivante de cette même valeur écrasée (une liste déroulante de magasins, un contrôle d'accès `in_array($storeId, $managedIds)`) voit « aucun magasin », pas « tous les magasins », et casse. Cette erreur exacte a été livrée dans `DailyReportController::indexAll()` de `kintai-bundle-daily-report` (corrigée dans `fix/global-scope-permission-store-filter`) — traitez toujours `null` comme une branche à part entière, à l'image de `is_admin`, jamais comme quelque chose qu'un repli `??` peut absorber.

Ne recalculez pas cette logique vous-même. Réutilisez le trait du contrat `kintai\Contract\Http\Concerns\HasAdminAccess` — `managedIds(Request $request): ?array`, `availableStores(?array $managedIds): array`, `assertStoreAccess(Request $request, int $storeId): void`, `assertAnyStoreAccess(Request $request, array $storeIds): void` — le trait qu'utilise le contrôleur d'administration de chaque bundle officiel (voir `StorePhotoController` dans `kintai-bundle-store-photos` pour un exemple réel). Il gère déjà correctement le cas `null` ; un équivalent écrit à la main dans votre contrôleur est exactement la manière dont cette famille de bogues revient, un bundle à la fois.

Si votre bundle doit servir un **fichier téléversé** (une image, un PDF, une pièce jointe) derrière cette même autorisation, ne construisez pas votre propre route de service de fichiers — pointez vers la route `/storage/{path*}` de Kintai (nom de route `storage.file`), qui applique déjà `managed_store_ids` (`StorageFileController::assertPathStoreAccess()`) ainsi que son propre confinement des chemins de téléversement et sa liste blanche de types MIME. Une route parallèle duplique une logique d'autorisation que le Core possède déjà, hors de la couverture de ses propres tests.

## URL lisibles : paramètres de route typés

Les routes web du Core n'affichent plus d'identifiants de base de données pour les magasins et les employés (`/admin/stores/所沢東町店/edit`, `/admin/users/057/edit`, voir `docs/architecture.md`, « Readable URLs »). Votre bundle obtient la même chose en typant ses paramètres de route — rien à enregistrer :

```php
// routes.php
$r->get('/stores/{id:store}/photos',               [StorePhotoController::class, 'index'], name: 'store_photos.index', permission: 'photos.view');
$r->get('/stores/{id:store}/reports/{uid:employee}', [ReportController::class, 'show'],    name: 'reports.show',       permission: 'reports.view');
```

- **Vos contrôleurs ne changent pas :** `$request->param('id')` renvoie toujours l'identifiant numérique. Le segment est résolu avant `PermissionMiddleware`, donc les règles `store_param` et `assertStoreAccess()` continuent de fonctionner sans changement.
- **Générez les liens avec `route_url()` en passant l'identifiant** (`route_url('store_photos.index', ['id' => $storeId])`) : il produit le segment lisible et encodé. Pour une URL construite à la main, utilisez `store_segment($storeId)` / `employee_segment($userId)` au lieu de l'identifiant brut — jamais le nom de l'employé.
- **Les anciens liens continuent de fonctionner :** un identifiant numérique est résolu et un GET est redirigé en `301` vers l'URL lisible (un POST est servi tel quel), donc les favoris, e-mails et notifications envoyés avant la migration de votre bundle ne sont pas cassés.
- **Ne typez que ce qui désigne un magasin ou un employé.** Un identifiant de rapport, de photo ou d'adhésion reste un simple `{id}`.
- **Exige un Core qui fournit les paramètres de route typés** (voir son CHANGELOG) : exigez un `kintai_contract` qu'un tel Core fournit, car un Core plus ancien lirait `{id:store}` comme un segment littéral.

## Migrations de base de données

Un bundle peut posséder ses propres tables — une PR dédiée sur le dépôt de Kintai n'est **plus** nécessaire pour créer un schéma. Placez un dossier facultatif `database/migrations/` à la racine de votre bundle (à côté de `src/`, même règle que `Views/`/`routes.php`), contenant des fichiers au **format exact** des migrations du Core (`database/migrations/php/*.php`) :

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

Nommez les fichiers `YYYY_MM_DD_NNNNNN_description.php` — l'ordre alphabétique est l'ordre d'exécution, exactement comme pour le Core. Protégez toujours `up()` avec `hasTable()`/`hasColumn()` (comme ci-dessus) : les migrations doivent être idempotentes, car une resynchronisation manuelle peut les rejouer (voir ci-dessous). Passez toujours par `$this->schema()` : il donne à vos tables le même schéma portable que celles du Core sous SQLite et MySQL (clés primaires signées, assorties aux colonnes `integer()` qui les référencent). Une valeur par défaut sur une colonne `text()` doit utiliser `$this->textDefault('…')` — MySQL y refuse un littéral simple.

**Quand elles s'exécutent** : automatiquement, juste après que les fichiers de votre bundle ont été posés sur le disque lors d'une installation ou d'une mise à jour depuis `/admin/bundles/market` (`BundleInstallerService::activate()`) — avant que le bundle ne soit marqué actif, donc une migration en échec annule toute l'installation ou la mise à jour plutôt que d'activer un bundle au schéma incomplet. On peut aussi les resynchroniser à la main, pour tous les bundles installés à la fois, avec `php scripts/db-migrate.php` (`--dry-run` montre ce qui est en attente, même option que pour le Core).

**Suivi** : les migrations de bundle appliquées sont enregistrées dans une table `bundle_migrations` (`bundle_slug` + nom de `migration`, uniques ensemble) — distincte de la table `migrations` du Core, pour que deux bundles ne puissent jamais se disputer un nom de fichier de migration, et pour que `BundleMigrationRunner::forgetBundle()` puisse n'effacer que les lignes de suivi de votre bundle à la désinstallation. Les tables métier elles-mêmes ne sont **pas** supprimées à la désinstallation (voir Limites) — seules les lignes de suivi le sont, pour qu'une réinstallation rejoue proprement vos méthodes `up()` (leurs gardes `hasTable()` les rendent sans effet si les tables sont encore là).

Votre interface de dépôt, votre modèle Eloquent et vos tables vivent dans l'espace de noms de votre bundle, comme ceux de chaque bundle officiel (voir « Classes propres au bundle » ci-dessus).

## Ressources statiques

Si votre bundle a besoin de son propre CSS ou JS, ne demandez pas son ajout dans `public/assets/` de Kintai — c'était la situation de chaque bundle avant ce mécanisme, et cela ruinait tout l'intérêt de distribuer les bundles dans des dépôts séparés (une retouche CSS voulait dire une PR sur le Core). Fournissez-le vous-même, depuis votre propre dossier `public/` (à côté de `src/`, `Views/`, `routes.php` — même règle que tout le reste de l'arborescence requise ci-dessus) :

```
your-bundle/
  public/
    css/your-bundle.css
    js/your-bundle.js
```

Enregistrez-le dans `register()`, exactement comme `loadViewsFrom()`/`loadRoutesFrom()` :

```php
public function register(): void
{
    $this->loadViewsFrom($this->getPath() . '/Views', 'your-namespace');
    $this->loadRoutesFrom($this->getPath() . '/routes.php');
    $this->loadAssetsFrom('public');
}
```

Depuis n'importe quelle vue de votre bundle, référencez le fichier via l'assistant `bundle_asset()` — jamais un chemin écrit en dur, car l'emplacement réel sur le disque dépend de la version installée :

```php
<?php if ($css = bundle_asset('your-slug', 'css/your-bundle.css')): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endif; ?>
```

`bundle_asset()` renvoie `null` (sans jamais lever d'exception) quand votre bundle n'est pas actif — protégez toujours le `<link>`/`<script>` par un `if`, comme ci-dessus, plutôt que de supposer qu'il se résout toujours. L'URL qu'il construit (`GET /bundle-assets/{slug}/{path}?v={version}`) est servie par une route dédiée, sans authentification (`BundleAssetController`) — ce sont des fichiers statiques publics, non protégés par `AuthMiddleware`/`PermissionMiddleware` comme les pages de votre bundle — confinée au dossier `public/` déclaré et limitée à une liste blanche fixe d'extensions (`css`, `js`, `svg`, `png`, `webp`). Tout ce qui sort de cette liste, ou toute tentative de remonter au-dessus de votre dossier `public/`, est refusé (`403`) ; une requête pour un bundle inactif, ou qui n'a jamais appelé `loadAssetsFrom()`, donne un simple `404`.

Si une vue a besoin du **contenu** du fichier plutôt que de son URL — le cas courant étant un export PDF qui intègre sa feuille de style via `file_get_contents()` — utilisez `bundle_asset_path()`, qui renvoie le chemin absolu du même fichier sur le disque (`null` également si le bundle est inactif) :

```php
$css = file_get_contents(bundle_asset_path('your-slug', 'css/pdf-your-bundle.css') ?? '');
```

## Le manifeste `bundle.json`

Obligatoire à la racine du dépôt du bundle :

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

| Champ | Obligatoire | Remarques |
|---|---|---|
| `slug` | Oui | Doit être égal à la valeur renvoyée par `getName()` — vérifié à l'installation, un slug différent est refusé avant toute écriture sur le disque. |
| `name` | Oui | Lisible, affiché dans le catalogue. |
| `version` | Oui | Semver strict (`X.Y.Z`), comparé avec `version_compare()`. Doit être égal à la valeur renvoyée par `getVersion()`. |
| `namespace` | Oui | Racine PSR-4 de tout ce qui est sous `src/`. |
| `entry_class` | Oui | Nom complet de la classe d'entrée ; son fichier doit exister sous `src/` une fois résolu depuis `namespace` (vérifié avant l'activation). |
| `description`, `author`, `license`, `homepage` | Non | Informatifs uniquement. |
| `kintai_contract` | Oui | Contrainte semver sur la version du **contrat des bundles** (voir [Contrat de bundle versionné](#contrat-de-bundle-versionné)), par ex. `^1.19`. C'est la seule vérification de compatibilité : un bundle qui ne la déclare pas est antérieur au contrat versionné, il est refusé à l'installation et jamais chargé au démarrage. |
| `kintai_core.min`/`.max` | Non — **informatif** | Ignoré par la vérification de compatibilité depuis que le contrat est obligatoire (des bundles publiés avant lui déclaraient une plage ouverte et cassaient quand le Core retirait des classes internes). |
| `requires_bundles` | Non (`{}` par défaut) | Slug → contrainte de version. **Informatif pour l'instant** — voir Limites. |

## Contrat de bundle versionné

Le contrat des bundles a **sa propre version**, indépendante de celle du Core : `kintai\Contract\Contract::VERSION` (actuellement `1.23.0`). Il suit semver — une version mineure ne fait qu'ajouter (une classe, une méthode facultative, un point d'extension), une version majeure retire ou modifie une signature et est annoncée dans une section « Bundle contract » dédiée du CHANGELOG.

Déclarez dans `bundle.json` la version du contrat dont votre bundle a besoin :

```json
"kintai_contract": "^1.0"
```

Contraintes prises en charge (règles de Composer) : `^1.2` (>= 1.2.0, < 2.0.0), `~1.2` / `~1.2.3`, `>=1.0 <2.0` (espace = ET), `^1.0 || ^2.0` (OU), `*`, et une version exacte.

Le Core la vérifie :

- **à l'installation** (catalogue) : un bundle incompatible est refusé avant toute écriture sur le disque ;
- **au démarrage** : un bundle installé devenu incompatible (par ex. après une mise à jour du Core qui a relevé la version majeure du contrat) **n'est pas chargé**, même activé — l'application continue de fonctionner, l'événement est journalisé (`bundle_incompatible_not_loaded`) et la page des bundles affiche la raison pour que l'Owner installe une version compatible ;
- **avant une mise à jour du Core** : `/admin/update` lit la version du contrat de la release proposée et liste les bundles installés qu'elle ne chargerait plus.

Un bundle sans `kintai_contract` est considéré comme incompatible partout, y compris un bundle déposé à la main dans `src/Bundles/{Name}/` (qui a besoin d'un `bundle.json` à côté de sa classe).

`kintai_core.min`/`max` est purement informatif (voir le tableau du manifeste ci-dessus) : `kintai_contract` est la seule vérification de compatibilité.

## Publier une release

1. Augmentez `version` dans `bundle.json` (et `getVersion()` dans votre classe d'entrée — les deux doivent concorder).
2. Étiquetez le commit `vX.Y.Z` et poussez l'étiquette.
3. Créez une Release GitHub pour cette étiquette (`gh release create vX.Y.Z --generate-notes`, ou un workflow de CI qui fait de même à la poussée d'une étiquette — voir `.github/workflows/release.yml` de `kintai-bundle-notebook`). Rien à construire : le `zipball_url` généré automatiquement par la Release est exactement ce que télécharge `BundleInstallerService`.

## Content Security Policy : pas de scripts en ligne

Kintai envoie `script-src 'self' 'nonce-…'` (`SecurityHeadersMiddleware`) : le navigateur n'exécute que les scripts servis par Kintai lui-même, ou un `<script>` en ligne portant le nonce de la requête courante. **Les attributs d'événement en ligne (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…) et les liens `javascript:` sont bloqués**, sans bruit — le bouton ne fait simplement rien, sans aucune erreur côté serveur. Une vue de bundle ne doit donc pas en utiliser.

- **`<script>` en ligne** — seulement si c'est vraiment nécessaire, avec le nonce de la requête : `<script nonce="<?= csp_nonce() ?>">…</script>`. Un bloc de données `<script type="application/json">` n'est pas exécuté et n'a pas besoin de nonce. Préférez un fichier dans `assets/js/` de votre bundle (chargé avec `bundle_asset()`), qui n'a besoin de rien.
- **Attributs d'événement** — remplacez-les par des attributs déclaratifs `data-*`, gérés par `public/assets/js/modules/csp-actions.js` (chargé par le layout de l'application) :

| Au lieu de | Écrivez |
|---|---|
| `onclick="doThing()"` | `data-on-click="doThing"` |
| `onclick="doThing('a', 2)"` | `data-on-click="doThing" data-args='["a", 2]'` |
| `onchange="doThing(this.value)"` | `data-on-change="doThing" data-args='["@value"]'` (`"@this"`, `"@value"`, `"@checked"` sont remplacés à l'appel) |
| `onchange="this.form.submit()"` | `data-submit-on-change` |
| `onchange="document.getElementById('f').submit()"` | `data-submit-form="f"` |
| `onclick="location.href='/x'"` | `data-goto="/x"` (http/https uniquement) |
| `onclick="event.stopPropagation()"` | `data-stop-propagation` |
| `onclick="window.print()"` | `data-on-click="@print"` (aussi `@close`, `@select`, `@removeParent`, `@copy`) |
| `onsubmit="return confirm('…')"`, `onclick="return confirm('…')"` | `data-confirm="…"` sur le `<form>` ou sur le bouton d'envoi (fenêtre de confirmation globale) |
| `Button::attrs(['onclick' => 'f()'])` | `Button::attrs(['data-on-click' => 'f'])` |

`data-on-*` n'appelle qu'une **fonction que vous avez définie vous-même** sur `window` (une déclaration de fonction dans votre script, ou `window.f = …`) : les fonctions natives du navigateur comme `eval` ou `setTimeout` sont volontairement refusées, pour qu'un attribut injecté ne devienne pas un moyen d'exécuter du code. Les valeurs d'attributs de `Button`/`Modal` sont déjà échappées par le composant — passez la valeur brute, pas le résultat de `htmlspecialchars()`.

**Version requise.** Les actions `data-*` et `csp_nonce()` existent depuis **Kintai Core 0.3.0**. Tout Core qui fournit le contrat des bundles (1.0 est arrivé avec le Core 0.3.6) les a, donc déclarer `kintai_contract` — désormais obligatoire — suffit : un Core plus ancien refuse le bundle au lieu d'installer des contrôles qui ne font rien. À l'inverse, c'est un **changement cassant pour les bundles existants** : un bundle écrit avant 0.3.0 qui utilise encore des gestionnaires en ligne continue de fonctionner sur un Core antérieur à 0.3.0, mais ses boutons, listes et confirmations cessent de fonctionner à partir de 0.3.0 tant qu'il n'est pas migré. `tests/Unit/Security/NoInlineScriptGuardTest.php` dans Kintai échoue dès qu'une vue du Core réintroduit un gestionnaire en ligne ; faites la même vérification sur vos propres vues.

### Styles dynamiques : pas de `style="…"`

La même règle s'applique aux styles depuis le contrat 1.22 : la CSP envoie `style-src 'self' 'nonce-…'` (sans `'unsafe-inline'`), si bien qu'un attribut `style="…"` est ignoré par le navigateur et qu'un bloc `<style>` ne s'applique qu'avec le nonce — sans bruit, comme pour les scripts. **Changement cassant pour les bundles écrits avant 1.22** qui utilisent encore `style="…"` : leurs couleurs, largeurs et positions sont perdues jusqu'à leur migration. La présentation fixe va dans le CSS de votre bundle. Une valeur connue seulement au rendu (couleur choisie par l'utilisateur, hauteur d'une barre, pourcentage) passe par `style_class()` (contrat 1.22), qui renvoie une classe portant ces déclarations :

```php
<div class="bar <?= style_class(['height' => $h . 'px', 'background' => $color]) ?>"></div>
<span class="avatar-chip <?= style_class(['--chip-bg' => $user['color']]) ?>"></span>
```

Les règles sont collectées pendant la requête et émises dans un seul `<style nonce="…">` par le layout. Une page rendue **sans** layout (vue d'impression, aperçu PDF) doit les émettre elle-même avec `<?= inline_styles() ?>` juste avant `</body>`, et mettre le nonce sur ses propres blocs `<style>` : `<style nonce="<?= csp_nonce() ?>">`. Les valeurs sont filtrées (lettres, chiffres, `#`, `%`, `.`, `,`, `-`, espaces et parenthèses seulement) : tout le reste est écarté, si bien qu'une donnée saisie ne peut pas sortir de la règle. Modifier `element.style.x` en JavaScript n'est pas concerné par la CSP et reste permis ; construire du HTML avec un attribut `style="…"` en JavaScript ne l'est pas.

### Migrer un bundle existant

1. Trouvez les coupables : `grep -rnE "[[:space:]]on(click|change|submit|input)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|javascript:|<script>" Views src`.
2. Remplacez chacun par l'attribut `data-*` correspondant du tableau ci-dessus. Un `confirm()` devient `data-confirm` ; un gestionnaire qui appelait `event.stopPropagation()` a besoin de `data-stop-propagation`, sinon un ancêtre délégué se déclenche aussi.
3. Vérifiez que chaque fonction appelée via `data-on-*` est définie **sur `window`** : une déclaration de fonction dans un script classique, ou `window.f = …`. Un `const f = …` ou `let f = …` de premier niveau n'est pas une propriété de `window` et est refusé.
4. Posez le nonce sur les blocs `<script>` en ligne que vous gardez : `<script nonce="<?= csp_nonce() ?>">`.
5. Déclarez `kintai_contract` et ajoutez la vérification de CI ci-dessous.

### Vérifier en CI

Le serveur ne voit aucune erreur quand le navigateur bloque un gestionnaire en ligne, donc rien d'autre ne détectera une régression. Les bundles officiels exécutent cette étape dans `.github/workflows/tests.yml` :

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

Un **registre** n'est rien de plus qu'un fichier statique `registry.json` servi en HTTPS simple (l'URL `raw.githubusercontent.com` d'un dépôt GitHub convient très bien, c'est ainsi qu'est servi le registre officiel) — Kintai ne le clone pas non plus, il le récupère simplement par un GET (`BundleRegistryClient`).

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

- `schema_version` — Kintai comprend actuellement `1` et `2` ; une instance qui ne comprend pas un schéma plus récent rejette proprement la liste (avec une entrée de journal) au lieu de la mal interpréter.
- `repository_url` doit être un simple `https://github.com/{owner}/{repo}` — l'installeur en déduit l'URL de recherche de release de l'API GitHub.
- `versions` (schéma 2) est indexé par canal de mise à jour — `release` (seulement les releases non préliminaires publiées depuis `main`), `beta` (`main` ou `beta`, sans `alpha`), `alpha` (tout) — chacun une liste de versions installables, de la plus récente à la plus ancienne. Cela reflète le canal que l'Owner choisit une fois pour tous les bundles installés sur `/admin/bundles/market` (`AppSettingsService::bundleUpdateChannel()`, indépendant du canal `/admin/update` du Core) : la liste qui correspond à ce canal est ce que le catalogue propose comme « dernière version » et ce qu'installe une simple mise à jour. Le **schéma 1** (`versions` comme tableau plat, sans clé de canal) reste accepté pour la compatibilité — Kintai propose alors cette même liste sur tous les canaux, faute de pouvoir savoir de quelle branche vient chaque release. Le registre officiel calcule automatiquement les trois listes du schéma 2 à partir des vraies Releases GitHub de chaque bundle (`target_commitish`/`prerelease`, même règle que les canaux de release de Kintai) — voir `scripts/sync-versions.js` de [`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle), qui s'exécute toutes les heures et ouvre une PR dès qu'une nouvelle release change les listes de versions d'un bundle ; vous n'éditez pas `versions` à la main.
- Le `bundle.json` de votre dépôt reste la véritable source de vérité pour la compatibilité (`kintai_contract`) et tout ce qui est lu à l'installation — `versions` ici n'est qu'une indication pour le catalogue sur ce qui est installable et sur quel canal.
- `commits` (facultatif, ajouté au schéma 2 sans changement de version — les anciennes versions de Kintai l'ignorent) associe chaque version au sha complet de 40 caractères du commit pointé par son étiquette, par ex. `"commits": {"1.0.2": "7f99e084…"}`. Quand il est présent, Kintai vérifie l'archive téléchargée par rapport à lui **avant toute extraction** (un zipball GitHub porte son commit dans le commentaire ZIP et dans le nom de son dossier racine) et refuse l'installation en cas d'écart, si bien qu'une étiquette déplacée vers un autre commit (dépôt ou compte compromis) ne peut pas faire passer un autre code derrière le registre. Le registre officiel le remplit automatiquement : la même exécution horaire de `sync-versions.js` épingle le commit de chaque nouvelle release, ne réécrit jamais une épingle existante et échoue bruyamment si une étiquette a bougé — l'épingle n'atteint `main` que par la pull request qu'un mainteneur relit. Kintai lit le commit attendu dans le registre côté serveur (jamais dans la requête) et refuse d'installer si le registre est injoignable pour le vérifier. Un registre qui ne publie pas `commits` continue de fonctionner comme avant (un avertissement est journalisé). Ce n'est pas une signature : cela repose sur la génération de l'archive par GitHub et sur HTTPS, et le registre lui-même reste l'ancre de confiance. Si vous maintenez votre propre registre, `sync-versions.js` d'`AudricSan/KintaiBundle` peut être réutilisé tel quel.

Deux façons d'être découvert :
- **Votre propre registre** — écrivez et hébergez vous-même un `registry.json` (n'importe où, accessible en HTTPS), puis n'importe qui peut ajouter son URL depuis `/admin/bundles/registries`. Aucune approbation de quiconque n'est nécessaire.
- **Le registre officiel de Kintai** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — ouvrez une pull request qui ajoute une entrée à son `registry.json`. Y figurer ne rend **pas** votre bundle « officiel » — voir ci-dessous.

## Officiel ou tiers

`config/official-bundles.php`, dans le dépôt de Kintai lui-même, est la **seule** source de vérité sur les slugs maintenus par le projet Kintai — un bundle ne peut pas se déclarer officiel lui-même, quel que soit le registre qui le liste. Tout le reste est marqué « tiers » dans le catalogue, et l'installer ou le mettre à jour exige de cocher explicitement `confirm_third_party`, vérifié côté serveur (pas seulement masqué en JavaScript) — attendez-vous à ce que vos utilisateurs voient un avertissement, et concevez votre bundle en supposant qu'ils le liront avant de continuer.

## Limites connues (à la date de rédaction)

- **Un processus, un autoloader Composer.** Il n'y a ni `composer.json` par bundle ni isolation des dépendances — votre bundle s'exécute dans le même processus PHP et la même arborescence d'espaces de noms que la racine `kintai\` de Kintai. Ne dépendez que des noms `kintai\Contract\…` listés plus haut : ce sont les seules classes du Core garanties d'une version de Kintai à l'autre.
- **`requires_bundles` n'est pas encore appliqué.** Une dépendance déclarée envers le slug ou la version d'un autre bundle est acceptée et enregistrée, mais rien ne bloque aujourd'hui l'installation si elle manque ou est trop ancienne — considérez-la comme de la documentation pour l'instant, pas comme une garantie.
- **Pas de désinstallation des données métier.** `/admin/bundles/market` sait installer, mettre à jour et désinstaller les fichiers d'un bundle, son entrée de manifeste et ses lignes de suivi de migrations — mais les tables créées par vos migrations (et leurs données) ne sont jamais supprimées ni touchées à la désinstallation. Les nettoyer, si besoin, est pour l'instant une opération manuelle en base.
- **Les scripts et gestionnaires d'événements en ligne ne fonctionnent pas.** Depuis Kintai Core 0.3.0, la Content-Security-Policy n'a pas de `'unsafe-inline'` pour les scripts : une vue de bundle avec `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, un lien `javascript:` ou un `<script>` sans nonce est bloqué sans bruit par le navigateur. Depuis le contrat 1.22, même chose pour les styles : un attribut `style="…"` ou un `<style>` sans nonce est ignoré (utiliser `style_class()`). Voir [Content Security Policy](#content-security-policy--pas-de-scripts-en-ligne).
- **GitHub uniquement.** `repository_url` doit pointer vers un dépôt GitHub — ni GitLab, ni serveur Git auto-hébergé, ni archive hors Git.

## Implémentation de référence

[`kintai-bundle-notebook`](https://github.com/AudricSan/kintai-bundle-notebook) est un exemple petit et complet de tout ce qui précède : son propre modèle, son dépôt et ses migrations, des permissions déclarées, une fonctionnalité de magasin, des widgets de tableau de bord et un type de notification, des tests qui tournent contre le vrai Core. L'ancien pilote, `kintai-bundle-feedback`, a été retiré (le Core ne le charge plus).
