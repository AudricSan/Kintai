# Stratégie base de données : Kintai

🌐 [English](../database.md) · **Français** · [日本語](database.ja.md)

## 📊 Vue d'ensemble
Kintai utilise **Eloquent ORM** (`illuminate/database` ^13.19) comme unique couche d'accès aux données. Supporte SQLite (par défaut, zéro configuration) et MySQL/MariaDB (échelle production) — indépendant du driver, sélectionné via `config/database.php`.

## 🛤 Eloquent ORM

### Initialisation
Amorcé par `DatabaseServiceProvider` (`src/Core/Database/DatabaseServiceProvider.php`) via `Illuminate\Database\Capsule\Manager`.

### Modèles (`src/Domain/Eloquent/`)
36 modèles Eloquent `final`, suivant tous le même schéma : `$guarded = []`, `$timestamps = false`, aucun comportement au-delà des relations/casts. Regroupés par domaine :

- **Tenancy :** `User`, `Store`, `StoreUser`
- **Planning :** `Shift`, `ShiftType`, `Availability`, `ShiftClaim`, `ShiftSwapRequest`
- **Temps & paie :** `Timeclock`, `TimeoffRequest`, `UserShiftTypeRate`, `StoreDeductionSetting`
- **Rapports journaliers & RH :** `DailyReport`, `HiringReport`, `ResignationReport`, `SalaryReport`, `Feedback`
- **Communication :** `MessageThread`, `ThreadMessage`, `ThreadParticipant`, `Notification`
- **Système & paramètres :** `ActivityEntry`, `AppSetting`, `ApiToken`, `CronToken`, `IcalToken`, `PasswordResetToken`, `ImportAlias`, `StoreFeature`, `StoreImportSetting`, `StorePhotoImage`, `StorePhotoSubmission`, `UserDashboardPref`, `UserNavPref`
- **i18n :** `Language`, `Translation` — modèles legacy, plus lus au runtime : les traductions sont servies depuis `lang/*.json` via `JsonLanguageRepository`/`JsonTranslationRepository`. Dette technique connue, suppression prévue.

La liste complète et à jour reste la source de vérité : `ls src/Domain/Eloquent/`.

### Pattern Repository
30 interfaces de repository dans `src/Core/Repositories/`, liées à leur implémentation dans `RepositoryServiceProvider`. Contrôleurs et services ne doivent **jamais** utiliser les modèles Eloquent directement — uniquement via les interfaces de repository injectées. Presque toutes les implémentations encapsulent des modèles Eloquent ; les interfaces `Language`/`Translation` font exception, liées à des repositories basés sur des fichiers JSON à la place (leurs équivalents `Database*Repository` existent encore sur disque mais sont du code mort inutilisé, antérieur à la migration JSON — nettoyage à prévoir).

## 🔄 Système de migration
Basé sur PHP, unifié — un seul fichier de migration couvre SQLite et MySQL, pas de SQL brut ni de duplication par driver.

- **Emplacement :** `database/migrations/php/` (41 migrations)
- **Exécuteur :** `php scripts/db-migrate.php` (`--dry-run` pour prévisualiser)
- **Classe de base :** `kintai\Core\Database\Migration`
- **Idempotence :** protégée par `$this->schema()->hasTable()` — peut être relancée sans risque

## 🗄 Drivers supportés
- **SQLite :** par défaut. Zéro configuration, basé fichier — adapté à un déploiement single-tenant.
- **MySQL / MariaDB :** pour une meilleure concurrence en écriture ou une infrastructure d'hébergement existante. Note : SQLite ici n'applique pas `ON DELETE CASCADE` — les lignes dépendantes sont supprimées explicitement dans le code des repositories, pour un comportement cohérent entre les deux drivers.

## 💾 Sauvegarde & portabilité
- Chaque instance peut déclencher un dump SQL complet (`BackupService`) couvrant à la fois la base de données et les fichiers uploadés.
- Chaque tenant étant une base de données unique et autonome, migrer vers l'auto-hébergement se résume au dump de la BDD + le code source de Kintai — aucune étape d'extraction nécessaire.
