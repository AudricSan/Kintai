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
  "K". Reste la **seule source** pour les 9 expressions seules (考える n'y
  figure pas — voir `25_question.png` ci-dessous pour cette pose).
- `docs/brand/foxy_pose.png` (v1) et `docs/brand/foxy-pose-sheet-v2.png`
  (v2, avec expressions + scènes bonus) — planches consolidées 24 poses,
  Foxy y porte le sac "K". Un upscale ×4 de la v1 a été comparé à
  l'original et écarté (qualité inégale, aucun gain à la taille d'export
  utilisée, 240px de haut).
- **`docs/brand/NN_nom.png`** — depuis le 12/09/2026, source à privilégier :
  poses générées individuellement en HD, fond blanc uni (pas de carte, pas
  de légende), qualité et détail nettement supérieurs aux planches en
  grille. Numérotation alignée sur celle de `foxy_pose.png`/`foxy-pose-
sheet-v2.png` (1 à 24), poursuivie au-delà (25, 26) pour de nouvelles
  poses sans équivalent dans les planches en grille. C'est la source
  utilisée pour toute pose disponible en HD ; les planches en grille ne
  servent plus que pour les poses pas encore régénérées individuellement
  (2, 16).
- **`docs/brand/25_question.png`** (考える, ajouté le 16/09/2026) — comble le
  manque HD de 考える signalé en section 3 depuis le 12/09/2026 (patte sur
  le menton, bulle "?"). Envisagé un temps pour `error-404.png`, finalement
  non retenu (voir section 2) — 404 est resté sur `17_recherche_vide.png`.
- **`docs/brand/26_surpris.png`** (びっくり, ajouté le 16/09/2026) — variante
  de `01_surpris.png` avec points d'exclamation jaunes. Envisagé un temps
  pour `error-403.png`, finalement non retenu (voir section 2) — 403 est
  passé sur `27_reffu.png` à la place.
- **`docs/brand/27_reffu.png`** (拒否・refuse, ajouté le 16/09/2026) — patte
  levée en geste "stop", regard sévère, "!!" rouges. Pose finalement
  retenue pour `error-403.png`, plus parlante pour un accès refusé que les
  poses "surprise" (01/26).
- **`docs/brand/28_soupir.png`** (soupir content, assis, ajouté le
  16/09/2026 *a posteriori*) — archive de la pose utilisée pour
  `brand-icon.png` (voir section 1). Contrairement aux autres `NN_nom.png`,
  déjà détourée (fond transparent) et non recadrée : la version fournie
  par l'utilisateur a été écrasée par le recadrage final avant d'être
  sauvegardée séparément.
- **`docs/brand/405.png`** (composition dédiée, ajoutée le 16/09/2026) — à la
  différence des autres fichiers de cette liste, ce n'est pas une pose
  seule mais une scène complète (renard + panneau "sens interdit" + cartes
  GET/POST/PUT/DELETE/PATCH + bulle de dialogue), avec le texte "405 /
  Method Not Allowed" et des phrases japonaises **gravés dans l'image**.
  Ne suit pas la convention `NN_nom.png` car elle ne réutilise pas une
  pose de la bibliothèque. Voir section 4 pour l'arbitrage sur le texte en
  dur.
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
mentionnés ici jusqu'au 12/09/2026 ont depuis disparu du dossier ; 考える
est resté sans source HD jusqu'à l'ajout de `25_question.png` le
16/09/2026 (voir ci-dessus et section 3).

## 1. Poses intégrées dans l'app

| Pose                                 | Source actuelle                                                     | Fichier exporté      | Contexte d'usage                                                                                  |
| ------------------------------------ | ------------------------------------------------------------------- | --------------------- | ------------------------------------------------------------------------------------------------- |
| 拒否・refuse (patte levée, "stop")   | `27_reffu.png` (HD, ajouté 16/09/2026)                              | `http-error/403.png`    | Page 403                                                                                           |
| 検索（空） (loupe + bulle "?")       | `17_recherche_vide.png` (HD)                                        | `http-error/404.png`    | Page 404                                                                                           |
| Scène dédiée (renard + panneau interdit + texte JP en dur) | `405.png` (composition, ajoutée 16/09/2026)          | `http-error/405.png`       | Page 405 — pas de correspondance directe avec une pose de la bibliothèque (voir Sources et section 4) |
| 振り返る (se retourne)               | `03_se_retourne.png` (HD)                                           | `http-error/422.png`    | Page 422                                                                                          |
| ツール・メンテナンス (outils, casquette K) | `21_Maintenance.png` (HD)                                     | `http-error/500.png`    | Page 500 (depuis le 16/09/2026 ; remplace がっかり `04_decu.png`, désormais disponible non câblé) |
| 寝る (dort)                          | `05_dort.png` (HD)                                                  | `http-error/503.png`    | Page 503 (maintenance)                                                                            |
| スマホを見る (regarde son téléphone) | `06_telephone.png` (HD)                                             | `docs-hero.png`  | Sidebar wiki + page "wiki non cloné" (`/docs`)                                                    |
| Salut / accueil                      | foxy_pose #16 (grille, pas encore en HD)                            | `wave-hello.png` | Bandeau des pages invité (`layout/guest.php` : login, mot de passe oublié, réinitialisation)      |
| ボックス（空）, penché sur le carton | `18_boite_vide_penche.png` (HD, ajouté 16/09/2026)                  | `box-empty.png`  | Icône par défaut de **tout** `.empty-state` (CSS `::before`) — couvre ~25 emplacements dans l'app |
| 通知・ベル                           | `22_cloche.png` (HD, ajouté 16/09/2026)                             | `bell.png`       | Variante `.empty-state--bell`, utilisée sur `/notifications`                                      |
| タイムカード・時計, pointeuse "08:59" | `19_pointeuse.png` (HD, ajouté 16/09/2026)                          | `timeclock-empty.png` | Variante `.empty-state--timeclock`, utilisée sur `employee-timeclock.php` ("aucun pointage cette semaine") |
| 給与明細 (texte JP gravé dans l'image) | `20_fiche_paie.png` (HD, ajouté 16/09/2026)                       | `salary-empty.png` | Variante `.empty-state--salary`, utilisée sur `reports-salary.php` ("sr_empty") — texte japonais figé gardé délibérément, voir section 4 |

**Emplacement et nommage final (16/09/2026)** : après un essai avec un
préfixe `<numéro de pose>-` dans `public/assets/img/mascot/` (abandonné),
les 6 images d'erreur vivent dans leur propre sous-dossier
`public/assets/img/mascot/http-error/`, nommées uniquement par le code
HTTP (`403.png`, `404.png`, `405.png`, `422.png`, `500.png`, `503.png`) —
plus simple à référencer et à faire correspondre aux pages. Les 6 vues
`src/UI/View/errors/{403,404,405,422,500,503}.php` pointent vers
`/assets/img/mascot/http-error/<code>.png`.

**État final des images (16/09/2026)** : les 6 fichiers ont été détourés
(fond transparent) par l'utilisateur lui-même à partir des sources HD,
puis recadrés à la boîte englobante du contenu opaque (+ marge de 8px) et
exportés en PNG hauteur 480px (au lieu des 240px historiques — le CSS
(`error-mascot { width: 140px; height: auto }`) redimensionne de toute
façon à l'affichage, les 480px donnent une meilleure netteté sur écran
retina). `box-empty.png`, `bell.png`, `timeclock-empty.png` et
`salary-empty.png` ont été détourés/recadrés le même jour, avec le même
process (voir section 5) ; `docs-hero.png` et `wave-hello.png` restent
inchangés, à re-découper depuis les HD listées en section 2 quand on
retouchera ces pages.

**Pages de secours statiques (16/09/2026)** : en complément des vues PHP
(`src/UI/View/errors/*.php`, qui gèrent les erreurs normales de l'app —
403/404/405/422/500/503 levées par le routeur/les contrôleurs), 6 pages
HTML autonomes existent maintenant sous `public/errors/<code>.html`
(CSS inlined avec valeurs de repli, même image `http-error/<code>.png`,
pas de dépendance PHP) et sont déclarées via `ErrorDocument` dans
`public/.htaccess`. Elles ne remplacent **jamais** les pages PHP tant que
PHP répond normalement — Apache n'invoque `ErrorDocument` que lorsqu'il
génère lui-même l'erreur (PHP plante sans sortie, module PHP désactivé,
fichier statique sous `/assets/` réellement absent), donc c'est un filet
de secours pour le cas "le serveur (PHP) meurt", pas un doublon du flux
normal. Vérifié en conditions réelles sur le XAMPP local (`127.0.0.7`) :
une vraie 404 Apache (fichier statique manquant) sert bien
`public/errors/404.html`, tandis qu'une 404 applicative normale
(`Router::dispatch()` ne trouve pas de route) continue d'afficher la vue
PHP habituelle sans interférence.

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
| `18_boite_vide_assis.png`  | ボックス（空）, assis dans le carton               | Variante alternative à `box-empty.png` (voir section 1, qui utilise `18_boite_vide_penche.png`) si on veut varier l'icône par défaut                            |
| `23_fete.png`              | 祝う・節目, confettis (déjà fond transparent !)    | Jalon célébré (mise à jour réussie, sauvegarde terminée)                                                                                                         |
| `24_fatigue.png`           | 疲れてるけど立ってる, debout, goutte de sueur      | Alerte douce sur charge de travail (proche du max de jours consécutifs)                                                                                          |
| `04_decu.png`              | がっかり, déçu                                     | Libéré le 16/09/2026 (`error-500.png` utilise désormais `21_Maintenance.png`) — contexte à retrouver : refus/rejet d'une demande ?                              |
| `01_surpris.png`           | びっくり, "!!" traits verts                        | Non retenu pour error-403 (voir `27_reffu.png` en section 1) — alternative si on veut varier le ton des traits d'exclamation                                    |
| `25_question.png`          | 考える, patte sur le menton + bulle "?"            | Non retenu pour error-404 (resté sur `17_recherche_vide.png`) — pose disponible pour un autre contexte de réflexion/attente                                     |
| `26_surpris.png`           | びっくり, "!!" jaunes                              | Non retenu pour error-403 (voir `27_reffu.png` en section 1) — variante supplémentaire de surprise si besoin                                                    |

`21_Maintenance.png` (ツール・メンテナンス, casquette K + boîte à outils),
initialement suggérée ci-dessus pour la page de mise à jour admin
(`system/update.php`), est utilisée depuis le 16/09/2026 pour
`error-500.png` (voir section 1) — reste réutilisable pour `system/update.php`
si besoin, un même export pouvant servir plusieurs contextes.

Des 9 expressions seules de l'ancienne planche (通常/normal, にっこり/sourire,
ウインク/clin d'œil, びっくり/surpris, 困る/embêté, 照れる/gêné, 怒る/fâché,
悲しい/triste, ???/perplexe), utiles pour des contextes compacts, 5 sont
maintenant disponibles en HD individuellement (通常, にっこり, ウインク,
困る, 怒る — voir `expr-*-hd.png` en Sources) ; びっくり/照れる/??? restent
en basse résolution malgré leur présence dans `foxy-expressions-sheet-hd.png`
(pas encore découpées) ; 悲しい reste sans aucune source HD. 考える dispose
désormais d'une source HD dédiée, `25_question.png` (voir Sources) — distincte
de ces 9 expressions seules (pose avec corps, pas juste un visage).

`public/assets/img/mascot/brand-icon.png` (visage de Foxy dans la topbar,
`.topbar-brand__icon`, et sur la carte de palette de `/admin/settings` -
appearance) était détouré et recadré depuis `expr-wink-hd.png` (clin
d'œil) ; remplacé le 16/09/2026 par un recadrage serré (tête + oreilles,
160×160, fond transparent) d'une nouvelle pose assise (soupir content,
yeux fermés), fournie directement sous le nom `brand-icon.png` (donc déjà
écrasée par le recadrage final) — la pose complète est archivée a
posteriori sous `docs/brand/28_soupir.png` (fond déjà transparent, pas
blanc comme les autres `NN_nom.png`, faute d'avoir gardé l'original avant
recadrage) si un recadrage différent est voulu plus tard.

## 3. Poses encore manquantes en HD

- **#2 待機・通常** (idle debout, neutre) — plus utilisée nulle part depuis
  que `error-405.png` est passé à une composition dédiée (voir section 1),
  toujours en basse résolution si besoin ailleurs.
- **#16 Salut / accueil** — utilisé par `wave-hello.png`, toujours en basse
  résolution.

考える (réfléchit) a rejoint la liste des poses résolues le 16/09/2026 avec
l'ajout de `25_question.png` (voir Sources et section 1) — après avoir été
sans source HD connue depuis la disparition de son unique aperçu (voir
historique en Sources).

## 4. Points d'attention

- **`error-405.png` : texte japonais gravé dans l'image, gardé délibérément
  (16/09/2026)**. Contrairement à toutes les autres poses, cette
  composition contient "405", "Method Not Allowed" et plusieurs phrases en
  japonais directement dans les pixels (pas de calque texte détachable),
  alors que le titre/message de la page sont déjà rendus séparément par
  `error_405_title`/`error_405_message` (fr/en/ja) — un visiteur FR ou EN
  verra donc du texte japonais figé sous le titre traduit. Signalé à
  l'utilisateur, qui a explicitement choisi de le garder tel quel plutôt
  que de simplifier la pose. Si ça pose problème plus tard (retours
  utilisateurs, incohérence visuelle), la piste de repli est une version
  simplifiée : juste le renard + le panneau "interdit", sans texte gravé,
  cohérente avec le style des 5 autres pages.
- **`salary-empty.png` (#20 給与明細) : texte japonais gravé, gardé
  délibérément (16/09/2026)**. Même situation que `error-405.png` : le
  renard tient une feuille où "給与明細" est écrit directement dans le
  dessin, pas détachable — un visiteur FR/EN verra ce texte japonais figé
  sur l'état vide de `reports-salary.php`. Signalé à l'utilisateur, qui a
  choisi de l'utiliser quand même plutôt que d'attendre une regénération
  sans texte gravé.
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
