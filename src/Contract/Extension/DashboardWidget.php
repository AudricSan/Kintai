<?php

declare(strict_types=1);

namespace kintai\Contract\Extension;

/**
 * Widget de tableau de bord fourni par un bundle (point d'extension `Bundle::dashboardWidgets()`, contrat 1.4).
 *
 * Le Core le propose dans le panneau « Personnaliser » du tableau de bord visé (gérant ou employé) et l'affiche après
 * ses propres widgets quand l'utilisateur l'a activé (tous le sont par défaut), qu'il a la permission `permission`
 * et que la fonctionnalité `storeFeature` est active. Le Core appelle `data` puis rend la vue `view` (vue namespacée
 * du bundle, ex. « notebook::widgets.dashboard ») avec le tableau retourné ; une exception dans l'un ou l'autre
 * retire le widget et est journalisée, sans casser la page.
 */
final class DashboardWidget
{
    public const MANAGER = 'manager';
    public const EMPLOYEE = 'employee';

    /**
     * @param string        $key          Identifiant unique sur ce tableau de bord (préférences de l'utilisateur)
     * @param string        $audience     MANAGER (tableau de bord gérant) ou EMPLOYEE (tableau de bord employé)
     * @param string        $label        Clé de traduction du nom affiché dans le panneau « Personnaliser »
     * @param string        $view         Vue rendue (namespacée, ex. « notebook::widgets.dashboard »)
     * @param \Closure|null $data         fn(array $contexte): array — variables de la vue. Le contexte contient user
     *                                    (utilisateur connecté), store_ids (magasins visibles : ceux qu'il gère, ou les
     *                                    siens côté employé), managed_store_ids (null = tous, côté gérant), stores_map
     *                                    (id => nom) et today (Y-m-d)
     * @param string|null   $permission   Permission requise (ex. « notebook.view »)
     * @param string|null   $storeFeature Fonctionnalité par magasin requise (ex. « notes »)
     * @param int           $order        Ordre parmi les widgets de bundles
     */
    public function __construct(
        public readonly string $key,
        public readonly string $audience,
        public readonly string $label,
        public readonly string $view,
        public readonly ?\Closure $data = null,
        public readonly ?string $permission = null,
        public readonly ?string $storeFeature = null,
        public readonly int $order = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $key) !== 1) {
            throw new \InvalidArgumentException("DashboardWidget : clé invalide « {$key} » (minuscules, chiffres, _).");
        }
        if (!in_array($audience, [self::MANAGER, self::EMPLOYEE], true)) {
            throw new \InvalidArgumentException("DashboardWidget « {$key} » : public inconnu « {$audience} ».");
        }
    }
}
