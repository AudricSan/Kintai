# Créer une release

🌐 [English](../releasing.md) · **Français** · [日本語](releasing.ja.md)

Ce document décrit comment publier une nouvelle version de Kintai sur GitHub, de façon à ce que les instances déployées puissent la détecter et l'appliquer automatiquement depuis `/admin/update` (voir `GithubUpdateService`).

## Principe

La mise à jour automatique (`GithubUpdateService::checkLatestRelease()`) interroge `GET /repos/{GITHUB_UPDATE_REPO}/releases/latest` et télécharge l'archive source (`zipball_url`) générée automatiquement par GitHub pour le tag de la release. Il n'y a donc **rien à construire ni à uploader manuellement** : une Release GitHub taguée `vX.Y.Z` sur `main` suffit.

Conséquences :
- Seules les **Releases GitHub** comptent (pas les tags seuls, pas les commits sur `main`). Tant qu'aucune Release n'existe, `checkLatestRelease()` renvoie `null`.
- Le tag doit être créé depuis `main` — c'est cette branche que les instances en production suivent.
- Le numéro de version doit suivre [semver](https://semver.org/) (`MAJEUR.MINEUR.CORRECTIF`), sans préfixe `v` dans les fichiers de config (le préfixe `v` n'existe que sur le tag Git — `GithubUpdateService` le retire automatiquement pour comparer les versions). Pendant la phase bêta, les versions sont de la forme `0.0.x-beta`.

## Pré-requis

- [GitHub CLI](https://cli.github.com/) (`gh`) installé et authentifié (`gh auth login`).
- Être sur la branche `main`, à jour avec `origin/main`, arbre de travail propre (`git status` vide).
- La section `## [Unreleased]` de `CHANGELOG.md` doit refléter tout ce qui sera publié — c'est elle qui devient les notes de la release.

## Procédure automatisée (recommandée)

```powershell
# Aperçu sans rien modifier ni publier
.\scripts\release.ps1 -Version 0.1.0-beta -DryRun

# Publication réelle
.\scripts\release.ps1 -Version 0.1.0-beta
```

Le script (`scripts/release.ps1`) :

1. Vérifie les pré-requis (`gh` installé/authentifié, branche `main`, arbre propre, pas en retard sur `origin/main`, tag pas déjà existant).
2. Transforme `## [Unreleased]` en `## [0.1.0-beta] - AAAA-MM-JJ` dans `CHANGELOG.md` et réinsère une section `## [Unreleased]` vide au-dessus pour la suite.
3. Met à jour le numéro de version dans `composer.json` et la valeur par défaut d'`APP_VERSION` dans `config/app.php`.
4. Commit ce bump (`chore(release): v0.1.0-beta`), crée le tag annoté `v0.1.0-beta`, pousse la branche et le tag.
5. Crée la Release GitHub (`gh release create`) avec pour notes le contenu qui était sous `## [Unreleased]`.

## Procédure manuelle (si le script n'est pas utilisable)

1. Se placer sur `main`, à jour :
   ```bash
   git checkout main
   git pull
   ```
2. Dans `CHANGELOG.md`, renommer `## [Unreleased]` en `## [0.1.0-beta] - 2026-07-08` et ajouter une nouvelle section `## [Unreleased]` vide juste au-dessus.
3. Mettre à jour la version dans `composer.json` (`"version": "0.1.0-beta"`) et `config/app.php` (`env('APP_VERSION', '0.1.0-beta')`).
4. Committer et pousser :
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "chore(release): v0.1.0-beta"
   git push
   ```
5. Créer le tag et la release :
   ```bash
   git tag -a v0.1.0-beta -m "Kintai v0.1.0-beta"
   git push origin v0.1.0-beta
   gh release create v0.1.0-beta --title "Kintai v0.1.0-beta" --notes-file notes.txt
   ```
   où `notes.txt` contient le texte de la section de version qu'on vient d'ajouter au changelog.

## Après la publication

- Sur chaque instance, l'owner voit « Mise à jour disponible » sur `/admin/update` et peut cliquer « Mettre à jour maintenant ».
- Avant d'appliquer quoi que ce soit, l'instance crée automatiquement un backup base de données + uploads et un snapshot du code dans `storage/backups/`.
- Si `composer.lock` a changé, l'instance tente `composer install` automatiquement (best-effort) ; en cas d'échec ou d'indisponibilité, un avertissement invite à le lancer manuellement en SSH.

## En cas de problème

- **Release publiée par erreur / cassée** : `gh release delete vX.Y.Z` puis `git push --delete origin vX.Y.Z` et `git tag -d vX.Y.Z` en local. Les instances qui ont déjà appliqué la mise à jour ne sont **pas** annulées automatiquement — restaurer depuis le backup DB+uploads et le snapshot de code créés dans `storage/backups/` juste avant l'update.
- **`gh` non authentifié** : `gh auth login`, puis relancer.
- **Le repo passe privé un jour** : définir `GITHUB_UPDATE_TOKEN` (voir `.env.example`) sur chaque instance ; `gh release create` fonctionne déjà avec l'auth `gh` locale, rien à changer côté script.
