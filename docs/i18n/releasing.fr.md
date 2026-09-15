# Créer une release

🌐 [English](../releasing.md) · **Français** · [日本語](releasing.ja.md)

Ce document décrit comment publier une nouvelle version de Kintai sur GitHub, de façon à ce que les instances déployées puissent la détecter et l'appliquer automatiquement depuis `/admin/update` (voir `GithubUpdateService`).

## Principe

La mise à jour automatique (`GithubUpdateService::checkLatestRelease()`) interroge `GET /repos/{GITHUB_UPDATE_REPO}/releases` (la liste complète, pas seulement la dernière) et télécharge l'archive source (`zipball_url`) générée automatiquement par GitHub pour le tag de la release sélectionnée. Il n'y a donc **rien à construire ni à uploader manuellement** : une Release GitHub taguée `vX.Y.Z` suffit.

Chaque instance suit l'un des trois **canaux de mise à jour**, choisi par l'Owner sur `/admin/update` :
- **Release** — uniquement les releases publiées depuis `main`, non marquées prerelease sur GitHub.
- **Beta** — les releases publiées depuis `main` ou `beta`.
- **Alpha** — toutes les releases, quel que soit le canal.

Le canal d'une release se détermine via son `target_commitish` (la branche source, renseignée par `.github/workflows/release.yml` — voir `GithubUpdateService::selectReleaseForChannel()`), pas en inspectant le tag. Parmi les releases visibles pour son canal, l'instance retient la version la plus haute (comparaison semver). `alpha`, `beta` et `main` sont des branches protégées (PR + CI verte obligatoires, plus de push direct) — voir `.github/workflows/release.yml`.

Conséquences :
- Seules les **Releases GitHub** comptent (pas les tags seuls, pas les commits). Tant qu'aucune Release ne correspond au canal de l'instance, `checkLatestRelease()` renvoie `null`.
- Sans préfixe `v` dans les fichiers de config (le préfixe `v` n'existe que sur le tag Git — `GithubUpdateService` le retire automatiquement pour comparer les versions).

## Schéma de version : X.Y.Z en cascade

Le numéro de version est toujours un `X.Y.Z` brut — plus de suffixe de prerelease, plus de lettre de semaine :

- **X** — version majeure. Bumpée **à la main**, uniquement pour un changement cassant. Bumper `X` remet `Y` et `Z` à `0`.
- **Y** — ligne de release. Bumpée **à la main**, une seule fois, à l'ouverture du *premier* alpha d'une nouvelle ligne — typiquement juste après que la ligne précédente a été publiée sur `main`. Bumper `Y` remet `Z` à `0`.
- **Z** — compteur d'itération au sein de la ligne `X.Y` courante, partagé et cumulatif entre `alpha` et `beta` (jamais remis à zéro entre les deux) : le premier alpha de la ligne est `X.Y.1`, la publication alpha ou beta suivante (peu importe laquelle) est `X.Y.2`, etc. **`Z` est calculé automatiquement par `.github/workflows/release.yml`** à partir du plus haut tag `vX.Y.*` existant — jamais saisi à la main.
- **`Z = 0` est réservé exclusivement à la release stable publiée sur `main`.** Comme alpha/beta démarrent toujours une ligne à `Z = 1` et ne font que monter, `X.Y.0` n'entre jamais en collision avec un tag alpha/beta déjà publié pour cette même ligne.

Exemple pour la ligne `0.13` :

```
alpha  -> v0.13.1, v0.13.2
beta   -> v0.13.3, v0.13.4   (même compteur, reprend où alpha s'est arrêté)
main   -> v0.13.0            (stable, taguée une fois la ligne prête)
```

La ligne suivante démarre à `0.14.1` (`Y` bumpé à la main, `Z` revenu à son placeholder `0` dans `composer.json`/`config/app.php` jusqu'à ce que le workflow calcule le vrai premier `Z`).

Ceci remplace l'ancien schéma de suffixe `X.Y.Z-<lettre de semaine><sous-version>` (ex. `0.12.0-ak23`), qui encodait trois compteurs indépendants par canal plus une lettre de semaine ISO difficile à lire d'un coup d'œil sur `/admin/update`.

## Quel nombre bumper

Contrairement à un semver classique MAJEUR/MINEUR/CORRECTIF, `Z` n'est jamais bumpé à la main — seuls `X`/`Y` le sont, et seulement dans ces deux cas :

- **Ouvrir le premier alpha d'une nouvelle ligne** → bump **Y** dans `composer.json`/`config/app.php` (laisser `Z` à son placeholder `.0` — le vrai `Z` de chaque publication est calculé par le workflow, jamais stocké ici).
- **Changement cassant** → bump **X** à la place (ce qui remet aussi `Y` à `0`).
- **Chaque publication alpha ou beta suivante sur cette ligne** → rien à bumper à la main ; `composer.json` continue d'afficher `X.Y.0`, seule la section `[Unreleased]` de `CHANGELOG.md` grossit.
- **Publier la release stable sur `main`** → rien à bumper non plus ; `composer.json` doit déjà afficher `X.Y.0` depuis le premier alpha de la ligne. Le workflow tague exactement `vX.Y.0`, ignoré avec un message de log si ce tag existe déjà (aucun bump de version n'a eu lieu depuis la dernière release stable).

## Publier une nouvelle version (flux recommandé)

Le numéro de base (`X.Y` dans `composer.json`/`config/app.php`/`CHANGELOG.md`, toujours écrit `X.Y.0`) reste bumpé **à la main**, exactement comme avant, mais uniquement à l'ouverture d'une nouvelle ligne (voir « Quel nombre bumper » ci-dessus) — pas à chaque publication alpha/beta. Ce qui est automatisé par `.github/workflows/release.yml` à chaque push sur `alpha`, `beta` ou `main`, c'est *le calcul de `Z` et la création du tag Git + de la Release GitHub* — vous ne taguez jamais et n'appelez jamais `gh release create` vous-même.

1. Sur une branche de travail classique, bumpez la version si vous ouvrez une nouvelle ligne (voir « Procédure manuelle » ci-dessous, ou lancez `scripts/release.ps1 -DryRun` pour prévisualiser les notes du changelog — ses étapes automatisées de tag/push/`gh release create` sont remplacées par la Action et échoueront simplement contre une branche protégée ; ne le lancez donc plus sans `-DryRun`).
2. Ouvrez une PR ciblant la branche du canal à publier (`alpha`, `beta` ou `main`), et fusionnez-la une fois la CI verte (exigée par la protection de branche).
3. `.github/workflows/release.yml` se déclenche sur le push résultant et :
   - lit la ligne `X.Y` dans `composer.json` ;
   - sur `alpha`/`beta`, trouve le plus haut tag `vX.Y.*` existant, calcule `Z+1`, et tague `vX.Y.Z`, marqué comme prerelease — chaque publication produit un tag distinct et toujours croissant ;
   - sur `main`, tague `vX.Y.0` comme Release normale (non-prerelease) — ignoré avec un message de log si ce tag exact existe déjà (c'est-à-dire si la ligne a déjà été publiée en stable) ;
   - extrait les notes de release depuis `CHANGELOG.md` (la section datée `## [X.Y.0]` pour `main`, la section `## [Unreleased]` pour `alpha`/`beta`).

Pour faire progresser une version d'un canal au suivant (alpha → beta → release), fusionnez la branche correspondante vers la suivante (ex. `alpha` dans `beta`, puis `beta` dans `main`) via une PR, comme toute autre promotion de branche.

## Procédure manuelle (bump de version)

Uniquement nécessaire à l'ouverture d'une nouvelle ligne (ou d'un nouveau majeur) — voir « Quel nombre bumper » ci-dessus ; à ignorer pour toute autre publication alpha/beta.

1. Sur une branche de travail, renommer `## [Unreleased]` en `## [0.13.0] - 2026-08-04` dans `CHANGELOG.md` (la version de ligne `X.Y.0` — `Z` vaut toujours `0` ici, le vrai `Z` de chaque publication étant calculé par le workflow) et ajouter une nouvelle section `## [Unreleased]` vide juste au-dessus.
2. Mettre à jour la version dans `composer.json` (`"version": "0.13.0"`) et `config/app.php` (`env('APP_VERSION', '0.13.0')`), en bumpant `Y` (ou `X` pour un changement cassant) et en remettant le reste à `0`.
3. Committer, pousser la branche, et ouvrir une PR vers `alpha` (les nouvelles lignes démarrent toujours là) :
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "core(release): v0.13.0"
   git push -u origin <votre-branche>
   gh pr create --base alpha
   ```
4. Une fois fusionnée, `.github/workflows/release.yml` calcule le vrai `Z` (`1` pour cette première publication) et crée automatiquement le tag (`v0.13.1`) et la Release GitHub — plus rien à faire à la main. Chaque publication alpha/beta suivante sur cette ligne n'est qu'une PR normale (sans bump de version) vers `alpha` ou `beta`.

## Après la publication

- Sur chaque instance, l'owner voit « Mise à jour disponible » sur `/admin/update` (pour le canal qu'elle suit) et peut cliquer « Mettre à jour maintenant ».
- Avant d'appliquer quoi que ce soit, l'instance crée automatiquement un backup base de données + uploads et un snapshot du code dans `storage/backups/`.
- Si `composer.lock` a changé, l'instance tente `composer install` automatiquement (best-effort) ; en cas d'échec ou d'indisponibilité, un avertissement invite à le lancer manuellement en SSH.

## En cas de problème

- **Release publiée par erreur / cassée** : `gh release delete vX.Y.Z` puis `git push --delete origin vX.Y.Z` et `git tag -d vX.Y.Z` en local. Les instances qui ont déjà appliqué la mise à jour ne sont **pas** annulées automatiquement — restaurer depuis le backup DB+uploads et le snapshot de code créés dans `storage/backups/` juste avant l'update.
- **La Action échoue à publier** : vérifier l'exécution du workflow `Release` dans l'onglet Actions ; la cause la plus fréquente est une version dans `composer.json` qui ne correspond encore à aucune section `## [...]` dans `CHANGELOG.md` — le workflow fait alors volontairement échouer le build plutôt que de publier une release sans notes. Ajouter l'entrée CHANGELOG manquante puis repousser. Il reste possible de taguer et publier manuellement avec `gh release create` si besoin.
- **Le repo passe privé un jour** : définir `GITHUB_UPDATE_TOKEN` (voir `.env.example`) sur chaque instance pour que `GithubUpdateService` puisse continuer à interroger l'API ; le workflow `Release` dispose déjà de son propre accès via le `GITHUB_TOKEN` intégré, rien à changer de ce côté.
