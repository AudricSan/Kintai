<?php

declare(strict_types=1);

namespace kintai\Contract;

/**
 * Contrat entre le Core et les bundles (voir docs/creating-a-bundle.md, « Contrat de bundle »).
 *
 * Tout ce qui vit sous kintai\Contract est la seule surface qu'un bundle, officiel ou tiers, a le droit
 * d'utiliser. Sa version est indépendante de celle du Core et suit le versionnage sémantique :
 * - mineure : ajout (classe, méthode optionnelle, point d'extension) ;
 * - majeure : suppression ou changement de signature, annoncé dans la section « Contrat de bundle » du CHANGELOG.
 *
 * Un bundle déclare la version qu'il requiert dans son bundle.json (« kintai_contract »: « ^1.0 ») ; le Core
 * refuse de l'installer, et ne le charge pas au démarrage, si la version fournie ne la satisfait pas.
 */
final class Contract
{
    /** Version du contrat fournie par ce Core. */
    public const VERSION = '1.20.0';

    /*
     * Historique :
     * - 1.20.0 : libellé des tâches planifiées par convention de traduction (cron_job_{nom}, tirets remplacés par des
     *   soulignés) dans les traductions du bundle ; à défaut, le nom de la tâche.
     * - 1.19.0 : point d'extension Bundle::dashboardStats() et kintai\Contract\Extension\DashboardStat (cartes de statistiques du
     *   tableau de bord employé).
     * - 1.18.0 : fournisseur d'échanges de shifts — Bundle::swapProviders(), interface kintai\Contract\Provider\SwapProvider et
     *   objet Swap.
     * - 1.17.0 : point d'extension Bundle::shiftFormSections() et kintai\Contract\Extension\ShiftFormSection (champs et
     *   boutons du formulaire de shift) ; événement kintai\Contract\Event\OpenShiftClosed (shift retiré de la bourse).
     * - 1.16.0 : police japonaise des PDF exposée aux bundles, kintai\Contract\Services\PdfCjkFontResolver.
     * - 1.15.0 : envoi d'e-mails exposé aux bundles, kintai\Contract\Services\Mailer.
     * - 1.14.0 : fournisseur de ventes — Bundle::salesProviders(), interface kintai\Contract\Provider\SalesProvider et
     *   objet DailySales ; lecture des ventes par les bundles via kintai\Contract\Services\Sales.
     * - 1.13.0 : point d'extension Bundle::eventListeners() et premier événement du Core,
     *   kintai\Contract\Event\EmployeeCreated (employé créé).
     * - 1.12.0 : point d'extension Bundle::profileSections() et kintai\Contract\Extension\ProfileSection (sections du
     *   formulaire « Mes informations » du profil).
     * - 1.11.0 : fournisseur de pointages — Bundle::attendanceProviders(), interface
     *   kintai\Contract\Provider\AttendanceProvider et objet Attendance.
     * - 1.10.0 : fournisseur d'absences — Bundle::absenceProviders(), interface
     *   kintai\Contract\Provider\AbsenceProvider et objet Absence.
     * - 1.9.0 : fournisseurs (étape 3) — Bundle::pendingRequestsProviders(), interface
     *   kintai\Contract\Provider\PendingRequestsProvider et objet PendingRequest (demandes en attente).
     * - 1.8.0 : point d'extension Bundle::cronJobs() et interface kintai\Contract\Cron\CronJob (tâches planifiées
     *   lancées par /cron/run/{tâche}).
     * - 1.7.0 : point d'extension Bundle::notificationTypes() ; libellés des notifications (notif_{type}) et du
     *   journal d'activité (audit_{action}) trouvés par convention dans les traductions du bundle.
     * - 1.6.0 : point d'extension Bundle::actions() et kintai\Contract\Extension\Action (liens d'action dans les
     *   emplacements des pages du Core).
     * - 1.5.0 : point d'extension Bundle::viewVariables() (variables partagées avec les vues des pages
     *   authentifiées).
     * - 1.4.0 : point d'extension Bundle::dashboardWidgets() et kintai\Contract\Extension\DashboardWidget
     *   (widgets des tableaux de bord gérant et employé).
     * - 1.3.0 : point d'extension Bundle::storeFeatures() et kintai\Contract\Extension\StoreFeature
     *   (fonctionnalités activables par magasin).
     * - 1.2.0 : point d'extension Bundle::permissions() (catégories de permissions du bundle).
     * - 1.1.0 : points d'extension d'interface — Bundle::navigation() et kintai\Contract\Extension\NavItem.
     * - 1.0.0 : surface initiale (src/Contract/surface.php).
     */

    private function __construct() {}

    /** La version fournie par ce Core satisfait-elle la contrainte déclarée par un bundle ? */
    public static function satisfies(string $constraint): bool
    {
        return VersionConstraint::parse($constraint)?->allows(self::VERSION) ?? false;
    }
}
