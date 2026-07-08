# Modèle économique : Kintai

🌐 [English](business-model.md) · **Français** · [日本語](business-model.ja.md)

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
La tarification porte sur la capacité d'hébergement et le niveau de support, pas sur l'accès aux fonctionnalités — chaque palier exécute le même code open source :
- **Gratuit :** planning basique pour un seul magasin
- **Pro :** limites plus élevées, abordable pour des équipes en croissance
- **Business :** plusieurs magasins, support prioritaire
- **Enterprise :** limites sur mesure, support dédié, orchestration personnalisée

## 🤝 Avantage concurrentiel
- **Efficacité des coûts :** une stack PHP légère maintient des coûts d'hébergement — et des prix — bas.
- **Flexibilité hybride :** un même client peut démarrer sur le SaaS géré et passer à l'auto-hébergement plus tard, ou l'inverse, sans réécriture.
- **Liberté de sortie :** le code étant AGPL et chaque tenant ayant sa propre base de données, nous retenons les clients par la qualité de service, pas par le verrouillage des données.

## 📈 Stratégie de croissance
- **Inbound :** visibilité open source sur GitHub, contenu technique et démo publique.
- **Vente directe :** prospection ciblée des franchises et réseaux de magasins japonais souhaitant abandonner les tableurs.
- **Écosystème de partenaires :** des tiers peuvent proposer des services « d'implémentation Kintai » au-dessus du même socle open source.
