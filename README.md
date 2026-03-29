# 🕒 Kintai — Système de Gestion de Shifts Open-Source

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![Tests](https://github.com/AudricSan/kintai/actions/workflows/tests.yml/badge.svg)](https://github.com/AudricSan/kintai/actions/workflows/tests.yml)
[![Architecture](https://img.shields.io/badge/architecture-MVC%20%2F%20Custom%20Framework-orange.svg)]()

Kintai est une plateforme de gestion de planning d'entreprise ultra-légère, conçue pour les structures multi-magasins. Contrairement aux solutions lourdes basées sur des frameworks comme Laravel ou Symfony, Kintai utilise un **moteur PHP sur mesure**, optimisé pour la rapidité et la portabilité.

---

## 🌍 Démo en ligne

**URL :** [https://kintai-lv1b.onrender.com](https://kintai-lv1b.onrender.com)

> **Note :** L'instance tourne sur le plan gratuit de Render. Un premier accès peut prendre **30 à 60 secondes** le temps que le conteneur démarre (cold start). Les données sont réinitialisées à chaque redémarrage.

## 🔑 Comptes de Démonstration (Seeders)

> Ces comptes sont créés automatiquement par les seeders lors de l'installation.

### Administrateur

| Champ            | Valeur                 |
| :--------------- | :--------------------- |
| **Email**        | `admin@kintai.local`   |
| **Mot de passe** | `Admin1234!`           |
| **Rôle**         | Super Administrateur   |

### Employés

| Nom               | Email                         | Mot de passe  |
| :---------------- | :---------------------------- | :------------ |
| Alice Martin      | `alice.martin@kintai.local`   | `Staff1234!`  |
| Bob Dupont        | `bob.dupont@kintai.local`     | `Staff1234!`  |
| Chloé Tanaka      | `chloe.tanaka@kintai.local`   | `Staff1234!`  |
| Yuki Yamamoto     | `yuki.yamamoto@kintai.local`  | `Staff1234!`  |
| David Leblanc     | `david.leblanc@kintai.local`  | `Staff1234!`  |
| Emma Sato         | `emma.sato@kintai.local`      | `Staff1234!`  |

---

## 📖 Sommaire
1.  [Fonctionnalités Clés](#-fonctionnalités-clés)
2.  [Architecture Technique](#-architecture-technique)
3.  [Installation & Déploiement](#-installation--déploiement)
4.  [Configuration](#-configuration)
5.  [API REST](#-api-rest)
6.  [Guide du Développeur](#-guide-du-développeur)
7.  [Base de Données](#-base-de-données)
8.  [Roadmap](#-roadmap)

---

## 🚀 Fonctionnalités Clés

### 👨‍💼 Administration & Management
- **Multi-Magasins :** Isolation des données par store avec configurations locales (Devise, Fuseau horaire).
- **Planification Visuelle :** Calendrier mensuel, hebdomadaire et vue Timeline.
- **Gestion des Échanges :** Validation des demandes d'échange de shifts entre employés.
- **Contrôle des Congés :** Workflow complet d'approbation/refus des demandes de temps libre.
- **Audit Log :** Traçabilité totale des modifications (qui a modifié quoi et quand).
- **Import Excel :** Module d'importation de shifts et d'employés via fichiers Excel.

### 👤 Espace Employé
- **Tableau de Bord :** Vue immédiate sur le prochain shift et les statistiques mensuelles.
- **Disponibilités :** Interface permettant aux employés d'indiquer leurs créneaux préférés.
- **Bourse d'Échanges :** Possibilité de proposer un shift à ses collègues en un clic.
- **Simulateur de Salaire :** Calcul en temps réel basé sur les taux horaires configurés.

---

## 🛠 Architecture Technique

Le projet suit une architecture **S.O.L.I.D.** et utilise les standards modernes du PHP (PSR-4, Strict Types).

### 1. Le Cœur (Core)
- **Container (DI) :** Un conteneur d'injection de dépendances gérant l'auto-résolution par réflexion.
- **Router :** Système de routage avancé supportant les groupes, les noms de routes, les paramètres regex et les middlewares.
- **Middleware Pipeline :** Chaîne de responsabilité permettant de décorer les requêtes (Auth, Admin, I18n, JSON).
- **Persistence Abstraction :** Utilisation du **Repository Pattern** pour découpler la logique métier du stockage.

### 2. Pilotes de Stockage (Drivers)
Kintai est capable de fonctionner avec trois moteurs différents sans modification de code :
- **SQLite :** Idéal pour 90% des déploiements.
- **MySQL :** Pour les gros volumes et la haute disponibilité.
- **JsonDB :** Un pilote custom stockant les données dans des fichiers `.json` (zéro dépendance DB).

---

## 📦 Installation & Déploiement

### Prérequis
- **PHP 8.3+** avec extensions : `pdo_sqlite`, `pdo_mysql`, `mbstring`, `gd`, `intl`.
- **Composer 2.x**.
- Un serveur web (Apache/Nginx) ou le serveur interne PHP.

### Installation Rapide (CLI)
```bash
# 1. Cloner et installer les dépendances
git clone https://github.com/AudricSan/kintai.git
cd kintai
composer install

# 2. Lancer l'assistant d'installation
php install.php
```

### Déploiement sous XAMPP / Apache
Pour que le routage fonctionne correctement, assurez-vous que `mod_rewrite` est activé.
1. Placez le projet dans `htdocs/Kintai`.
2. Configurez votre DocumentRoot vers `public/` ou accédez via `http://localhost/Kintai/public/`.
3. Le fichier `.htaccess` fourni dans `public/` gérera automatiquement les URLs propres.

---

## ⚙️ Configuration

Les fichiers de configuration se trouvent dans `/config` :

| Fichier          | Description                                    |
| :--------------- | :--------------------------------------------- |
| `app.php`        | Nom de l'app, mode Debug, Timezone par défaut. |
| `database.php`   | Choix du driver et identifiants de connexion.  |
| `routes.php`     | Définition de toutes les routes Web et API.    |
| `middleware.php` | Liste des middlewares globaux et nommés.       |

**Astuce :** Créez un fichier `database.local.php` pour surcharger la configuration de base sans l'envoyer sur Git.

---

## 🌐 API REST

Kintai expose une API complète pour l'intégration tierce. Toutes les routes API commencent par `/api`.

### Exemples de Points d'Accès :
- `GET /api/users` : Liste des utilisateurs.
- `GET /api/shifts?store_id=1` : Shifts d'un magasin.
- `POST /api/timeoff-requests` : Créer une demande de congé.

**Format de réponse :** JSON standard.
**Authentification :** Basée sur les sessions web ou via Header (extensible).

---

## 👨‍💻 Guide du Développeur

### Ajouter une nouvelle fonctionnalité
1. **Route :** Définissez la route dans `config/routes.php`.
   ```php
   $router->get('/nouvelle-page', [MonController::class, 'index']);
   ```
2. **Controller :** Créez votre classe dans `src/UI/Controller/Web/`.
   ```php
   public function index(Request $request): Response {
       return Response::html($this->view->render('ma-vue'));
   }
   ```
3. **Repository :** Si vous touchez à la DB, ajoutez une interface dans `src/Core/Repositories/` et liez-la dans `Application.php`.

### Exécuter les Tests
```bash
./vendor/bin/phpunit
```

---

## 🗄️ Base de Données

### Schéma Simplifié
- **users :** Comptes utilisateurs et authentification.
- **stores :** Établissements physiques.
- **shifts :** Sessions de travail planifiées.
- **shift_types :** Catégories (Matin, Soir, etc.) avec icônes et couleurs.
- **timeoff_requests :** Congés et absences.
- **audit_log :** Historique des modifications.

### Migrations
Le système de migration est intégré. Pour ajouter une table :
1. Créez un fichier `.sql` dans `database/migrations/sqlite/` et `mysql/`.
2. Créez un fichier `.json` dans `database/migrations/jsondb/`.
3. Relancez `php install.php` ou utilisez le script de migration dédié.

---

## 🗺️ Roadmap

- [ ] **Interface Mobile PWA :** Utilisation hors-ligne.
- [ ] **IA Planning :** Suggestion automatique de shifts basée sur l'historique.
- [ ] **Webhooks :** Notifications vers Slack/Discord lors d'un changement de shift.
- [ ] **Mode Sombre :** Interface UI complète en Dark Mode.

---

## 📄 Licence

Ce projet est publié sous licence **[GNU Affero General Public License v3.0](LICENSE)**.

- Vous pouvez utiliser, modifier et redistribuer le code librement, y compris à des fins commerciales.
- Toute version modifiée déployée sur un réseau doit rendre son code source disponible.
- Voir le fichier [LICENSE](LICENSE) pour le texte complet.
