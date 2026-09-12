# Mascotte Foxy — inventaire des poses

Ce document liste les poses de Foxy (la mascotte de Kintai) déjà intégrées à
l'application, celles disponibles mais pas encore utilisées, et les
éventuels manques restants.

## Sources

Documents de travail, à ne jamais utiliser directement dans l'app — chaque
pose utilisée en est extraite/détourée et exportée en PNG dans
`public/assets/img/mascot/` :

- `docs/brand/foxy-style-guide.png` — planche d'origine (15 poses + 9
  expressions seules, visage rond sans corps). Foxy n'y porte pas le sac
  "K". Reste la **seule source** pour les 9 expressions seules et pour
  考える (réfléchit, utilisé par `error-404.png`), absente de toutes les
  planches suivantes.
- `docs/brand/foxy_pose.png` (v1) et `docs/brand/foxy-pose-sheet-v2.png`
  (v2, avec expressions + scènes bonus) — planches consolidées 24 poses,
  Foxy y porte le sac "K". Un upscale ×4 de la v1 a été comparé à
  l'original et écarté (qualité inégale, aucun gain à la taille d'export
  utilisée, 240px de haut).
- **`docs/brand/NN_nom.png`** — depuis le 12/09/2026, source à privilégier :
  poses générées individuellement en HD, fond blanc uni (pas de carte, pas
  de légende), qualité et détail nettement supérieurs aux planches en
  grille. Numérotation alignée sur celle de `foxy_pose.png`/`foxy-pose-
sheet-v2.png` (1 à 24). C'est la source utilisée pour toute pose
  disponible en HD ; les planches en grille ne servent plus que pour les
  poses pas encore régénérées individuellement (2, 16) et pour 考える.
- **`docs/brand/expr-*-hd.png`** — même jour, mêmes propriétés (fond blanc
  uni à détourer) que `NN_nom.png` mais pour 5 des 9 expressions seules
  (jusque-là uniquement en basse résolution depuis `foxy-style-guide.png`) :
  `expr-normal-hd.png` (通常), `expr-smile-hd.png` (にっこり),
  `expr-wink-hd.png` (ウインク), `expr-worried-hd.png` (困る),
  `expr-angry-hd.png` (怒る). `docs/brand/foxy-expressions-sheet-hd.png`
  est la planche de référence dont elles sont extraites (8 expressions au
  total — びっくり/照れる/??? y figurent aussi mais n'ont pas encore été
  découpées individuellement ; 悲しい et 考える n'y figurent pas).

Les fichiers `ChatGPT Image..._02_12_30.png` (mockup publicitaire parodiant
Suica/JR East, hors-scope) et `ChatGPT Image..._08_26_29.png` (aperçu à 2
cases "考える"/"びっくり", contenait la seule version HD connue de 考える)
mentionnés ici jusqu'au 12/09/2026 ont depuis disparu du dossier — 考える
n'a donc plus aucune source HD (voir section 3).

## 1. Poses intégrées dans l'app

| Pose                                 | Source actuelle                                                     | Fichier exporté  | Contexte d'usage                                                                                  |
| ------------------------------------ | ------------------------------------------------------------------- | ---------------- | ------------------------------------------------------------------------------------------------- |
| 考える (réfléchit, "?")              | style-guide (basse résolution)                                      | `error-404.png`  | Page 404 — pas encore de version HD individuelle (voir ci-dessus)                                 |
| びっくり (surpris, "!!")             | `01_surpris.png` (HD)                                               | `error-403.png`  | Page 403                                                                                          |
| 待機・通常 (neutre)                  | foxy_pose #2 (grille, pas encore en HD)                             | `error-405.png`  | Page 405                                                                                          |
| 振り返る (se retourne)               | `03_se_retourne.png` (HD)                                           | `error-422.png`  | Page 422                                                                                          |
| がっかり (déçu)                      | `04_decu.png` (HD)                                                  | `error-500.png`  | Page 500                                                                                          |
| 寝る (dort)                          | `05_dort.png` (HD)                                                  | `error-503.png`  | Page 503 (maintenance)                                                                            |
| スマホを見る (regarde son téléphone) | `06_telephone.png` (HD)                                             | `docs-hero.png`  | Sidebar wiki + page "wiki non cloné" (`/docs`)                                                    |
| Salut / accueil                      | foxy_pose #16 (grille, pas encore en HD)                            | `wave-hello.png` | Bandeau des pages invité (`layout/guest.php` : login, mot de passe oublié, réinitialisation)      |
| ボックス (空)                        | foxy_pose #18 (grille, 2 variantes HD dispo, pas encore ré-exporté) | `box-empty.png`  | Icône par défaut de **tout** `.empty-state` (CSS `::before`) — couvre ~25 emplacements dans l'app |
| 通知・ベル                           | foxy_pose #22 (grille, HD dispo, pas encore ré-exporté)             | `bell.png`       | Variante `.empty-state--bell`, utilisée sur `/notifications`                                      |

Les fichiers déjà utilisés dans l'app (`error-403/404/405/422/500/503.png`,
`docs-hero.png`, `wave-hello.png`, `box-empty.png`, `bell.png`) restent ceux
exportés depuis la planche en grille pour 2/16/18/22 — à re-découper depuis
les HD listées en section 2 quand on retouchera ces pages, pour gagner en
netteté (403/422/500/503/404... voir ligne "docs-hero" : déjà HD).

## 2. Poses disponibles en HD, pas encore câblées dans l'app

Fichiers `docs/brand/NN_nom.png`, fond blanc à détourer avant export vers
`public/assets/img/mascot/` (process en section 4).

| Fichier                    | Pose                                               | Contexte suggéré                                                                                                                                                 |
| -------------------------- | -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `07_marche.png`            | 歩く, marche de profil                             | Étapes d'un assistant/wizard (installeur), indicateur de progression                                                                                             |
| `08_assis.png`             | 座る, assis neutre                                 | État vide alternatif si on veut varier `box-empty`                                                                                                               |
| `09_court.png`             | 走る, course                                       | Traitement en cours (import Excel, sauvegarde, sync du wiki)                                                                                                     |
| `10_saute.png`             | ジャンプ, saut joyeux                              | Confirmation de succès après une action (shift créé, congé approuvé)                                                                                             |
| `11_etirement.png`         | のび, étirement                                    | Accueil dashboard ("Bonjour" du matin)                                                                                                                           |
| `12_repos.png`             | 座って休憩, assis détendu                          | Statut "en congé" (bundle TimeOff)                                                                                                                               |
| `13_encourage.png`         | 応援する, pompons                                  | Fin d'assistant d'installation, jalon atteint                                                                                                                    |
| `14_content.png`           | 嬉しい, saute de joie                              | Écran de remerciement (bundle Feedback), échange de shift accepté                                                                                                |
| `15_encourage_court.png`   | 応援・走る, court en brandissant un drapeau K      | Mise à jour / sauvegarde en cours (variante dynamique de #9)                                                                                                     |
| `17_recherche_vide.png`    | 検索（空）, loupe + bulle "?"                      | "Aucun résultat" sur une liste avec recherche/filtre — pas encore câblé faute d'un point d'accroche CSS générique équivalent à `.empty-state` pour ce cas précis |
| `18_boite_vide_penche.png` | ボッwクス（空）, penché par-dessus le carton       | Variante possible de l'icône `.empty-state` par défaut                                                                                                           |
| `18_boite_vide_assis.png`  | ボックス（空）, assis dans le carton               | Variante possible de l'icône `.empty-state` par défaut — c'est celle-ci qui est la plus proche du cutout `box-empty.png` déjà en place                           |
| `19_pointeuse.png`         | タイムカード・時計, pointeuse "08:59"              | Bundle Timeclock (confirmation de pointage)                                                                                                                      |
| `21_Maintenance.png`       | ツール・メンテナンス, casquette K + boîte à outils | Page de mise à jour admin (`system/update.php`) pendant une mise à jour                                                                                          |
| `22_cloche.png`            | 通知・ベル                                         | Remplacement HD de `bell.png`                                                                                                                                    |
| `23_fete.png`              | 祝う・節目, confettis (déjà fond transparent !)    | Jalon célébré (mise à jour réussie, sauvegarde terminée)                                                                                                         |
| `24_fatigue.png`           | 疲れてるけど立ってる, debout, goutte de sueur      | Alerte douce sur charge de travail (proche du max de jours consécutifs)                                                                                          |

Des 9 expressions seules de l'ancienne planche (通常/normal, にっこり/sourire,
ウインク/clin d'œil, びっくり/surpris, 困る/embêté, 照れる/gêné, 怒る/fâché,
悲しい/triste, ???/perplexe), utiles pour des contextes compacts, 5 sont
maintenant disponibles en HD individuellement (通常, にっこり, ウインク,
困る, 怒る — voir `expr-*-hd.png` en Sources) ; びっくり/照れる/??? restent
en basse résolution malgré leur présence dans `foxy-expressions-sheet-hd.png`
(pas encore découpées) ; 悲しい et 考える restent sans aucune source HD.

`public/assets/img/mascot/brand-icon.png` (visage de Foxy dans la topbar,
`.topbar-brand__icon`) est détouré et recadré depuis `expr-wink-hd.png`.

## 3. Poses encore manquantes en HD

- **#2 待機・通常** (idle debout, neutre) — utilisé par `error-405.png`,
  toujours en basse résolution.
- **#16 Salut / accueil** — utilisé par `wave-hello.png`, toujours en basse
  résolution.
- **考える** (réfléchit) — utilisé par `error-404.png` ; l'aperçu qui en
  contenait une version HD a disparu du dossier (voir Sources), aucune
  source HD connue ne subsiste.

## 4. Points d'attention

- **`20_fiche_paie.png` (#20 給与明細)** : le renard tient une feuille où
  est écrit "給与明細" (japonais) directement dans le dessin — texte non
  détachable du reste de l'illustration. Inutilisable tel quel sur une
  interface fr/en sans regénérer la pose avec un papier vierge ou une icône
  neutre à la place du texte. À refaire avant tout usage dans le bundle
  Salary Report.
- **Pose 15, variante "salue de la main" perdue (12/09/2026)** : la planche
  HD comptait deux prises pour 応援・走る — une avec un drapeau K, une avec
  un simple signe de la main (sans accessoire). En renommant les deux, la
  seconde a été écrasée par erreur par la première (`mv` vers un nom déjà
  pris) avant d'avoir un nom distinct, et n'est pas récupérable localement
  (pas de corbeille, dossier non synchronisé). Il ne reste que la version
  au drapeau (`15_encourage_court.png`). Si la variante "salue de la main"
  est encore utile, elle doit être régénérée (probablement encore présente
  dans l'historique de conversation ChatGPT d'origine).

## 5. Process technique (rappel)

1. Repérer la grille de la planche source : `foxy-style-guide.png` = 8
   colonnes × 2 lignes (légende **en dessous** de chaque pose) ;
   `foxy_pose.png`/`foxy-pose-sheet-v2.png` = 6 colonnes × 4 lignes (légende
   **au-dessus**, à exclure du recadrage — largeur de cellule fixe 256px,
   la légende occupe les ~70 premiers pixels de hauteur de cellule). Les
   fichiers `NN_nom.png` individuels n'ont ni grille ni légende à exclure.
2. Détourer le fond par flood-fill **depuis les bords uniquement**, avec un
   critère "clair et peu saturé" (`min(r,g,b) > 205` et écart max-min
   `< 32`) plutôt qu'une simple distance à une couleur de fond fixe. Un
   recadrage par différence de couleur simple, ou un flood-fill qui ne part
   pas des bords, rend aussi transparentes les zones blanches du
   ventre/pattes du renard.
3. Exporter en PNG transparent, hauteur standard 240px, dans
   `public/assets/img/mascot/<contexte>.png`.
4. Utiliser la clé de traduction `mascot_alt` (déjà présente en fr/en/ja)
   pour l'attribut `alt`.
5. Pour une icône réutilisée par un CSS `background: url(...)` (cas de
   `.empty-state::before`), bien compter les niveaux de dossier depuis le
   fichier CSS où la règle est écrite (`public/assets/css/src/base.css` →
   `public/assets/img/...` = **deux** niveaux au-dessus, `../../img/...`),
   pas depuis `app.css` qui l'importe.
6. **Ne jamais `mv`/renommer directement vers un nom de fichier déjà
   utilisé** sans vérifier d'abord qu'il est libre (`mv -n`, ou lister le
   dossier juste avant) — un renommage écrase silencieusement la cible sur
   ce système de fichiers, sans corbeille ni confirmation.
