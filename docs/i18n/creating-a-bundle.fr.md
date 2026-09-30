# Créer un bundle

🌐 [English](../creating-a-bundle.md) · **Français** · [日本語](creating-a-bundle.ja.md)

Ce document s'adresse à quiconque écrit un bundle Kintai destiné à être **distribué indépendamment** — son propre dépôt Git, son propre historique de versions, installable depuis `/admin/bundles/market` sans jamais toucher au code de Kintai lui-même. Il ne couvre pas l'ancien modèle, toujours pris en charge, consistant à déposer un bundle directement dans `src/Bundles/` du dépôt principal (voir `docs/architecture.md` → "Modular Bundles" pour celui-là) — ce chemin fonctionne encore pour les 11 bundles livrés avec Kintai aujourd'hui, mais un nouveau bundle, surtout tiers, devrait utiliser le modèle décrit ici.

`kintai-bundle-feedback` (le bundle "Feedback", extrait du monorepo Kintai comme pilote de tout ce mécanisme) est un exemple complet et réel — à garder ouvert dans un second onglet pendant la lecture.

## Pourquoi un dépôt séparé, et pourquoi pas de `git clone`

Kintai n'exécute jamais `git clone`/`git pull` pour récupérer un bundle — l'installateur (`BundleInstallerService`) télécharge le **zipball d'une release GitHub taguée** en simple HTTP (avec un repli curl → wrapper de flux PHP, voir `HttpFetcher`), exactement le même mécanisme que Kintai utilise déjà pour se mettre à jour lui-même (`GithubUpdateService`). C'est délibéré : beaucoup d'hébergements mutualisés (le public visé par Kintai) ne garantissent pas qu'un binaire `git` soit accessible depuis PHP, alors qu'une requête HTTPS sortante fonctionne toujours.

Conséquences qui façonnent tout ce qui suit :
- Votre bundle a besoin d'**au moins une release GitHub taguée `vX.Y.Z`** — un simple tag ne suffit pas, `zipball_url` n'existe que sur une vraie release.
- L'unique dossier racine du zipball devient la racine installée du bundle — peu importe comment GitHub le nomme (`{owner}-{repo}-{sha}`), seul son contenu compte.
- Rien à construire ni à uploader comme artefact de release : GitHub calcule `zipball_url` automatiquement à partir du commit taggé.

## Arborescence requise

```
your-bundle/
  bundle.json                 # manifeste — voir ci-dessous, obligatoire
  src/
    YourBundle.php            # classe d'entrée, étend kintai\Core\BundleContract\Bundle
    Controllers/Web/...
    Controllers/Api/...
  database/migrations/        # optionnel — voir « Migrations de base de données » ci-dessous
  Views/                      # optionnel — chargé via loadViewsFrom()
  public/{css,js}/...         # optionnel — voir « Assets » ci-dessous, chargé via loadAssetsFrom()
  lang/{en,fr,ja}.json         # optionnel — clés de traduction propres au bundle
  routes.php                  # optionnel — chargé via loadRoutesFrom()
  README.md
  LICENSE
```

Deux pièges fréquents ici :
- **Seule votre classe d'entrée (et tout ce qui vit sous le même namespace) va sous `src/`.** `routes.php`, `Views/` et `lang/` vivent à la racine du bundle, en frères de `src/` — pas à l'intérieur. Cela reflète le champ `namespace` de `bundle.json`, qui est une racine PSR-4 pour `src/` uniquement.
- `Bundle::getPath()` renvoie la **racine** du bundle (le dossier contenant `bundle.json`), quel que soit celui des deux dossiers où vit le fichier de votre classe — `loadRoutesFrom($this->getPath() . '/routes.php')` et `loadViewsFrom($this->getPath() . '/Views', 'votre-namespace')` se résolvent correctement tant que vous suivez l'arborescence ci-dessus.

## Le contrat stable : `kintai\Core\BundleContract\Bundle`

Votre classe d'entrée étend cette classe abstraite — la seule partie de `kintai\Core\*` qui suit son propre versionnage sémantique, annoncé au CHANGELOG quand elle change (ajouter une méthode optionnelle est mineur ; changer ou supprimer une méthode est un changement majeur documenté). Rien d'autre sous `kintai\Core\` ne vient avec cette garantie.

```php
abstract class Bundle
{
    abstract public function getName(): string;      // slug — doit correspondre au "slug" de bundle.json
    abstract public function register(): void;        // câble services, routes, vues

    public function getVersion(): string;              // doit correspondre au "version" de bundle.json
    public function getLabel(): string;                 // affiché dans /admin/bundles(/market)
    public function getDescription(): string;
    public function boot(): void;                       // s'exécute une fois tous les bundles enregistrés
    public function getPath(): string;                  // racine du bundle, voir ci-dessus

    protected function loadRoutesFrom(string $path): void;
    protected function loadViewsFrom(string $path, string $namespace): void;
    protected function loadAssetsFrom(string $relativeDir): void;  // voir « Assets » ci-dessous
}
```

Un exemple minimal et réel (la vraie classe d'entrée de `kintai-bundle-feedback`, réduite) :

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

Votre namespace peut être n'importe quoi — par convention, les bundles installés utilisent `kintai\Bundles\Installed\{Nom}\`, délibérément distinct de la convention legacy du monorepo (`kintai\Bundles\{Nom}\`) pour que les deux mécanismes d'autoload (le PSR-4 propre de Composer pour `src/Bundles/`, et un `spl_autoload_register` dédié pour tout ce qui vit sous `storage/bundles/`) ne puissent jamais entrer en collision sur le même nom de classe.

**Une paire repository/interface de `src/Core/Repositories/*Interface.php` fait aussi partie de la surface stable** dont vous pouvez dépendre (ex. `FeedbackRepositoryInterface` ci-dessus) — celles-ci sont déjà un contrat stable par construction, indépendamment de `BundleContract`.

## Le contrat de portée RBAC : `managed_store_ids`

Toute route Web enregistrée par votre bundle sous `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` doit déclarer un `permission:` — soit une clé précise de `PermissionCatalog`, soit `'public'` (une route volontairement non gatée finement, ex. self-service/agrégat ; omettre `permission:` a le même effet). `PermissionMiddleware` résout cette règle en un attribut de requête, `managed_store_ids`, que chaque contrôleur en aval doit lire plutôt que de le recalculer lui-même :

- **`null`** — illimité. Soit l'utilisateur est Owner, soit son rôle accorde la clé de permission de votre route avec la case "Toutes les boutiques" cochée (`role_permissions.scope = 'global'`) — ce qui peut arriver même quand l'*affectation* qui relie cet utilisateur à ce rôle reste elle-même limitée à son magasin d'origine. Les deux cas signifient exactement la même chose : aucun filtrage par magasin.
- **`int[]`** — restreint exactement à ces identifiants de magasin.

**L'erreur qui réintroduit ce bug à chaque fois** : écrire `$request->getAttribute('managed_store_ids') ?? []` au lieu de tester explicitement `=== null`. Ça paraît anodin car plusieurs méthodes de repository du Core traitent un tableau de filtre *vide* comme "aucun filtre" — une requête de liste en aval peut donc continuer à "fonctionner" par coïncidence — mais le prochain code qui relit cette même valeur écrasée (un sélecteur de magasin, une vérification d'accès `in_array($storeId, $managedIds)`) voit "zéro magasin", pas "tous les magasins", et casse. Cette erreur exacte s'est retrouvée dans `DailyReportController::indexAll()` de `kintai-bundle-daily-report` (corrigée dans `fix/global-scope-permission-store-filter`) — traitez toujours `null` comme sa propre branche, à l'image de `is_admin`, jamais comme quelque chose qu'un `??` peut absorber.

Ne recalculez pas cette résolution vous-même. Réutilisez le trait `kintai\UI\Controller\Web\HasAdminAccess` du Core — `managedIds(Request $request): ?array`, `availableStores(?array $managedIds): array`, `assertStoreAccess(Request $request, int $storeId): void`, `assertAnyStoreAccess(Request $request, array $storeIds): void` — le même trait qu'utilise le contrôleur admin de chaque bundle officiel (voir `StorePhotoController` dans `kintai-bundle-store-photos` pour un exemple réel). Il traite déjà correctement le cas `null` ; un équivalent réécrit à la main dans votre propre contrôleur est exactement la façon dont cette catégorie de bug se réintroduit, un bundle à la fois.

Si votre bundle doit servir un **fichier uploadé** (une image, un PDF, une pièce jointe) derrière cette même autorisation, ne construisez pas votre propre route de service de fichier — appuyez-vous sur `/storage/{path*}` (nom de route `storage.file`) de Kintai lui-même, qui applique déjà `managed_store_ids` (`StorageFileController::assertPathStoreAccess()`) ainsi que son propre confinement de chemin d'upload et sa liste blanche de types MIME. Une route de service de fichier parallèle duplique une logique d'autorisation que le Core possède déjà, hors de sa propre couverture de tests.

## Migrations de base de données

Un bundle peut posséder ses propres tables — une PR dédiée sur le dépôt de Kintai n'est **plus** nécessaire pour créer un schéma. Déposez un dossier optionnel `database/migrations/` à la racine de votre bundle (au même niveau que `src/`, même règle que `Views/`/`routes.php`), contenant des fichiers au **format strictement identique** à celui des migrations du Core de Kintai (`database/migrations/php/*.php`) :

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

Nommez les fichiers `YYYY_MM_DD_NNNNNN_description.php` — l'ordre alphabétique est l'ordre d'exécution, exactement comme pour les migrations du Core. Protégez toujours `up()` avec `hasTable()`/`hasColumn()` (comme ci-dessus) : les migrations doivent être idempotentes, car elles peuvent être rejouées par une resynchronisation manuelle (voir plus bas).

**Quand elles s'exécutent** : automatiquement, juste après que les fichiers de votre bundle ont été déposés sur le disque lors d'une installation ou d'une mise à jour depuis `/admin/bundles/market` (`BundleInstallerService::activate()`) — avant que le bundle soit marqué actif, de sorte qu'une migration en échec annule toute l'installation/mise à jour au lieu d'activer un bundle au schéma incomplet. Elles peuvent aussi être resynchronisées manuellement, pour tous les bundles installés d'un coup, avec `php scripts/db-migrate.php` (`--dry-run` prévisualise ce qui est en attente, même option que pour les migrations du Core).

**Suivi** : les migrations de bundle appliquées sont enregistrées dans une table `bundle_migrations` (`bundle_slug` + nom de la migration, uniques ensemble) — distincte de la table `migrations` propre au Core, si bien que deux bundles différents ne peuvent jamais entrer en collision sur un nom de fichier de migration, et que `BundleMigrationRunner::forgetBundle()` peut effacer uniquement les lignes de suivi de votre bundle à la désinstallation. Les tables métier elles-mêmes ne sont **pas** supprimées à la désinstallation (voir Limitations) — seules les lignes de suivi le sont, de sorte qu'une réinstallation ultérieure rejoue proprement vos méthodes `up()` (leurs gardes `hasTable()` en font une opération sans effet si les tables sont toujours là).

Ce mécanisme est volontairement indépendant du Core : votre interface de repository et votre modèle Eloquent peuvent vivre dans le namespace de votre propre bundle, ou dans `src/Core/Repositories/*Interface.php` si vous préférez suivre la convention que tous les bundles officiels utilisent actuellement (voir « Le contrat stable » ci-dessus) — la migration elle-même n'en a que faire.

## Assets

**Nécessite `kintai_core.min: "0.2.0"` ou supérieur.** Si votre bundle a besoin de son propre CSS ou JS, ne demandez pas qu'il soit ajouté au `public/assets/` de Kintai — c'était la situation de tous les bundles avant que ce mécanisme existe, et cela ruinait tout l'intérêt de distribuer les bundles comme dépôts séparés (un ajustement de CSS impliquait une PR sur le Core). Livrez-le vous-même, depuis votre propre dossier `public/` (au même niveau que `src/`, `Views/`, `routes.php` — même règle que tout le reste de l'arborescence requise ci-dessus) :

```
your-bundle/
  public/
    css/your-bundle.css
    js/your-bundle.js
```

Déclarez-le dans `register()`, exactement comme `loadViewsFrom()`/`loadRoutesFrom()` :

```php
public function register(): void
{
    $this->loadViewsFrom($this->getPath() . '/Views', 'your-namespace');
    $this->loadRoutesFrom($this->getPath() . '/routes.php');
    $this->loadAssetsFrom('public');
}
```

Depuis n'importe laquelle des vues de votre bundle, référencez le fichier via le helper `bundle_asset()` — jamais un chemin codé en dur, car l'emplacement réel sur le disque dépend de la version actuellement installée :

```php
<?php if ($css = bundle_asset('your-slug', 'css/your-bundle.css')): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endif; ?>
```

`bundle_asset()` renvoie `null` (sans jamais lever d'exception) quand votre bundle n'est pas actif — protégez toujours le `<link>`/`<script>` par un `if`, comme ci-dessus, plutôt que de supposer qu'il se résout toujours. L'URL qu'il construit (`GET /bundle-assets/{slug}/{path}?v={version}`) est servie par une route dédiée non authentifiée (`BundleAssetController`) — ce sont des fichiers statiques publics, pas protégés par `AuthMiddleware`/`PermissionMiddleware` comme les pages de votre bundle — confinée à votre dossier `public/` déclaré et limitée à une liste blanche fixe d'extensions (`css`, `js`, `svg`, `png`, `webp`). Tout ce qui sort de cette liste blanche, ou toute tentative de remonter au-dessus de votre dossier `public/`, est rejeté (`403`) ; une requête vers un bundle inactif, ou qui n'a jamais appelé `loadAssetsFrom()`, renvoie un simple `404`.

Si une vue a besoin du **contenu** du fichier plutôt que d'une URL — le cas courant étant un export PDF qui intègre sa feuille de style via `file_get_contents()` — utilisez plutôt `bundle_asset_path()`, qui se résout vers le chemin absolu du même fichier sur le système de fichiers (là encore `null` si inactif) :

```php
$css = file_get_contents(bundle_asset_path('your-slug', 'css/pdf-your-bundle.css') ?? '');
```

## Le manifeste `bundle.json`

Obligatoire à la racine du dépôt du bundle :

```json
{
    "slug": "feedback",
    "name": "Retours utilisateurs",
    "version": "1.0.0",
    "description": "Employee feedback (bugs, suggestions) — submission modal, admin list and deletion.",
    "author": { "name": "Votre nom", "email": "vous@exemple.com", "url": "https://exemple.com" },
    "kintai_core": { "min": "0.1.0", "max": "999.999.999" },
    "requires_bundles": {},
    "namespace": "kintai\\Bundles\\Installed\\Feedback",
    "entry_class": "kintai\\Bundles\\Installed\\Feedback\\FeedbackBundle",
    "license": "AGPL-3.0-only",
    "homepage": "https://github.com/vous/votre-bundle"
}
```

| Champ | Obligatoire | Notes |
|---|---|---|
| `slug` | Oui | Doit être identique à la valeur renvoyée par `getName()` — vérifié à l'installation, un slug incohérent est rejeté avant toute écriture sur le disque. |
| `name` | Oui | Lisible par un humain, affiché dans le catalogue. |
| `version` | Oui | Semver strict (`X.Y.Z`), comparé via `version_compare()`. Doit être identique à la valeur renvoyée par `getVersion()`. |
| `namespace` | Oui | Racine PSR-4 pour tout ce qui vit sous `src/`. |
| `entry_class` | Oui | Nom pleinement qualifié de la classe d'entrée ; son fichier doit exister sous `src/` une fois résolu depuis `namespace` (vérifié avant activation). |
| `description`, `author`, `license`, `homepage` | Non | Informatif uniquement. |
| `kintai_core.min`/`.max` | Non (défauts `0.0.0`/`999.999.999`) | L'installateur refuse d'activer un bundle hors de ces bornes par rapport à la version Core de l'instance en cours. Un bundle dont les vues utilisent les actions `data-*` ou `csp_nonce()` doit fixer `min` à `0.3.0` — voir [Content Security Policy](#content-security-policy--pas-de-script-inline). |
| `requires_bundles` | Non (défaut `{}`) | Slug → contrainte de version. **Informatif uniquement pour l'instant** — voir Limitations. |

## Publier une release

1. Incrémenter `version` dans `bundle.json` (et `getVersion()` dans votre classe d'entrée — ils doivent correspondre).
2. Tagger le commit `vX.Y.Z` et pousser le tag.
3. Créer une GitHub Release pour ce tag (`gh release create vX.Y.Z --generate-notes`, ou un workflow CI qui fait la même chose au push d'un tag — voir le `.github/workflows/release.yml` minimal de `kintai-bundle-feedback`). Rien à construire : le `zipball_url` auto-généré de la release est exactement ce que `BundleInstallerService` télécharge.

## Content Security Policy : pas de script inline

Kintai envoie `script-src 'self' 'nonce-…'` (`SecurityHeadersMiddleware`) : le navigateur n'exécute que les scripts servis par Kintai lui-même, ou un `<script>` inline portant le nonce de la requête en cours. **Les attributs d'événements inline (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…) et les liens `javascript:` sont bloqués**, en silence : le bouton ne fait simplement rien, sans aucune erreur côté serveur. Une vue de bundle ne doit donc pas en utiliser.

- **`<script>` inline** — seulement en cas de vrai besoin, avec le nonce de la requête : `<script nonce="<?= csp_nonce() ?>">…</script>`. Un bloc de données `<script type="application/json">` n'est pas exécuté et n'a pas besoin de nonce. Préférez un fichier dans `assets/js/` de votre bundle (chargé avec `bundle_asset()`), qui n'exige rien.
- **Attributs d'événements** — remplacez-les par des attributs déclaratifs `data-*`, gérés par `public/assets/js/modules/csp-actions.js` (chargé par le layout de l'application) :

| Au lieu de | Écrire |
|---|---|
| `onclick="doThing()"` | `data-on-click="doThing"` |
| `onclick="doThing('a', 2)"` | `data-on-click="doThing" data-args='["a", 2]'` |
| `onchange="doThing(this.value)"` | `data-on-change="doThing" data-args='["@value"]'` (`"@this"`, `"@value"`, `"@checked"` sont remplacés à l'appel) |
| `onchange="this.form.submit()"` | `data-submit-on-change` |
| `onchange="document.getElementById('f').submit()"` | `data-submit-form="f"` |
| `onclick="location.href='/x'"` | `data-goto="/x"` (http/https uniquement) |
| `onclick="event.stopPropagation()"` | `data-stop-propagation` |
| `onclick="window.print()"` | `data-on-click="@print"` (aussi `@close`, `@select`, `@removeParent`, `@copy`) |
| `onsubmit="return confirm('…')"`, `onclick="return confirm('…')"` | `data-confirm="…"` sur le `<form>` ou sur le bouton submit (modale de confirmation globale) |
| `Button::attrs(['onclick' => 'f()'])` | `Button::attrs(['data-on-click' => 'f'])` |

`data-on-*` n'appelle qu'une **fonction que vous avez définie vous-même** sur `window` (une déclaration de fonction dans votre script, ou `window.f = …`) : les fonctions natives du navigateur comme `eval` ou `setTimeout` sont refusées volontairement, pour qu'un attribut injecté ne devienne pas un moyen d'exécuter du code. Les valeurs d'attributs de `Button`/`Modal` sont déjà échappées par le composant : passez la valeur brute, pas le résultat de `htmlspecialchars()`.

**Version requise.** Les actions `data-*` et `csp_nonce()` existent à partir de **Kintai Core 0.3.0**. Un bundle qui les utilise doit déclarer `"kintai_core": { "min": "0.3.0", … }` dans son `bundle.json`, pour que l'installateur le refuse sur un Core plus ancien au lieu d'installer des contrôles qui ne font rien. À l'inverse, c'est une **rupture pour les bundles existants** : un bundle écrit avant la 0.3.0 qui utilise encore des handlers inline continue de fonctionner sur un Core antérieur à la 0.3.0, mais ses boutons, sélecteurs et confirmations cessent de fonctionner à partir de la 0.3.0 tant qu'il n'est pas migré. `tests/Unit/Security/NoInlineScriptGuardTest.php` dans Kintai échoue dès qu'une vue du Core réintroduit un handler inline ; faites la même vérification sur vos propres vues.

### Migrer un bundle existant

1. Repérez les occurrences : `grep -rnE "[[:space:]]on(click|change|submit|input)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|javascript:|<script>" Views src`.
2. Remplacez chacune par l'attribut `data-*` correspondant du tableau ci-dessus. Un `confirm()` devient `data-confirm` ; un handler qui appelait `event.stopPropagation()` exige `data-stop-propagation`, sinon un ancêtre délégué se déclenche aussi.
3. Vérifiez que chaque fonction appelée via `data-on-*` est définie **sur `window`** : une déclaration de fonction dans un script classique, ou `window.f = …`. Un `const f = …` ou `let f = …` de premier niveau n'est pas une propriété de `window` et est refusé.
4. Mettez le nonce sur les blocs `<script>` inline que vous gardez : `<script nonce="<?= csp_nonce() ?>">`.
5. Relevez `kintai_core.min` à `0.3.0` et ajoutez la vérification CI ci-dessous.

### Vérifier en CI

Le serveur ne voit aucune erreur quand le navigateur bloque un handler inline : rien d'autre n'attrapera une régression. Les bundles officiels exécutent cette étape dans `.github/workflows/tests.yml` :

```yaml
- name: No inline event handlers or nonce-less scripts (Kintai CSP)
  run: |
    set -euo pipefail
    if grep -rnE "[[:space:]]on(click|change|submit|input|load|error|focus|blur|dblclick|keyup|keydown)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|(href|src|action)[[:space:]]*=[[:space:]]*[\"']javascript:|<script>" Views src; then
      echo "::error::Inline handlers, javascript: URLs and <script> without nonce are blocked by Kintai's CSP"
      exit 1
    fi
```

Un **registry** n'est rien de plus qu'un fichier statique `registry.json` servi en simple HTTPS (l'URL `raw.githubusercontent.com` d'un dépôt GitHub fonctionne bien, et c'est comme ça que le registry officiel est servi) — Kintai ne le clone jamais non plus, il se contente d'un GET (`BundleRegistryClient`).

```json
{
    "schema_version": 2,
    "name": "Mon registry",
    "bundles": [
        {
            "slug": "votre-bundle",
            "name": "Votre Bundle",
            "description": "...",
            "repository_url": "https://github.com/vous/votre-bundle",
            "versions": {
                "release": ["1.0.0"],
                "beta": ["1.1.0", "1.0.0"],
                "alpha": ["1.1.0", "1.0.0"]
            }
        }
    ]
}
```

- `schema_version` — Kintai comprend actuellement `1` et `2` ; une instance qui ne comprend pas un schéma plus récent rejette proprement le listing (avec une entrée de log) plutôt que de le mal interpréter.
- `repository_url` doit être un simple `https://github.com/{owner}/{repo}` — l'installateur en dérive l'URL de l'API GitHub pour retrouver la release.
- `versions` (schema 2) est indexé par canal de mise à jour — `release` (uniquement les releases non-prerelease publiées depuis `main`), `beta` (`main` ou `beta`, exclut `alpha`), `alpha` (tout) — chacun une liste de versions installables, la plus récente en premier. Ça reflète le canal choisi une seule fois pour tous les bundles installés sur `/admin/bundles/market` (`AppSettingsService::bundleUpdateChannel()`, indépendant du canal de mise à jour du Core lui-même sur `/admin/update`) : la liste correspondant à ce canal est ce que l'UI du catalogue propose comme "dernière version" et ce qu'une simple mise à jour installe. **Le schema 1** (`versions` en liste plate, sans clé de canal) reste accepté pour compatibilité — Kintai propose alors cette même liste plate sur les trois canaux, faute de moyen de savoir de quelle branche vient chaque release. Le registry officiel calcule ces trois listes du schema 2 automatiquement à partir des vraies Releases GitHub de chaque bundle (`target_commitish`/`prerelease`, même règle que les canaux de release de Kintai lui-même) — voir `scripts/sync-versions.js` de [`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle), qui tourne toutes les heures et ouvre une PR dès qu'une nouvelle release change les listes de versions d'un bundle ; vous n'éditez jamais `versions` à la main.
- Le `bundle.json` de votre dépôt reste la source de vérité réelle pour la compatibilité (`kintai_core.min`/`max`) et tout le reste, lue au moment de l'installation — `versions` ici n'est jamais qu'une indication pour l'UI du catalogue sur ce qui est installable et sur quel canal.
- `commits` (facultatif, ajouté au schema 2 sans changer de version — les anciennes versions de Kintai l'ignorent) associe à chaque version le sha complet (40 caractères) du commit vers lequel pointe son tag, p. ex. `"commits": {"1.0.2": "7f99e084…"}`. S'il est présent, Kintai compare l'archive téléchargée à ce commit **avant toute extraction** (un zipball GitHub porte son commit dans le commentaire du ZIP et dans le nom de son dossier racine) et refuse l'installation en cas de différence : un tag déplacé vers un autre commit (dépôt ou compte compromis) ne peut donc pas faire passer un autre code à l'insu du registry. Le registry officiel le renseigne automatiquement : le même passage horaire de `sync-versions.js` épingle le commit de chaque nouvelle release, ne réécrit jamais une empreinte existante et échoue bruyamment si un tag a bougé — l'empreinte n'atteint `main` que via la pull request relue par un mainteneur. Kintai lit le commit attendu dans le registry côté serveur (jamais depuis la requête) et refuse d'installer si le registry est injoignable pour le vérifier. Un registry qui ne publie pas `commits` continue de fonctionner comme avant (un avertissement est journalisé). Ce n'est pas une signature : cela repose sur la génération de l'archive par GitHub et sur HTTPS, et le registry reste le point de confiance. Si vous tenez votre propre registry, le `sync-versions.js` de `AudricSan/KintaiBundle` est réutilisable tel quel.

Deux façons d'être découvert :
- **Votre propre registry** — écrivez et hébergez vous-même un `registry.json` (n'importe où accessible en HTTPS), puis n'importe qui peut ajouter son URL depuis `/admin/bundles/registries`. Aucune approbation nécessaire de quiconque.
- **Le registry officiel Kintai** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — ouvrez une pull request ajoutant une entrée à son `registry.json`. Être listé là-bas ne rend **pas** votre bundle "officiel" — voir ci-dessous.

## Officiel vs. tiers

`config/official-bundles.php`, dans le dépôt Kintai lui-même, est la **seule** source de vérité sur quels slugs sont maintenus par le projet Kintai. Un bundle ne peut pas s'auto-déclarer officiel, quel que soit le registry qui le liste. Tout le reste est signalé "tiers" dans l'UI du catalogue, et installer ou mettre à jour un tel bundle exige une case `confirm_third_party` explicite, imposée côté serveur (pas seulement masquée en JavaScript) — attendez-vous à ce que vos utilisateurs voient un avertissement, et concevez votre bundle en supposant qu'ils le liront avant de continuer.

## Limitations connues (à la date de rédaction)

- **Un seul processus, un seul autoloader Composer.** Il n'y a pas de `composer.json` par bundle ni d'isolation des dépendances — votre bundle tourne dans le même processus PHP et le même arbre de namespaces que le `kintai\` de Kintai lui-même. Ne dépendez que de `BundleContract\Bundle`, des interfaces de `src/Core/Repositories/*Interface.php`, et d'autres classes Core avec lesquelles vous êtes à l'aise de vous coupler entre versions de Kintai.
- **`requires_bundles` n'est pas encore appliqué.** Déclarer une dépendance sur le slug/la version d'un autre bundle est accepté et stocké, mais rien ne bloque actuellement l'installation si elle manque ou est trop ancienne — considérez-le comme de la documentation pour l'instant, pas une garantie.
- **Pas de flux de désinstallation pour les données métier.** `/admin/bundles/market` peut installer, mettre à jour et désinstaller les fichiers d'un bundle, son entrée de manifeste et ses lignes de suivi de migrations — mais les tables créées par vos migrations (et leurs données) ne sont jamais supprimées ni touchées à la désinstallation. Les nettoyer, si on le souhaite, reste pour l'instant une opération manuelle sur la base de données.
- **Les scripts et handlers d'événements inline ne fonctionnent pas.** À partir de Kintai Core 0.3.0, la Content-Security-Policy n'a plus `'unsafe-inline'` pour les scripts : une vue de bundle avec `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, un lien `javascript:` ou un `<script>` sans nonce est bloquée en silence par le navigateur. Voir [Content Security Policy](#content-security-policy--pas-de-script-inline).
- **GitHub uniquement.** `repository_url` doit pointer vers un dépôt GitHub — ni GitLab, ni serveur Git auto-hébergé, ni source d'archive non-Git.

## Implémentation de référence

[`kintai-bundle-feedback`](https://github.com/AudricSan/kintai-bundle-feedback) est le bundle pilote construit pour valider tout ce mécanisme — un exemple complet, fonctionnel et minimal de tout ce qui précède, extrait de `src/Bundles/Feedback/` de Kintai sans aucun changement de comportement.
