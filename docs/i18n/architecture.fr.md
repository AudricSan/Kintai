# Architecture : Kintai

🌐 [English](../architecture.md) · **Français** · [日本語](architecture.ja.md)

## 🏗 Conception générale
Kintai suit une architecture MVC (Model-View-Controller) modulaire, conçue pour une portabilité et un isolement extrêmes.

### 1. Le modèle « single-tenant orchestré »
Kintai n'est pas un SaaS à infrastructure partagée.
- **Plan de données :** chaque propriétaire (tenant) dispose d'une instance dédiée de l'application et d'une base de données dédiée.
- **Plan de contrôle :** une couche d'orchestration centrale (Kintai SaaS) gère le cycle de vie de ces instances (provisioning, mises à jour, sauvegardes).

### 2. Composants principaux
- **Application cœur :** framework PHP 8.3 sur mesure suivant les principes PSR-12 et SOLID.
- **Router :** router regex sur mesure gérant les routes Web, API et Cron.
- **Container :** conteneur d'injection de dépendances (DI) léger pour la gestion des services.
- **Middleware :** un pipeline pour les préoccupations transverses (Auth, i18n, Sécurité).

## 📊 Couche de données (Eloquent ORM)
Kintai utilise **Eloquent en mode standalone** (`illuminate/database`) comme unique ORM.
- Les **repositories** encapsulent les modèles Eloquent pour préserver le découplage du domaine ; les contrôleurs n'utilisent jamais les modèles directement.
- **Drivers :** SQLite (par défaut) et MySQL.
- **Migrations :** basées sur PHP, unifiées (`database/migrations/php/`) — plus de fichiers SQL bruts.
- L'ancien `PersistenceDriverInterface` et JsonDB ont été supprimés.

## 🧩 Bundles modulaires
Les fonctionnalités sont séparées en **Core** (toujours actif) et **Bundles** (`src/Bundles/*/`) — les deux font partie de ce dépôt AGPL-3.0, rien n'est verrouillé derrière une licence séparée. Toute fonctionnalité au-delà du cœur toujours actif est destinée à être livrée sous forme de bundle : aujourd'hui Messaging, Daily Reports, Store Photos, Congés, Échanges de shifts, Bourse aux shifts (shift claims), le rapport de démission, le rapport de salaire, le rapport d'embauche, Feedback et Pointage — le Core se limite désormais à Shift, User et Store, plus l'infrastructure transversale (auth, i18n, notifications, réglages, cron, ...). Congés, Échanges de shifts et Pointage sont trois exceptions partielles : `TimeoffRequestRepositoryInterface`, `ShiftSwapRequestRepositoryInterface` et `TimeclockRepositoryInterface` restent tous des services Core (plusieurs composants Core — stats magasin, service de planification, contrôleur admin des shifts, export iCal, `HomeController`, tableau de bord employé — lisent ces données pour des calculs qui doivent continuer de fonctionner quoi qu'il arrive), donc désactiver l'un de ces trois bundles retire son UI de gestion mais pas les données sous-jacentes ni ces calculs Core. Le toggle par store `timeclock` déjà existant se filtre désormais aussi sur le bundle via `AdminStoreController::FEATURE_BUNDLE_MAP`, comme `messages`/`daily_reports`/`photos`. Bourse aux shifts est différent : aucun composant Core ne lit directement ses données, donc `ShiftClaimRepositoryInterface` a été déplacé dans le bundle lui-même plutôt que de rester un service Core — le désactiver retire entièrement la fonctionnalité, données comprises. Son extraction a aussi nécessité de scinder les six méthodes open-shifts/publish/claim hors d'`AdminShiftController` (Core) et d'`EmployeeController` (Core), qui mélangeaient auparavant la logique de bourse aux shifts avec les contrôleurs de planification toujours actifs. Embauche, démission et salaire partageaient auparavant un seul `AdminReportController` avec un dispatch interne `repo(string $type)` ; ce contrôleur a désormais entièrement disparu, remplacé par trois contrôleurs de bundle (`AdminResignationReportController`, `AdminSalaryReportController`, `AdminHiringReportController`) partageant la logique CRUD/PDF via un trait réutilisable `HasStaffReportCrud`. La méthode `calculateSalaryPreset()` de salaire lit `DailyReportRepositoryInterface` (bundle DailyReport) pour pré-remplir le chiffre d'affaires du mois — une dépendance bundle-à-bundle : la création d'un rapport de salaire suppose donc que daily-report reste actif. Contrairement à démission/salaire, `HiringReportRepositoryInterface` reste un service Core plutôt que de rejoindre son bundle : `AdminUserController` le lit/écrit directement pour générer automatiquement un rapport d'embauche à chaque création d'employé (formulaire standard et import Excel rapide), un mécanisme du cœur de la gestion des utilisateurs qui doit continuer de fonctionner même si le bundle hiring-report est désactivé — le désactiver ne retire que l'UI de consultation/édition/PDF, pas cette génération automatique ni les données. Feedback est une extraction complète (comme Store Photos) : aucun autre composant Core ne lit les données de feedback, donc `FeedbackRepositoryInterface` est enregistré par le bundle lui-même. Une subtilité : la modale de soumission de feedback est incluse directement par le layout partagé `app.php` sur chaque page employé (pas via les vues propres du bundle), donc `AuthMiddleware` partage un indicateur `feedback_enabled` (issu de `FeatureManager`) que le layout vérifie avant de l'inclure — sinon la modale continuerait de s'afficher, et son POST renverrait une 404, une fois le bundle désactivé.
- **Découverte :** `BundleDiscoveryService` scanne `src/Bundles/*/` au démarrage et détecte toute classe `{Nom}\{Nom}Bundle` qui étend `Bundle` — rien n'est codé en dur dans un registre : un bundle tiers n'a qu'à être déposé dans `src/Bundles/` (même convention PSR-4 : `kintai\Bundles\{Nom}\{Nom}Bundle`) pour être détecté, sans aucune modification du code core.
- **Feature flags :** le chargement effectif d'un bundle découvert est décidé par `FeatureManager`, alimenté par `LicenseServiceProvider` — une question de déploiement, pas de licence. Les Owners activent/désactivent chaque bundle au niveau de l'instance depuis `/admin/bundles` (stocké dans `app_settings.enabled_bundles`), avec repli sur `enabled_features` de `config/license.php` si rien n'est configuré. Au sein d'un bundle activé, chaque store peut ensuite l'activer ou non pour lui-même via ses propres réglages (fiche magasin).
- **Officiel ou tiers :** `config/official-bundles.php` liste les slugs des bundles réellement développés et maintenus par le projet Kintai. C'est la seule source de vérité pour cette distinction — un bundle ne peut pas s'auto-déclarer officiel. `/admin/bundles` signale comme tiers tout bundle détecté mais absent de cette liste, avec un avertissement indiquant qu'il n'est pas maintenu par le projet principal.
- **Système de hooks :** le Core fournit des points d'extension permettant aux bundles d'injecter des éléments UI, des routes API et de la logique.

## 🌐 Multi-tenancy
Le multi-tenancy est réalisé au **niveau du déploiement**, pas au niveau du code.
- **Isolation :** isolation physique par propriétaire.
- **Reporting inter-magasins :** géré nativement puisque tous les magasins d'un même propriétaire partagent la même instance de base de données.

## 🖥 Stratégie frontend
- **Rendu côté serveur (SSR) :** vues PHP natives pour la rapidité, la simplicité et la facilité de déploiement.
- **JS & CSS vanilla :** aucune étape de build lourde ni framework frontend, pour que l'application reste légère et facile à personnaliser.
- **Mobile first :** design responsive pensé pour les employés consultant leur planning en déplacement.

## 📡 API & Intégrations
- **API V1 :** une API RESTful permettant l'intégration avec des outils tiers.
- **iCal :** calendriers personnels pour les employés, sécurisés par token.
