# Kintai — plan de correction

**Problèmes relevés par Audric le 03/11/2026**  
**Analyse du dépôt :** 03/11/2026  
**Portée :** analyse et recommandations seulement. Aucun code, test, migration ou configuration du dépôt n'a été modifié.

## Décisions de comportement retenues

- Le planning doit ouvrir la semaine courante du magasin sélectionné, selon le jour de début de semaine configuré pour ce magasin. Il ne s'agit pas d'une vue agrégée de tous les magasins.
- Lorsqu'un employé est supprimé, conserver son ID comme tombstone anonymisé et inactif afin de ne pas réutiliser son identité. Exclure ce compte des sélections et traitements opérationnels. Les données historiques qui doivent être conservées restent rattachées à l'ancien ID.
- Un import explicitement affecté au nouvel employé peut transférer vers lui un shift exact présent sous l'ancien ID tombstone. Cette réaffectation doit découler de l'affectation explicite de la ligne importée, et non d'une simple égalité de noms.
- Ces décisions ne sont pas compatibles avec une suppression en cascade littérale de toutes les lignes historiques. Le plan ci-dessous distingue les références opérationnelles à nettoyer des références historiques à préserver.

## 1. Semaine affichée après connexion

### Constat et cause étayée

- Après un login réussi d'un administrateur ou d'un manager, [AuthController::login](D:/audri/Xamp/htdocs/Kintai/src/UI/Controller/Web/AuthController.php#L132) redirige vers `/admin/shifts/timeline` sans paramètres de date ou de vue.
- Dans [AdminShiftController::shiftsTimeline](D:/audri/Xamp/htdocs/Kintai/src/UI/Controller/Web/Scheduling/AdminShiftController.php#L378), l'absence de `start` fait prendre la date courante comme ancre, puis calcule le premier jour de la semaine selon `week_start_day` du magasin sélectionné.
- Sans paramètre `store_id`, le contrôleur choisit le premier magasin accessible. Le sélecteur de magasin du template transmet toutefois le premier jour actuellement affiché (`start`), et non la date du jour ([shifts-timeline.php](D:/audri/Xamp/htdocs/Kintai/src/UI/View/scheduling/shifts-timeline.php#L128)).
- L'application utilise le fuseau horaire de l'instance pour les fonctions PHP de date; le fuseau configuré par magasin n'intervient pas dans ce calcul ([Application::loadConfig](D:/audri/Xamp/htdocs/Kintai/src/Core/Application.php#L392), [InstanceTimezone](D:/audri/Xamp/htdocs/Kintai/src/Core/Services/InstanceTimezone.php#L12)).

**Conclusion :** le code lu devrait afficher la semaine courante lors d'un login neuf, sans URL `start` préexistante. Le défaut n'est donc pas démontré sur ce chemin précis. Le point faible vérifiable est le changement de magasin : il conserve la date de la vue précédente, ce qui peut afficher une semaine passée ou future au lieu de la semaine courante du magasin nouvellement sélectionné. Une différence de date autour de minuit dépend aussi du fuseau de l'instance.

### Corrections proposées

1. Rendre explicite le contexte du redirect de login (`view=week` et date du jour dans le fuseau de l'instance), au lieu de dépendre d'une valeur implicite.
2. Au changement de magasin, réancrer la période sur la date courante, puis recalculer la semaine avec le `week_start_day` du magasin choisi. Garder la navigation précédente/suivante pour les changements de période volontaires.
3. Centraliser le calcul « date courante → début de semaine » dans un helper testable, en utilisant le même fuseau que l'application et le paramètre de semaine propre au magasin.
4. Vérifier que les vues planning qui partagent ce comportement (timeline et calendrier, le cas échéant) appliquent la même règle sans modifier le comportement des liens de navigation explicites.

### Tests de non-régression à ajouter

- Login réussi admin et manager : destination timeline avec la semaine courante, sans conserver une période antérieure.
- Deux magasins accessibles ayant des `week_start_day` différents : sélectionner chacun doit afficher sa propre semaine courante.
- Changer de magasin depuis une semaine passée ou future : revenir à la semaine courante du magasin choisi.
- Cas de bord autour du jour de début de semaine et autour du changement de date selon le fuseau de l'instance.
- Navigation vers la semaine précédente/suivante : la période volontairement choisie reste affichée.

**À reproduire avant de coder :** relever l'URL exacte après le login concerné, l'identifiant du magasin et son `week_start_day`, ainsi que l'heure/fuseau au moment du problème. Ces éléments distingueront un changement de magasin qui conserve `start` d'un problème de date/fuseau.

## 2. Import de shifts après suppression puis recréation d'un employé

### Cause étayée

- La suppression standard anonymise l'utilisateur mais conserve sa ligne : l'ancien ID et ses liens historiques ne disparaissent pas. L'anonymiseur marque le compte inactif et supprimé, mais ne supprime pas ses appartenances aux magasins ni ses alias d'import.
- [DatabaseUserRepository::findAll](D:/audri/Xamp/htdocs/Kintai/src/Core/Repositories/DatabaseUserRepository.php#L47) renvoie tous les utilisateurs, y compris les tombstones.
- [AdminShiftImportController::processImport](D:/audri/Xamp/htdocs/Kintai/src/UI/Controller/Web/Scheduling/AdminShiftImportController.php#L118) construit les membres du magasin à partir de cette liste et des appartenances sans filtrer `deleted_at` ou `is_active`. `storeMemberIds()` renvoie chaque membre enregistré, sans ce filtre.
- `resolveEntryUserIds()` construit une correspondance nom → ID sur ces membres, puis peut utiliser un alias enregistré du magasin. Un ancien compte anonymisé ou un alias vers son ID peut donc encore gagner la résolution, surtout si le nom a été réutilisé.
- Lors de la confirmation, le contrôleur cherche un shift exact avec le triplet utilisateur/magasin/date. S'il en trouve un, il incrémente `skipped` et ne change pas son propriétaire. La recherche d'un shift exact ne permet donc pas de transférer le shift existant d'un ancien ID tombstone vers le nouvel ID explicitement choisi.
- Le résolveur de l'import Excel déduplique également à partir du nom, de la date et des heures ([StrictDeduplicator](D:/audri/Xamp/htdocs/Kintai/src/Core/Services/ExcelShiftImport/Phase3/StrictDeduplicator.php#L25), [DeduplicationKey](D:/audri/Xamp/htdocs/Kintai/src/Core/Services/ExcelShiftImport/Phase3/DeduplicationKey.php#L17)); le nom ne doit donc pas servir d'identité de compte.

### Corrections proposées

1. Construire les choix et la correspondance d'import uniquement depuis les membres actifs du magasin : compte actif et `deleted_at` nul. Ne pas traiter une simple correspondance de nom comme une affectation suffisamment sûre si plusieurs personnes partagent ce nom.
2. Valider chaque alias avant de l'utiliser : son utilisateur doit exister, être actif et appartenir au magasin importé. Écarter et nettoyer les alias vers un tombstone ou un ancien membre; ne pas laisser l'alias ancien écraser un choix explicite fait dans la prévisualisation.
3. Après affectation explicite à un nouvel utilisateur actif, détecter les shifts exacts par magasin/date/heures, indépendamment de l'ancien `user_id`. Si le propriétaire actuel est le tombstone correspondant, mettre à jour le shift existant avec le nouvel ID au lieu de le marquer doublon ou d'en créer un second.
4. Ne jamais transférer automatiquement un shift historique sur le seul fait que les deux comptes ont le même nom. Si le propriétaire existant est un autre utilisateur actif, signaler le conflit dans la prévisualisation et laisser le choix à l'administrateur.
5. Garder l'ancien ID tombstone et les shifts qui ne font pas l'objet d'une affectation explicite; ne pas réattribuer en masse tout l'historique au nouvel utilisateur.
6. Traiter le transfert du shift et la mise à jour/nettoyage de l'alias comme une opération cohérente, afin qu'une interruption ne laisse pas le shift et l'alias en désaccord.

### Tests de non-régression à ajouter

- Un compte tombstone avec le même nom et une ancienne appartenance au magasin n'est pas proposé comme employé actif et ne gagne pas la résolution.
- Un alias pointant vers l'ancien ID est ignoré ou nettoyé; l'affectation explicite au nouvel ID est prioritaire et persiste pour les imports suivants.
- Une ligne explicitement affectée au nouvel employé transfère le shift exact qui était sous le tombstone : un seul shift subsiste et son `user_id` devient le nouvel ID.
- Un shift historique non mentionné par l'import demeure sous l'ancien ID et n'apparaît pas comme appartenant au nouvel employé.
- Un shift exact attribué à un autre compte actif n'est pas transféré silencieusement.
- Vérifier l'unicité après import, les shifts qui traversent minuit, les alias par magasin et les cas de noms identiques/ambiguës.

## 3. Références restantes après suppression d'un rapport de démission et d'un employé

### Cause étayée

- La suppression d'un employé depuis l'interface d'administration appelle [UserAnonymizer::anonymize](D:/audri/Xamp/htdocs/Kintai/src/UI/Controller/Web/Staff/AdminUserController.php#L889), elle ne fait pas de suppression physique. Les tests existants confirment que la ligne utilisateur ne doit pas être supprimée ([UserAnonymizerTest](D:/audri/Xamp/htdocs/Kintai/tests/Unit/Core/UserAnonymizerTest.php#L62)).
- L'anonymisation conserve l'ID et les données historiques (shifts, pointages, paie) par conception; c'est précisément ce qui réserve l'ancien ID et évite de confondre une personne recréée avec l'ancienne.
- Les clés étrangères ne décrivent pas toutes une cascade complète. Par exemple, `resignation_reports.user_id` et `created_by` sont en `ON DELETE SET NULL` dans [la migration des rapports de démission](D:/audri/Xamp/htdocs/Kintai/database/migrations/php/2026_06_09_000036_create_resignation_reports_table.php#L32). Ces actions ne se déclenchent pas lors d'une anonymisation, puisque la ligne `users` reste présente.
- La migration de réparation SQLite restaure délibérément un parent `users` manquant pour préserver l'historique. Une suppression en cascade de toutes les références contredirait donc la politique documentée du projet et le tombstone que tu as confirmé.
- Les références opérationnelles les plus directement liées au bug d'identité sont les appartenances magasin et les alias d'import : contrairement aux rôles et jetons, elles ne sont pas retirées par le chemin standard d'anonymisation et peuvent laisser l'ancien compte dans des traitements.

### Corrections proposées

1. Conserver la suppression standard comme anonymisation avec tombstone : effacer les données personnelles, garder l'ID inactif et non réutilisable, révoquer les rôles/jetons et préserver les liens historiques qui doivent rester consultables.
2. Dans la même opération transactionnelle d'anonymisation, retirer les associations exclusivement opérationnelles qui ne doivent plus être utilisées : au minimum les appartenances de magasin et les alias d'import de cet ID. Inventorier de la même façon les autres associations utilisées pour proposer ou assigner un employé.
3. Garder les références d'historique nécessaires attachées au tombstone; les vues et calculs opérationnels doivent les exclure ou les afficher sous le libellé anonymisé, sans les rattacher à un nouvel ID.
4. Auditer les sélecteurs et tâches qui consomment `findAll()` : ne pas changer globalement cette méthode si des pages d'historique en ont besoin; fournir/utiliser une sélection explicite des utilisateurs actifs pour les affectations.
5. Vérifier les tables Core et celles des bundles effectivement installés pour classer chaque référence vers `users` en « opérationnelle à nettoyer » ou « historique à conserver ». Le dépôt analysé ne contient pas de source de bundle dans `storage/bundles`; les références propres à un bundle installé devront être vérifiées dans son dépôt/runtime.
6. Corriger la formulation « suppression en cascade complète » : avec le comportement confirmé (tombstone + historique conservé), toutes les références ne peuvent pas disparaître de la base. Si une purge physique reste souhaitée ultérieurement, en faire une action distincte, explicite et confirmée, qui réserve durablement l'ID dans un mécanisme de tombstone et spécifie les historiques à effacer.

### Tests de non-régression à ajouter

- Suppression admin/API : compte anonymisé, inactif, ancien ID conservé, rôles et authentifiants révoqués.
- Après suppression, l'ancien ID n'est plus membre actif du magasin et aucun alias d'import opérationnel ne le cible.
- Création d'un autre employé avec le même nom : nouvel ID distinct; sélection, import et assignation ne choisissent jamais l'ancien tombstone par défaut.
- Les shifts historiques restent associés à l'ancien ID jusqu'à une réaffectation explicite par import; aucune réattribution en masse n'a lieu.
- Vérifier les références de rapport, de paie, de pointage et des bundles installés selon la classification historique/opérationnelle retenue.
- Tester SQLite et MySQL, car les cascades SQLite ne doivent pas être supposées équivalentes dans le code applicatif.

## Périmètre de validation

Les tests existants couvrent la redirection de login sans vérifier sa destination exacte, la correspondance d'import limitée à des membres de magasin sans scénario tombstone, et l'anonymisation sans vérification des alias/appartenances persistants. Aucun test n'a été exécuté, puisqu'aucun code n'a été modifié et que ce livrable est un plan d'analyse/correction.
