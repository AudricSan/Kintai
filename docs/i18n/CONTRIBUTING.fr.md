# Contribuer à Kintai

🌐 [English](../../CONTRIBUTING.md) · **Français** · [日本語](CONTRIBUTING.ja.md)

Merci de votre intérêt pour ce projet !

## Avant de commencer

Kintai est publié sous licence **GNU Affero General Public License v3.0** (AGPL-3.0).
En contribuant, vous acceptez que vos contributions soient publiées sous les mêmes conditions.

## Comment contribuer

### Signaler un bug

Ouvrez une issue avec le template **Bug Report**. Incluez :
- Version de PHP et OS
- Étapes de reproduction
- Comportement attendu vs. observé
- Logs pertinents de `storage/logs/`

### Proposer une fonctionnalité

Ouvrez une issue avec le template **Feature Request**. Expliquez le cas d'usage et en quoi elle s'inscrit dans le périmètre du projet — voir [docs/i18n/vision.fr.md](vision.fr.md) pour la direction produit.

### Soumettre une Pull Request

1. Forkez le dépôt
2. Créez une branche : `git checkout -b feat/ma-fonctionnalite` ou `fix/mon-bug`
3. Faites vos changements en suivant les conventions ci-dessous
4. Lancez les tests : `./vendor/bin/phpunit`
5. Ouvrez une pull request contre la branche `alpha` (le canal le plus actif — `main`, `alpha` et `beta` sont des branches de canal de release protégées, les fusionner y publie automatiquement une Release GitHub, voir [releasing.fr.md](releasing.fr.md))

## Conventions de code

- PHP 8.3+, types stricts (`declare(strict_types=1)`) dans chaque fichier
- Racine du namespace : `kintai\` → `src/`
- Contrôleurs : `final class`, injection par constructeur, signature `method(Request $request): Response` — les paramètres de route sont lus via `$request->param('nom')`, jamais en argument de méthode
- La persistance passe par des interfaces de repository (`src/Core/Repositories/*Interface.php`) liées dans `RepositoryServiceProvider` — contrôleurs et services ne touchent jamais directement aux modèles Eloquent
- Les erreurs HTTP utilisent la hiérarchie d'exceptions de `src/Core/Exceptions/`
- Nouvelles tables : ajoutez une seule migration Eloquent dans `database/migrations/php/` — elle doit fonctionner pour SQLite et MySQL (pas de fichiers par driver)
- SQLite ici n'applique pas `ON DELETE CASCADE` — supprimez explicitement les lignes dépendantes dans le code du repository
- Les commentaires dans le code sont écrits en français ; tout le reste (messages de commit, descriptions de PR, documentation) en anglais
- Aucune dépendance framework externe au-delà d'`illuminate/database` (Eloquent, utilisé uniquement comme ORM)
- Jamais de `style="..."` inline dans les vues — étendez un module CSS sous `public/assets/css/src/`
- Les barres de filtres s'appliquent instantanément — pas de bouton "Filtrer". Les champs texte soumettent le formulaire avec un debounce sur `input` (voir le câblage générique dans `public/assets/js/app.js` qui cible `form.filter-bar`/`form.shifts-filters`), les champs `select`/date soumettent sur `onchange`. Cela s'applique à tous les filtres, y compris les champs de recherche par nom/texte

## Lancer les tests

```bash
composer install
./vendor/bin/phpunit
```

Chaque fonctionnalité nouvelle ou modifiée doit être accompagnée de tests PHPUnit dans `tests/Unit/` (et `tests/Integration/` le cas échéant).

## Langues de la documentation

Le README, le CHANGELOG, CONTRIBUTING, SECURITY et tout ce qui se trouve sous `docs/` sont disponibles en anglais, français (`.fr.md`) et japonais (`.ja.md`). L'anglais fait foi — si vous modifiez un document anglais, mettre à jour les traductions est apprécié mais pas requis pour qu'une PR soit acceptée. `php scripts/check-translations.php` signale les traductions manquantes ou obsolètes (non bloquant, exécuté en CI à chaque push).

## Par où commencer

- [docs/i18n/architecture.fr.md](architecture.fr.md) — comment le framework est construit
- [docs/i18n/database.fr.md](database.fr.md) — modèles, repositories, migrations
- [CHANGELOG.fr.md](CHANGELOG.fr.md) — ce qui a été fait récemment
