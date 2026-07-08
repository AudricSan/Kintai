# Stratégie multi-tenancy : Kintai

🌐 [English](multi-tenancy.md) · **Français** · [日本語](multi-tenancy.ja.md)

## 🛡 Principes
Le multi-tenancy de Kintai repose sur le principe d'**isolation physique**. Nous privilégions la sécurité et la portabilité des données à la densité d'une infrastructure partagée.

## 🧱 Définition du « Tenant »
Chez Kintai, un **Tenant** est défini comme un **Propriétaire** (une personne ou une entreprise).
- Un Tenant = une instance d'application.
- Un Tenant = une base de données.
- Un Tenant = plusieurs magasins.

## 🚀 Modèle de déploiement
Le SaaS Kintai agit comme un orchestrateur qui automatise le déploiement de ces instances single-tenant.

### 1. Serveur de base de données partagé (optionnel)
Bien que chaque tenant dispose d'une isolation logique de sa base de données, ils peuvent partager un serveur de base de données physique (par exemple un large cluster MySQL) où chaque tenant possède son propre schéma/nom de base.

### 2. Isolation physique par conteneur
Chaque tenant tourne dans son propre conteneur Docker, garantissant que l'utilisation des ressources et les vulnérabilités de sécurité restent confinées à une seule instance.

## 🔄 Gestion du cycle de vie
- **Provisioning :** mise en place automatisée de la BDD, du `.env` et du compte admin initial.
- **Mises à jour :** gérées via l'orchestrateur. Les tenants peuvent choisir différents canaux (Stable vs. Bêta).
- **Sauvegardes :** chaque base de données tenant est sauvegardée indépendamment, permettant une restauration granulaire.

## 📈 Logique inter-magasins
Puisqu'un propriétaire gère tous ses magasins au sein d'une seule base de données, les opérations multi-magasins restent simples :
- **Gestion globale des utilisateurs :** un utilisateur appartient au tenant et se voit attribuer des rôles par magasin.
- **Reporting centralisé :** comparer les coûts de personnel ou les ventes entre magasins est une simple requête SQL native au sein de la BDD du tenant.
- **Partage du personnel :** les employés peuvent facilement être planifiés sur plusieurs magasins appartenant au même tenant.

## 🚪 Liberté de sortie
Ce modèle est le socle de notre portabilité des données :
- Pour migrer vers l'auto-hébergement, le tenant a simplement besoin du dump de sa base de données et du code source de Kintai.
- Aucun script d'« extraction » n'est nécessaire pour séparer ses données de celles des autres utilisateurs.
