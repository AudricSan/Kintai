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

## Schéma de version : X.Y.Z, un chiffre par canal

Le numéro de version est toujours un `X.Y.Z` simple — pas de suffixe de préversion, pas de lettre de semaine. **Chaque canal possède exactement un chiffre** et remet à zéro ceux qui sont à sa droite :

| Canal | Publier dessus… | Effet | Exemple |
|---|---|---|---|
| `alpha` | incrémente **Z** | `X.Y.Z` → `X.Y.(Z+1)` | `0.3.0` → `0.3.1` |
| `beta` | incrémente **Y**, remet `Z` à 0 | `X.Y.Z` → `X.(Y+1).0` | `0.3.4` → `0.4.0` |
| `main` | incrémente **X**, remet `Y` et `Z` à 0 | `X.Y.Z` → `(X+1).0.0` | `0.4.2` → `1.0.0` |

Donc **seul `main` peut changer le premier chiffre, seul `beta` le deuxième, seul `alpha` le troisième.** Rien n'est jamais saisi à la main : `.github/workflows/release.yml` prend le **plus haut tag `vX.Y.Z` existant** (quel que soit le canal qui l'a publié), applique la règle du canal qui publie, et crée le tag Git + la Release GitHub. Sans aucun tag, on part de `0.0.0`.

Exemple, à partir de `v0.3.0` :

```
alpha  -> v0.3.1, v0.3.2, v0.3.3
beta   -> v0.4.0                  (Y + 1, Z remis à 0)
alpha  -> v0.4.1                  (le compteur repart du plus haut tag)
main   -> v1.0.0                  (X + 1, Y et Z remis à 0)
```

Le champ `version` de `config/app.php` est un littéral simple (pas d'indirection par variable d'environnement) et c'est **ce que le Core en cours d'exécution annonce** (`UpdateService::getCurrentVersion()`), y compris pour le contrôle `kintai_core.min` de l'installateur de bundles. Personne ne le bumpe à la main : à chaque release, `.github/workflows/release.yml` exécute `scripts/set-release-version.php` avec la version calculée et l'inscrit dans `config/app.php` du commit vers lequel pointe le tag, si bien que l'archive de la release (donc une installation neuve depuis elle) annonce sa vraie version. Ce commit n'est référencé **que par le tag** — il n'est jamais poussé sur la branche de canal protégée — donc le littéral présent sur `develop`/`alpha`/`beta`/`main` reste une base en retard sur le dernier tag. Dès qu'une instance applique une mise à jour, `GithubUpdateService::applyUpdate()` réécrit ce même champ avec le tag exact qu'elle vient d'appliquer. C'est le seul fichier qui suit la version ; il n'y a pas de `storage/app/version.json` séparé. Gardez ce champ littéral : le script refuse de publier s'il ne peut pas le réécrire.

Ceci remplace le schéma précédent (`Y` bumpé à la main, `Z` cumulatif partagé, `Z = 0` réservé à `main`) puis, avant lui, le schéma à suffixe `X.Y.Z-<lettre de semaine><sous-version>` (ex. `0.12.0-ak23`).

## Publier une nouvelle version (flux recommandé)

Il n'y a aucune version à bumper : c'est la promotion d'une branche qui décide quel chiffre avance. Ce qu'automatise `.github/workflows/release.yml` à chaque push sur `alpha`, `beta` ou `main`, c'est *le calcul de la version suivante et la création du tag Git + de la Release GitHub* — vous ne taguez jamais et ne lancez jamais `gh release create` vous-même.

1. Ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]` sur votre branche de travail (comme pour toute modification).
2. Ouvrir une PR vers la branche du canal visé (`alpha`, `beta` ou `main`), et la merger une fois la CI verte (exigé par la protection de branche).
3. `.github/workflows/release.yml` s'exécute sur le push résultant et :
   - retrouve le plus haut tag `vX.Y.Z` existant ;
   - applique la règle du canal (tableau ci-dessus) et tague le résultat — marqué prerelease sur `alpha`/`beta`, Release normale sur `main` ;
   - extrait les notes de version de `CHANGELOG.md` (la section `## [Unreleased]`, ou, si elle est vide, la section `## [X.Y.Z]` de la version qui vient d'être calculée).

Pour faire avancer une version (develop → alpha → beta → release), on merge la branche correspondante vers l'avant (p. ex. `develop` dans `alpha`, puis `alpha` dans `beta`, puis `beta` dans `main`) via PR, comme n'importe quelle autre promotion. Seules les étapes `alpha`/`beta`/`main` publient réellement une release — un merge dans `develop` n'en publie jamais (voir « `develop` » plus haut). Rappel : chaque promotion fait avancer un chiffre — une publication `beta` incrémente `Y`, une publication `main` incrémente `X`.
## Après la publication

- Sur chaque instance, l'owner voit « Mise à jour disponible » sur `/admin/update` (pour le canal qu'elle suit) et peut cliquer « Mettre à jour maintenant ».
- Avant d'appliquer quoi que ce soit, l'instance crée automatiquement un backup base de données + uploads et un snapshot du code dans `storage/backups/`.
- Si `composer.lock` a changé, l'instance tente `composer install` automatiquement (best-effort) ; en cas d'échec ou d'indisponibilité, un avertissement invite à le lancer manuellement en SSH.

## En cas de problème

- **Release publiée par erreur / cassée** : `gh release delete vX.Y.Z` puis `git push --delete origin vX.Y.Z` et `git tag -d vX.Y.Z` en local. Les instances qui ont déjà appliqué la mise à jour ne sont **pas** annulées automatiquement — restaurer depuis le backup DB+uploads et le snapshot de code créés dans `storage/backups/` juste avant l'update.
- **La Action échoue à publier** : vérifier l'exécution du workflow `Release` dans l'onglet Actions ; la cause la plus fréquente est une section `## [Unreleased]` vide dans `CHANGELOG.md`, sans section `## [X.Y.Z]` pour la version calculée non plus — le workflow fait alors volontairement échouer le build plutôt que de publier une release sans notes. Ajouter l'entrée CHANGELOG manquante puis repousser. Autre cause : des notes dépassant la limite GitHub de 125 000 caractères (`HTTP 422: body is too long`, tag déjà poussé mais aucune Release) — le workflow tronque désormais les notes à 120 000 octets avec un lien vers le `CHANGELOG.md` complet, mais gardez `[Unreleased]` court : une fois une version publiée, déplacez ses entrées sous une section datée `## [X.Y.Z]` (ou une plage d'archive) au lieu de les laisser s'accumuler. Il reste possible de taguer et publier manuellement avec `gh release create` si besoin.
- **Le repo passe privé un jour** : définir `GITHUB_UPDATE_TOKEN` (voir `.env.example`) sur chaque instance pour que `GithubUpdateService` puisse continuer à interroger l'API ; le workflow `Release` dispose déjà de son propre accès via le `GITHUB_TOKEN` intégré, rien à changer de ce côté.
