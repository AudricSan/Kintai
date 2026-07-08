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
Les fonctionnalités sont séparées en **Core** (toujours actif) et **Bundles** (`src/Bundles/*/`) — les deux font partie de ce dépôt AGPL-3.0, rien n'est verrouillé derrière une licence séparée. Toute fonctionnalité au-delà du cœur toujours actif est destinée à être livrée sous forme de bundle : aujourd'hui Messaging, Daily Reports et Store Photos. Les congés, échanges de shifts, bourse aux shifts (shift claims), et les rapports RH avancés (embauche, démission, salaire) vivent aujourd'hui dans le Core plutôt qu'en bundles — ce sont des candidats à une future migration vers leurs propres bundles.
- **Feature flags :** `config/bundles.php` liste les bundles connus du code ; leur chargement effectif est décidé par `FeatureManager`, alimenté par `LicenseServiceProvider` — une question de déploiement, pas de licence. Les Owners activent/désactivent chaque bundle au niveau de l'instance depuis `/admin/bundles` (stocké dans `app_settings.enabled_bundles`), avec repli sur `enabled_features` de `config/license.php` si rien n'est configuré. Au sein d'un bundle activé, chaque store peut ensuite l'activer ou non pour lui-même via ses propres réglages (fiche magasin).
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
