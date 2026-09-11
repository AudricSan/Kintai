# Mascotte Foxy — inventaire des poses

Ce document liste les poses de Foxy (la mascotte de Kintai) déjà intégrées à
l'application, celles disponibles mais pas encore utilisées, et les
éventuels manques restants.

## Sources

Deux planches de référence coexistent (documents de travail, à ne jamais
utiliser directement dans l'app — chaque pose utilisée en est extraite,
détourée et exportée en PNG dans `public/assets/img/mascot/`) :

- `docs/brand/foxy-style-guide.png` — planche d'origine (15 poses + 9
  expressions seules, visage rond sans corps). Foxy n'y porte pas encore le
  sac "K". C'est la **seule source** pour les 9 expressions seules.
- `docs/brand/foxy_pose.png` — planche consolidée (24 poses, grille 6×4),
  générée à partir des demandes de la section 3 de la v1 de ce document.
  Foxy y porte systématiquement le petit sac banane vert "K", plus
  cohérent visuellement. **Source à privilégier** pour toute pose qui y
  figure — y compris pour ré-exporter une pose déjà utilisée depuis
  l'ancienne planche, par cohérence visuelle.
  - Un upscale ×4 de cette planche a été généré puis comparé à l'original :
    qualité inégale (linework plus net sur certaines cases, mais formes
    légèrement déformées sur d'autres, ex. l'oreille/la bandoulière de la
    pose 16). Comme la taille d'export cible (240px de haut) est inférieure
    à la résolution native de la planche, l'upscale n'apporte rien et a été
    écarté — on découpe directement depuis `foxy_pose.png`.

## 1. Poses intégrées

| Pose | Source | Fichier exporté | Contexte d'usage |
|---|---|---|---|
| 考える (réfléchit, "?") | style-guide (v1) | `error-404.png` | Page 404 — absente de la planche consolidée, donc toujours issue de l'ancienne planche (sans le sac) |
| びっくり (surpris, "!!") | foxy_pose #1 | `error-403.png` | Page 403 |
| 待機・通常 (neutre) | foxy_pose #2 | `error-405.png` | Page 405 |
| 振り返る (se retourne) | foxy_pose #3 | `error-422.png` | Page 422 |
| がっかり (déçu) | foxy_pose #4 | `error-500.png` | Page 500 |
| 寝る (dort) | foxy_pose #5 | `error-503.png` | Page 503 (maintenance) |
| スマホを見る (regarde son téléphone) | foxy_pose #6 | `docs-hero.png` | Sidebar wiki + page "wiki non cloné" (`/docs`) |
| Salut / accueil | foxy_pose #16 | `wave-hello.png` | Bandeau des pages invité (`layout/guest.php` : login, mot de passe oublié, réinitialisation) |
| ボックス (空) | foxy_pose #18 | `box-empty.png` | Icône par défaut de **tout** `.empty-state` (CSS `::before`) — couvre ~25 emplacements dans l'app (dashboard, plannings, demandes, messages, rapports...) sans toucher aux vues |
| 通知・ベル | foxy_pose #22 | `bell.png` | Variante `.empty-state--bell`, utilisée sur `/notifications` quand la liste est vide |

## 2. Poses disponibles, pas encore utilisées

Déjà découpées/dessinées, juste pas encore câblées quelque part.

| Pose (foxy_pose) | Description | Contexte suggéré |
|---|---|---|
| #7 歩く | Marche, de profil | Étapes d'un assistant/wizard (installeur), indicateur de progression |
| #8 座る | Assis, neutre | État vide alternatif si on veut varier `box-empty` |
| #9 走る | Course | Traitement en cours (import Excel, sauvegarde, sync du wiki) |
| #10 ジャンプ | Saut joyeux | Confirmation de succès après une action (shift créé, congé approuvé) |
| #11 のび | Étirement | Accueil dashboard ("Bonjour" du matin) |
| #12 座って休憩 | Assis, détendu | Statut "en congé" (bundle TimeOff) |
| #13 応援する | Encourage, petit drapeau K | Fin d'assistant d'installation, jalon atteint |
| #14 嬉しい | Content, cœurs | Écran de remerciement (bundle Feedback), échange de shift accepté |
| #15 応援・走る | Encourage en courant | Mise à jour / sauvegarde en cours (variante dynamique de #9) |
| #17 検索（空） | Regarde une barre de recherche vide | "Aucun résultat" sur une liste avec recherche/filtre — pas encore câblé faute d'un point d'accroche CSS générique équivalent à `.empty-state` pour ce cas précis |
| #19 タイムカード・時計 | Tient une horloge de pointage | Bundle Timeclock (confirmation de pointage) |
| #21 ツール・メンテナンス | Clé à molette + boîte à outils | Page de mise à jour admin (`system/update.php`) pendant une mise à jour |
| #23 祝う・節目 | Confettis, sifflet de fête | Jalon célébré (mise à jour réussie, sauvegarde terminée) |
| #24 疲れてるけど立ってる | Debout, un peu fatigué | Alerte douce sur charge de travail (proche du max de jours consécutifs) |

Les 9 expressions seules de l'ancienne planche (通常/normal, にっこり/sourire,
ウインク/clin d'œil, びっくり/surpris, 困る/embêté, 照れる/gêné, 怒る/fâché,
悲しい/triste, ???/perplexe) restent disponibles pour des contextes compacts
(icône à côté d'un toast, d'une erreur de validation de formulaire).

## 3. Point d'attention — pose à corriger avant usage

- **#20 給与明細 (payslip)** : le renard tient une feuille où est écrit
  "給与" (japonais, "salaire") directement dans le dessin — texte non
  détachable du reste de l'illustration. Inutilisable tel quel sur une
  interface fr/en sans regénérer la pose avec un papier vierge ou une icône
  neutre à la place du texte. À refaire avant tout usage dans le bundle
  Salary Report.

## Process technique (rappel)

1. Repérer la grille de la planche source : `foxy-style-guide.png` = 8
   colonnes × 2 lignes (légende **en dessous** de chaque pose) ;
   `foxy_pose.png` = 6 colonnes × 4 lignes (légende **au-dessus**, à
   exclure du recadrage — largeur de cellule fixe 256px, la légende occupe
   les ~70 premiers pixels de hauteur de cellule).
2. Détourer le fond par flood-fill **depuis les bords uniquement**, avec un
   critère "clair et peu saturé" (`min(r,g,b) > 205` et écart max-min
   `< 32`) plutôt qu'une simple distance à une couleur de fond fixe — la
   planche consolidée mélange un fond de page blanc et un fond de carte
   vert pâle légèrement différents. Un recadrage par différence de couleur
   simple, ou un flood-fill qui ne part pas des bords, rend aussi
   transparentes les zones blanches du ventre/pattes du renard.
3. Exporter en PNG transparent, hauteur standard 240px, dans
   `public/assets/img/mascot/<contexte>.png`.
4. Utiliser la clé de traduction `mascot_alt` (déjà présente en fr/en/ja)
   pour l'attribut `alt`.
5. Pour une icône réutilisée par un CSS `background: url(...)` (cas de
   `.empty-state::before`), bien compter les niveaux de dossier depuis le
   fichier CSS où la règle est écrite (`public/assets/css/src/base.css` →
   `public/assets/img/...` = **deux** niveaux au-dessus, `../../img/...`),
   pas depuis `app.css` qui l'importe.
