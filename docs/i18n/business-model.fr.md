# Modèle économique : Kintai

🌐 [English](../business-model.md) · **Français** · [日本語](business-model.ja.md)

## 💰 Stratégie de monétisation : open core, service payant

L'intégralité du code de Kintai — cœur et bundles (Messagerie, Rapports journaliers, et tout ce qui sera ajouté ensuite) — est en AGPL-3.0. Rien dans ce dépôt n'est propriétaire ou verrouillé derrière une licence payante : n'importe qui peut auto-héberger l'intégralité des fonctionnalités gratuitement. Le revenu provient du fait d'**exploiter** Kintai pour des clients qui préfèrent ne pas le faire eux-mêmes, pas de la rétention de fonctionnalités.

### 1. SaaS géré (principale source de revenus)
Kintai en tant que **SaaS géré** : le même code open source, exploité et maintenu pour le compte du client.
- Déploiement et provisioning automatisés (`scripts/provision.php`)
- Sauvegardes et mises à jour de sécurité gérées
- Gestion SSL/domaine
- Choix du canal de mise à jour (Stable / Bêta)

### 2. Support & services d'implémentation
- Onboarding et migration de données depuis des tableurs ou d'anciens outils
- Configuration sur mesure (règles de magasin, paramètres de déduction de paie, traductions)
- Contrats de support prioritaire pour les clients Enterprise auto-hébergés

### 3. Tarification SaaS par paliers
Chaque palier exécute le même code open source — les clients auto-hébergés peuvent activer n'importe quel bundle ou fonctionnalité gratuitement en modifiant `config/bundles.php`. Sur le SaaS géré, le palier détermine ce que nous activons et supportons par défaut pour ce client, pas ce qui existe dans le code :

| Palier | Prix | Limites principales |
| --- | --- | --- |
| Community | Gratuit | Auto-hébergement, fonctionnalités de base |
| Pro | XX €/mois | Fonctionnalités avancées, sauvegardes, support standard |
| Business | XX €/mois | Multi-utilisateurs, SSO, API avancée, support prioritaire |
| Enterprise | Sur devis | Déploiement dédié, SLA, support premium, fonctionnalités sur mesure |

## 🤝 Avantage concurrentiel
- **Efficacité des coûts :** une stack PHP légère maintient des coûts d'hébergement — et des prix — bas.
- **Flexibilité hybride :** un même client peut démarrer sur le SaaS géré et passer à l'auto-hébergement plus tard, ou l'inverse, sans réécriture.
- **Liberté de sortie :** le code étant AGPL et chaque tenant ayant sa propre base de données, nous retenons les clients par la qualité de service, pas par le verrouillage des données.

## 📈 Stratégie de croissance
- **Inbound :** visibilité open source sur GitHub, contenu technique et démo publique.
- **Vente directe :** prospection ciblée des réseaux multi-sites (retail, hôtellerie-restauration et autres secteurs à shifts) souhaitant abandonner les tableurs.
- **Écosystème de partenaires :** des tiers peuvent proposer des services « d'implémentation Kintai » au-dessus du même socle open source.
