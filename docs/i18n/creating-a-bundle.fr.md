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
  Views/                      # optionnel — chargé via loadViewsFrom()
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
| `kintai_core.min`/`.max` | Non (défauts `0.0.0`/`999.999.999`) | L'installateur refuse d'activer un bundle hors de ces bornes par rapport à la version Core de l'instance en cours. |
| `requires_bundles` | Non (défaut `{}`) | Slug → contrainte de version. **Informatif uniquement pour l'instant** — voir Limitations. |

## Publier une release

1. Incrémenter `version` dans `bundle.json` (et `getVersion()` dans votre classe d'entrée — ils doivent correspondre).
2. Tagger le commit `vX.Y.Z` et pousser le tag.
3. Créer une GitHub Release pour ce tag (`gh release create vX.Y.Z --generate-notes`, ou un workflow CI qui fait la même chose au push d'un tag — voir le `.github/workflows/release.yml` minimal de `kintai-bundle-feedback`). Rien à construire : le `zipball_url` auto-généré de la release est exactement ce que `BundleInstallerService` télécharge.

## Se faire lister dans un registry

Un **registry** n'est rien de plus qu'un fichier statique `registry.json` servi en simple HTTPS (l'URL `raw.githubusercontent.com` d'un dépôt GitHub fonctionne bien, et c'est comme ça que le registry officiel est servi) — Kintai ne le clone jamais non plus, il se contente d'un GET (`BundleRegistryClient`).

```json
{
    "schema_version": 1,
    "name": "Mon registry",
    "bundles": [
        {
            "slug": "votre-bundle",
            "name": "Votre Bundle",
            "description": "...",
            "repository_url": "https://github.com/vous/votre-bundle",
            "versions": ["1.0.0"]
        }
    ]
}
```

- `schema_version` doit valoir `1` — une instance qui ne comprend pas un schéma plus récent rejette proprement le listing (avec une entrée de log) plutôt que de le mal interpréter.
- `repository_url` doit être un simple `https://github.com/{owner}/{repo}` — l'installateur en dérive l'URL de l'API GitHub pour retrouver la release.
- `versions` est une indication pour l'UI du catalogue (quelles versions proposer) ; le `bundle.json` de votre dépôt reste la source de vérité réelle, lue au moment de l'installation.

Deux façons d'être découvert :
- **Votre propre registry** — écrivez et hébergez vous-même un `registry.json` (n'importe où accessible en HTTPS), puis n'importe qui peut ajouter son URL depuis `/admin/bundles/registries`. Aucune approbation nécessaire de quiconque.
- **Le registry officiel Kintai** ([`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)) — ouvrez une pull request ajoutant une entrée à son `registry.json`. Être listé là-bas ne rend **pas** votre bundle "officiel" — voir ci-dessous.

## Officiel vs. tiers

`config/official-bundles.php`, dans le dépôt Kintai lui-même, est la **seule** source de vérité sur quels slugs sont maintenus par le projet Kintai. Un bundle ne peut pas s'auto-déclarer officiel, quel que soit le registry qui le liste. Tout le reste est signalé "tiers" dans l'UI du catalogue, et installer ou mettre à jour un tel bundle exige une case `confirm_third_party` explicite, imposée côté serveur (pas seulement masquée en JavaScript) — attendez-vous à ce que vos utilisateurs voient un avertissement, et concevez votre bundle en supposant qu'ils le liront avant de continuer.

## Limitations connues (à la date de rédaction)

- **Un seul processus, un seul autoloader Composer.** Il n'y a pas de `composer.json` par bundle ni d'isolation des dépendances — votre bundle tourne dans le même processus PHP et le même arbre de namespaces que le `kintai\` de Kintai lui-même. Ne dépendez que de `BundleContract\Bundle`, des interfaces de `src/Core/Repositories/*Interface.php`, et d'autres classes Core avec lesquelles vous êtes à l'aise de vous coupler entre versions de Kintai.
- **`requires_bundles` n'est pas encore appliqué.** Déclarer une dépendance sur le slug/la version d'un autre bundle est accepté et stocké, mais rien ne bloque actuellement l'installation si elle manque ou est trop ancienne — considérez-le comme de la documentation pour l'instant, pas une garantie.
- **Pas encore de flux de désinstallation.** `/admin/bundles/market` peut installer et mettre à jour ; retirer les fichiers d'un bundle et ses lignes en base de données n'est pas encore câblé.
- **GitHub uniquement.** `repository_url` doit pointer vers un dépôt GitHub — ni GitLab, ni serveur Git auto-hébergé, ni source d'archive non-Git.

## Implémentation de référence

[`kintai-bundle-feedback`](https://github.com/AudricSan/kintai-bundle-feedback) est le bundle pilote construit pour valider tout ce mécanisme — un exemple complet, fonctionnel et minimal de tout ce qui précède, extrait de `src/Bundles/Feedback/` de Kintai sans aucun changement de comportement.
