# Créer une release

🌐 [English](../releasing.md) · **Français** · [日本語](releasing.ja.md)

Ce document décrit comment publier une nouvelle version de Kintai sur GitHub, de façon à ce que les instances déployées puissent la détecter et l'appliquer automatiquement depuis `/admin/update` (voir `GithubUpdateService`).

## Principe

La mise à jour automatique (`GithubUpdateService::checkLatestRelease()`) interroge `GET /repos/{GITHUB_UPDATE_REPO}/releases` (la liste complète, pas seulement la dernière) et télécharge l'archive source (`zipball_url`) générée automatiquement par GitHub pour le tag de la release sélectionnée. Il n'y a donc **rien à construire ni à uploader manuellement** : une Release GitHub taguée `vX.Y.Z` suffit.

Chaque instance suit l'un des trois **canaux de mise à jour**, choisi par l'Owner sur `/admin/update` :
- **Release** — uniquement les tags stables (`vX.Y.Z`, non marqués prerelease sur GitHub). Construit depuis `main`.
- **Beta** — les tags stables et les tags `-beta`. Construit depuis la branche `beta`.
- **Alpha** — tous les tags, y compris `-alpha`. Construit depuis la branche `alpha`.

Parmi les releases visibles pour son canal, l'instance retient la version la plus haute (comparaison semver, donc `1.0.0-beta.3` < `1.0.0`). `alpha`, `beta` et `main` sont des branches protégées (PR + CI verte obligatoires, plus de push direct) — voir `.github/workflows/release.yml`.

Conséquences :
- Seules les **Releases GitHub** comptent (pas les tags seuls, pas les commits). Tant qu'aucune Release ne correspond au canal de l'instance, `checkLatestRelease()` renvoie `null`.
- Le numéro de version doit suivre [semver](https://semver.org/) (`MAJEUR.MINEUR.CORRECTIF[-alpha|beta.N]`), sans préfixe `v` dans les fichiers de config (le préfixe `v` n'existe que sur le tag Git — `GithubUpdateService` le retire automatiquement pour comparer les versions).

## Publier une nouvelle version (flux recommandé)

Le numéro de version de base (`X.Y.Z` dans `composer.json`/`config/app.php`/`CHANGELOG.md`) reste bumpé **à la main**, exactement comme avant. Ce qui change, c'est *qui crée le tag Git et la Release GitHub* : c'est désormais automatisé par `.github/workflows/release.yml` à chaque bump poussé sur `alpha`, `beta` ou `main` — vous ne taguez plus et n'appelez plus `gh release create` vous-même.

1. Sur une branche de travail classique, bumpez la version (voir « Procédure manuelle » ci-dessous, ou lancez `scripts/release.ps1 -DryRun` pour prévisualiser les notes du changelog — ses étapes automatisées de tag/push/`gh release create` sont remplacées par la Action et échoueront simplement contre une branche protégée ; ne le lancez donc plus sans `-DryRun`).
2. Ouvrez une PR ciblant la branche du canal à publier (`alpha`, `beta` ou `main`), et fusionnez-la une fois la CI verte (exigée par la protection de branche).
3. `.github/workflows/release.yml` se déclenche sur le push résultant et :
   - lit la version de base dans `composer.json` ;
   - sur `alpha`/`beta`, tague `vX.Y.Z-{canal}` — un **tag glissant** : si ce tag/Release existe déjà (ex. un nouveau push sur la même version `X.Y.Z` toujours en cours), il est supprimé puis recréé sur le nouveau commit, plutôt que de s'accumuler en `vX.Y.Z-{canal}.1`, `.2`, `.3`... Marqué comme prerelease. **Fait échouer le build** si `vX.Y.Z` (sans suffixe) existe déjà en Release stable — publier une prerelease avec la même version de base qu'une stable déjà sortie serait sémantiquement *inférieur* à ce tag stable, donc invisible pour le canal ; bumper la version de base d'abord ;
   - sur `main`, tague `vX.Y.Z` comme Release normale (non-prerelease) — ignoré avec un message de log si ce tag exact existe déjà (c'est-à-dire si aucun bump de version n'a eu lieu depuis la dernière release stable) ;
   - extrait les notes de release depuis `CHANGELOG.md` (la section datée `## [X.Y.Z]` pour `main`, la section `## [Unreleased]` pour `alpha`/`beta`).

Pour faire progresser une version d'un canal au suivant (alpha → beta → release), fusionnez la branche correspondante vers la suivante (ex. `alpha` dans `beta`, puis `beta` dans `main`) via une PR, comme toute autre promotion de branche.

## Procédure manuelle (bump de version)

1. Sur une branche de travail, renommer `## [Unreleased]` en `## [0.1.0-beta] - 2026-07-08` dans `CHANGELOG.md` et ajouter une nouvelle section `## [Unreleased]` vide juste au-dessus.
2. Mettre à jour la version dans `composer.json` (`"version": "0.1.0-beta"`) et `config/app.php` (`env('APP_VERSION', '0.1.0-beta')`).
3. Committer, pousser la branche, et ouvrir une PR vers `alpha`, `beta` ou `main` selon le cas :
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "chore(release): v0.1.0-beta"
   git push -u origin <votre-branche>
   gh pr create --base beta
   ```
4. Une fois fusionnée, `.github/workflows/release.yml` crée automatiquement le tag et la Release GitHub — plus rien à faire à la main.

## Après la publication

- Sur chaque instance, l'owner voit « Mise à jour disponible » sur `/admin/update` (pour le canal qu'elle suit) et peut cliquer « Mettre à jour maintenant ».
- Avant d'appliquer quoi que ce soit, l'instance crée automatiquement un backup base de données + uploads et un snapshot du code dans `storage/backups/`.
- Si `composer.lock` a changé, l'instance tente `composer install` automatiquement (best-effort) ; en cas d'échec ou d'indisponibilité, un avertissement invite à le lancer manuellement en SSH.

## En cas de problème

- **Release publiée par erreur / cassée** : `gh release delete vX.Y.Z` puis `git push --delete origin vX.Y.Z` et `git tag -d vX.Y.Z` en local. Les instances qui ont déjà appliqué la mise à jour ne sont **pas** annulées automatiquement — restaurer depuis le backup DB+uploads et le snapshot de code créés dans `storage/backups/` juste avant l'update.
- **La Action échoue à publier** : vérifier l'exécution du workflow `Release` dans l'onglet Actions ; la cause la plus fréquente est une version dans `composer.json` qui ne correspond encore à aucune section `## [...]` dans `CHANGELOG.md` — le workflow fait alors volontairement échouer le build plutôt que de publier une release sans notes. Ajouter l'entrée CHANGELOG manquante puis repousser. Il reste possible de taguer et publier manuellement avec `gh release create` si besoin.
- **Le repo passe privé un jour** : définir `GITHUB_UPDATE_TOKEN` (voir `.env.example`) sur chaque instance pour que `GithubUpdateService` puisse continuer à interroger l'API ; le workflow `Release` dispose déjà de son propre accès via le `GITHUB_TOKEN` intégré, rien à changer de ce côté.
