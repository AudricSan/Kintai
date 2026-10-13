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
    public const VERSION = '1.6.0';

    /*
     * Historique :
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
