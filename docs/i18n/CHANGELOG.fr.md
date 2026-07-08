# Changelog

🌐 [English](../../CHANGELOG.md) · **Français** · [日本語](CHANGELOG.ja.md)

Toutes les évolutions notables de Kintai sont documentées ici.

## [0.0.3-beta] - 2026-07-08

### Ajouté
- **Première bêta publique.** Planning de shifts complet (vues liste/calendrier/semaine/jour/timeline Gantt, drag & drop, actions en masse, impression, détection des conflits de chevauchement, shifts ouverts + bourse aux shifts, import Excel avec analyse visuelle et score de confiance) ; temps & présence (pointage clock-in/out avec chronomètre en direct, édition admin, estimation automatique du salaire) ; espace employé (demandes de congés, échanges de shifts, disponibilités hebdomadaires, flux iCal, widget de feedback) ; messagerie interne par fils de discussion avec mise à jour en direct (SSE) et centre de notifications ; rapports journaliers avec workflow brouillon → soumis → validé, export PDF (compatible CJK), auto-validation planifiée et envoi par email ; API REST versionnée (`/api/v1`, authentification Bearer, pagination) couvrant toutes les ressources ; mise à jour en un clic depuis les Releases GitHub avec suivi de progression et backup/rollback automatique ; support SQLite et MySQL via un système de migration Eloquent unifié et indépendant du driver ; traductions FR/EN/JA.
