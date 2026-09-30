# Vue d'ensemble sécurité : Kintai

🌐 [English](../security-overview.md) · **Français** · [日本語](security-overview.ja.md)

## 🏗 Sécurité par l'architecture
- **Isolation des tenants :** le modèle « une instance par propriétaire » empêche par conception les fuites de données inter-tenants.
- **Dépendances minimales :** réduire les paquets externes minimise la surface d'attaque.
- **Séparation physique :** les bases de données sont séparées logiquement ou physiquement, réduisant l'impact d'une compromission d'une seule base.

## 🔐 Protections principales
- **Authentification :** authentification par session pour le web, tokens Bearer pour l'API.
- **Autorisation :** contrôle d'accès basé sur les rôles (RBAC) granulaire par magasin (Admin, Manager, Staff).
- **Chiffrement :** hachage sécurisé des mots de passe (Bcrypt/Argon2) et configuration sensible chiffrée.
- **CSRF & XSS :** middleware intégré pour la protection CSRF et échappement strict au niveau des vues.
- **Journaux d'audit :** chaque action sensible (changement de shift, modification de paramètres, création d'utilisateur) est journalisée avec l'ID utilisateur, l'horodatage et l'adresse IP.

## 📡 Réseau & Infrastructure
- **SSL/TLS :** HTTPS obligatoire pour toutes les instances SaaS.
- **En-têtes de sécurité :** HSTS, Content Security Policy (CSP) et X-Frame-Options activés par défaut via middleware.
- **Content Security Policy :** les scripts ne s'exécutent que s'ils sont servis par Kintai lui-même ou portent le nonce de la requête (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : un script ou un `onclick=` injecté par une faille XSS est bloqué par le navigateur. Les vues utilisent des attributs déclaratifs `data-*` gérés par `csp-actions.js` au lieu de handlers inline ; les bundles doivent faire de même (voir [Créer un bundle](creating-a-bundle.fr.md#content-security-policy--pas-de-script-inline)).
- **Rate limiting :** protège le login et les endpoints API sensibles contre les attaques par force brute.

## 🛠 Gestion des secrets
- **Secrets d'instance :** gérés par l'orchestrateur SaaS lors du provisioning.
- **Isolation d'environnement :** les secrets ne sont jamais commités dans le contrôle de version et sont stockés dans des fichiers `.env` isolés ou des coffres-forts sécurisés (ex. Vault).

## ⚖ Conformité
- **Préparation RGPD :** la portabilité des données, le droit à l'oubli et l'isolation physique simplifient la conformité RGPD pour le SaaS.
- **Droit du travail local :** l'architecture permet un suivi strict des heures et des pauses conformément aux réglementations locales.
