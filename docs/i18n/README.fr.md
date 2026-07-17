# 🕒 Kintai

🌐 [English](../../README.md) · **Français** · [日本語](README.ja.md)

**Gestion open-source des plannings, du pointage et des ressources humaines pour les entreprises multi-magasins.**

[![Version](https://img.shields.io/badge/version-0.7.9-purple.svg)](CHANGELOG.fr.md)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![Tests](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml/badge.svg)](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml)
[![Architecture](https://img.shields.io/badge/architecture-custom%20MVC-orange.svg)]()

Kintai est le pont entre le tableur Excel et l'ERP d'entreprise : planning, pointage, congés, échanges de shifts, rapports journaliers et estimation de la paie pour les entreprises de retail et d'hôtellerie-restauration gérant plusieurs magasins — auto-hébergé, sur votre propre infrastructure, avec vos propres données.

> **Statut : bêta (`0.7.9`).** Kintai est fonctionnel et utilisé quotidiennement sur l'instance de démonstration ci-dessous, mais l'API et le schéma de données peuvent encore évoluer avant une version stable `1.0.0`. Voir [CHANGELOG.fr.md](CHANGELOG.fr.md).

---

## 🌍 Essayer en ligne

**Démo :** [kintai-lv1b.onrender.com](https://kintai-lv1b.onrender.com)
*(Instance sur plan gratuit — le premier chargement peut prendre 30 à 60 s, les données sont réinitialisées à chaque redémarrage.)*

| Rôle | Email | Mot de passe |
| :--- | :--- | :--- |
| Super admin | `admin@kintai.local` | `Admin1234!` |
| Employé | `alice.martin@kintai.local` | `Staff1234!` |

---

## Pourquoi Kintai

- **Vous restez propriétaire de vos données.** Pas de boîte noire multi-tenant : chaque déploiement est une instance dédiée avec sa propre base de données. Passer de la démo hébergée à votre propre serveur, c'est juste un dump de base et un `git clone` — aucun verrouillage propriétaire.
- **Pensé pour le multi-magasin réel.** Fuseaux horaires, devises, règles de pause et seuils de sous-effectif par magasin, fonctionnalités activables individuellement — pas un outil mono-site étiré pour l'occasion.
- **Prêt pour le Japon, utilisable partout.** Export PDF compatible CJK, ordre des noms `姓 名`, affichage JPY/EUR/USD, traductions FR/EN/JA incluses — pensé pour les usages du retail japonais mais utilisable ailleurs.
- **Aucune taxe framework.** Un cœur PHP 8.3 sur mesure d'environ 600 fichiers (pas de Laravel, pas de Symfony), avec Eloquent comme seul emprunt. Tourne aussi bien sur un VPS à 5 € que sous Docker — voir [docs/i18n/architecture.fr.md](architecture.fr.md).
- **Réellement testé.** 589 tests PHPUnit couvrant le cœur, les repositories et les contrôleurs, exécutés à chaque push via GitHub Actions.

---

## Fonctionnalités

**Planning & shifts** — CRUD shifts/types de shifts, vues liste/calendrier/semaine/jour/timeline Gantt, drag & drop, actions en masse, impression, détection des conflits de chevauchement, shifts ouverts + bourse aux shifts, import Excel avec analyse visuelle et score de confiance.

**Temps & présence** — Pointage clock-in/out avec chronomètre en direct, édition admin du pointage, estimation automatique du salaire à partir des shifts/pauses/taux horaires/déductions.

**Espace employé** — Tableau de bord personnalisé, disponibilités hebdomadaires, demandes de congés, échanges de shifts, flux iCal, widget de feedback flottant.

**Communication** — Messagerie interne par fils de discussion (mise à jour en direct via SSE), centre de notifications avec toasts et statut lu/non lu.

**Rapports journaliers** — Rapports KPI par magasin (ventes, clients, coût de personnel) avec workflow brouillon → soumis → validé, export PDF (mPDF, polices CJK), auto-validation planifiée et envoi par email.

**API REST** — Versionnée (`/api/v1`), authentification par token Bearer, pagination, couvrant toutes les ressources principales. Référence : [.wiki/API.md](https://github.com/AudricSan/Kintai/wiki/API) *(wiki en cours de portage depuis le dépôt pré-bêta)*.

**Exploitation** — Mise à jour en un clic depuis les Releases GitHub (avec barre de progression et filet de sécurité backup/rollback automatique), sauvegarde/restauration SQLite/MySQL, endpoints cron protégés par token pour les planificateurs externes.

Détail complet dans [docs/i18n/architecture.fr.md](architecture.fr.md) et [docs/i18n/database.fr.md](database.fr.md).

---

## Rôles & workflows

| Rôle | Périmètre |
| :--- | :--- |
| Super admin | Tous les magasins, journal d'audit, configuration globale |
| Manager/admin magasin | Planning, personnel, congés, échanges, rapports et stats de son/ses magasin(s) |
| Staff | Son propre planning, pointage, congés, échanges, messages, feedback |

Flux typique : shifts créés ou importés → conflits vérifiés → employés notifiés → présence pointée → rapports journaliers validés → paie estimée.

---

## Démarrage

### Prérequis

- PHP 8.3+, Composer 2.x
- `pdo_sqlite` ou `pdo_mysql`, `mbstring`, `gd`, `intl`

### Installation locale

```bash
git clone https://github.com/AudricSan/Kintai.git
cd Kintai
composer install
php -S 127.0.0.1:8000 -t public
```

Ouvrir ensuite `http://127.0.0.1:8000/install.php` — l'installateur web crée `config/database.local.php`, exécute les migrations et crée le compte administrateur.

### Docker

```bash
docker build -t kintai .
docker run -p 8080:80 kintai
```

`scripts/docker-setup.php` prépare SQLite, exécute les migrations et peut semer des données de démo (`SEED_DEMO_DATA=true`). Voir le [Dockerfile](Dockerfile) pour toutes les variables d'environnement.

### Mise à jour

```bash
php scripts/db-migrate.php --dry-run   # aperçu des migrations en attente
php scripts/db-migrate.php             # les applique
```

Les propriétaires peuvent aussi déclencher une mise à jour en un clic depuis `/admin/backup`, qui récupère la dernière Release GitHub, sauvegarde la base et le code au préalable, et affiche une progression en direct.

---

## Stack technique

PHP 8.3 MVC sur mesure — pas de Laravel, pas de Symfony — avec `illuminate/database` (Eloquent) comme seule dépendance ORM. SQLite ou MySQL, interchangeables via la config. Vues PHP server-side, CSS/JS vanilla modulaire, aucun bundler.

```
public/index.php        → front controller
src/Core/                → framework : conteneur DI, router, middlewares, repositories, services
src/Bundles/*/           → modules autonomes activables (DailyReport, Messaging)
src/UI/                  → contrôleurs Web + API, vues PHP
public/assets/           → CSS/JS modulaire, aucun build
database/migrations/php/ → migrations Eloquent, SQLite + MySQL depuis un seul schéma
```

Pour aller plus loin : [docs/i18n/architecture.fr.md](architecture.fr.md) · [docs/i18n/database.fr.md](database.fr.md) · [docs/i18n/multi-tenancy.fr.md](multi-tenancy.fr.md)

---

## Contribuer

Les contributions sont bienvenues — rapports de bugs, idées de fonctionnalités et pull requests.

```bash
composer install
./vendor/bin/phpunit
```

Lire [CONTRIBUTING.fr.md](CONTRIBUTING.fr.md) pour les conventions de code (types stricts, injection par constructeur, règles de migration) avant d'ouvrir une PR, et [SECURITY.fr.md](SECURITY.fr.md) pour signaler une vulnérabilité en privé plutôt que via une issue publique.

---

## Roadmap

**Prochainement**
- [ ] Superposition visuelle des conflits directement sur la timeline
- [ ] Audit de sécurité approfondi des routes web POST et des endpoints API exposés
- [ ] Tests ciblés sur la paie, les échanges, les shifts ouverts et les notifications

**Plus tard**
- [ ] PWA installable avec mode hors-ligne partiel
- [ ] Export paie CSV/XML
- [ ] Webhooks sortants (Slack, LINE, Teams)
- [ ] Support multilingue étendu de l'interface web (FR/EN/JA seulement pour l'instant)
- [ ] Tableau de bord d'analytique et de reporting multi-magasins
- [ ] Planning multi-magasins avec échanges de shifts inter-magasins
- [ ] Optimisation de la paie et du coût de personnel multi-magasins
- [ ] Intégration inventaire/ventes multi-magasins (caisse, ERP, e-commerce)
- [ ] Suivi de performance et gamification des employés multi-magasins
- [ ] Conformité et application du droit du travail multi-magasins (heures sup, pauses, jours fériés)
- [ ] Application mobile multi-magasins pour employés et managers (iOS, Android)

Historique complet dans [CHANGELOG.fr.md](CHANGELOG.fr.md).

---

## Licence

[GNU Affero General Public License v3.0](LICENSE). Vous êtes libre d'utiliser, modifier et redistribuer Kintai, y compris commercialement — mais toute version modifiée exploitée comme service réseau doit rendre son code source disponible. Voir [LICENSE](LICENSE) pour le texte complet.
