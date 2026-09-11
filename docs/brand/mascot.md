# Mascotte Foxy — inventaire des poses

Ce document liste les poses de Foxy (la mascotte de Kintai) déjà intégrées à
l'application, celles disponibles dans la planche de référence mais pas
encore utilisées, et celles qu'il faudrait faire dessiner en plus. Il sert de
référence pour la prochaine série d'intégrations.

Planche de référence source : `docs/brand/foxy-style-guide.png` (ne pas
utiliser directement dans l'app — c'est un document de travail, pas un
asset). Chaque pose utilisée en est extraite, détourée (fond transparent) et
exportée en PNG dans `public/assets/img/mascot/`, hauteur standard 240px.

## 1. Poses déjà intégrées

| Pose (planche) | Fichier exporté | Contexte d'usage | Pourquoi ce choix |
|---|---|---|---|
| 考える (réfléchit, "?") | `error-404.png` | Page 404 | Il cherche la page, comme l'utilisateur |
| びっくり (surpris, "!!") | `error-403.png` | Page 403 | Réaction d'alerte face à un accès refusé |
| 待機・通常 (neutre) | `error-405.png` | Page 405 | Cas rare et neutre, pas besoin de dramatiser |
| 振り返る (se retourne) | `error-422.png` | Page 422 | Invite à revérifier ce qui a été saisi |
| がっかり (déçu) | `error-500.png` | Page 500 | Erreur serveur, on partage la déception |
| 寝る (dort) | `error-503.png` | Page 503 (maintenance) | Le site "dort" pendant la maintenance |
| スマホを見る (regarde son téléphone/l'app) | `docs-hero.png` | Sidebar wiki + page "wiki non cloné" (`/docs`) | Il consulte l'app, comme l'utilisateur qui lit la doc |

## 2. Poses disponibles dans la planche, pas encore utilisées

Pas besoin de nouveau dessin pour celles-ci — juste les découper/détourer
avec le même script (crop + flood-fill transparence depuis les bords, cf.
processus utilisé pour la série actuelle).

| Pose (planche) | Description | Contexte suggéré |
|---|---|---|
| 歩く (marche) | Démarche tranquille, de profil | Étapes d'un assistant/wizard (ex. installeur `public/install.php`), indicateur de progression |
| 座る (assis, neutre) | Assis, calme, regarde devant lui | État vide neutre générique (ex. `EmptyState` : aucune notification, aucun message) |
| 走る (court) | Course, mouvement | Traitement en cours (import Excel, sauvegarde, synchronisation du wiki) |
| ジャンプ (saute) | Saut joyeux | Confirmation de succès après une action (shift créé, congé approuvé) |
| のび (s'étire) | Étirement, réveil | Accueil sur le dashboard ("Bonjour" du matin), reprise après une pause |
| 座って休憩 (assis, repos) | Assis, détendu, pattes croisées | Statut "en congé" (bundle TimeOff), jour sans shift |
| 応援する (encourage, drapeau K) | Debout, agite un petit drapeau vert avec le logo K | Fin de l'assistant d'installation, objectif/jalon atteint dans les stats |
| 嬉しい (content, cœurs) | Assis, visage joyeux, petits cœurs autour | Écran de remerciement (bundle Feedback), échange de shift accepté |
| 応援 走る (encourage en courant) | Court en brandissant le drapeau K | Mise à jour de l'app en cours, sauvegarde en cours (variante plus dynamique que "走る") |

Les 9 expressions seules (visage rond, sans corps — 通常/normal, にっこり/sourire,
ウインク/clin d'œil, びっくり/surpris, 困る/embêté, 照れる/gêné, 怒る/fâché,
悲しい/triste, ???/perplexe) sont utilisables telles quelles pour des contextes
compacts : petite icône à côté d'un message toast, d'une erreur de validation
de formulaire, ou d'un badge de notification — pas besoin de tout le corps.

## 3. Poses à faire dessiner (n'existent pas dans la planche actuelle)

| Pose proposée | Description visuelle | Contexte d'usage | Priorité |
|---|---|---|---|
| Salut / accueil | Debout, une patte levée en signe de salut, sourire | Page de connexion (`auth/login.php`), message de bienvenue sur le dashboard | Haute |
| Recherche vide | Assis, tient une petite loupe, hausse les épaules | "Aucun résultat" dans les listes avec recherche/filtre (staff, shifts, journal d'activité) | Haute |
| Boîte vide | Regarde dans une boîte/panier ouvert et vide | État vide neutre pour listes de données (aucun shift cette semaine, aucun message) — à distinguer de "がっかり" (déçu), qui reste réservé aux erreurs | Haute |
| Pointeuse / horloge | Tient un badge ou une horloge de pointage | Confirmation de pointage (bundle Timeclock), écran de clock-in/out | Moyenne |
| Enveloppe de paie | Tient une enveloppe ou une pièce | Pages liées à la paie (Salary Report, fiche de paie estimée) | Moyenne |
| Outils / maintenance | Tient une clé à molette ou un tournevis | Page de mise à jour admin (`system/update.php`) pendant une mise à jour — différent du 503 (public, "site en dodo") | Moyenne |
| Cloche / notification | Tient ou regarde une clochette | Centre de notifications, vide ou avec nouveauté | Basse |
| Fête / jalon | Petit chapeau de fête, confettis | Jalon célébré (ex. mise à jour réussie, sauvegarde terminée, premier mois d'utilisation) | Basse |
| Fatigué mais debout | Debout, s'essuie le front, sourire un peu crispé | Alerte douce sur charge de travail (ex. proche du nombre max de jours consécutifs), à ne pas confondre avec une erreur | Basse |

## Process technique (rappel)

1. Recadrer la pose depuis `docs/brand/foxy-style-guide.png` (grille de la
   section "ポーズバリエーション", 8 colonnes × 2 lignes).
2. Exclure la légende japonaise du recadrage (elle n'est pas traduite et ne
   doit pas apparaître dans l'app).
3. Détourer le fond crème (`#FDFBF7` environ) par flood-fill **depuis les
   bords uniquement** — un recadrage par différence de couleur simple rend
   aussi transparentes les zones blanches du ventre/pattes du renard, ce qui
   casse le rendu sur fond sombre.
4. Exporter en PNG transparent, hauteur standard 240px, dans
   `public/assets/img/mascot/<contexte>.png`.
5. Utiliser la clé de traduction `mascot_alt` (déjà présente en fr/en/ja)
   pour l'attribut `alt`.
