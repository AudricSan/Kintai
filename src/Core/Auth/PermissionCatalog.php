<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

/**
 * Catalogue des permissions disponibles, groupées par catégorie.
 *
 * Les permissions sont un vocabulaire de développeur (comme les clés de
 * features de store) : un rôle est associé dynamiquement à un sous-ensemble
 * de ces clés en base (table role_permissions), mais les clés elles-mêmes
 * sont codées ici et s'enrichissent par une modification de ce fichier.
 */
final class PermissionCatalog
{
    public const CATEGORIES = [
        'employees'     => ['view', 'create', 'update', 'delete'],
        'shifts'        => ['view', 'create', 'update', 'delete', 'import', 'export', 'validate'],
        'stores'        => ['view', 'create', 'update', 'delete'],
        'payroll'       => ['view', 'generate', 'export'],
        'documents'     => ['view', 'create', 'update', 'delete'],
        'settings'      => ['update'],
        'timeoff'       => ['view', 'create', 'update', 'approve', 'delete'],
        'swaps'         => ['view', 'create', 'update', 'approve', 'delete'],
        'timeclock'     => ['view', 'update', 'delete'],
        'open_shifts'   => ['view', 'publish', 'approve'],
        'feedbacks'     => ['view', 'update', 'delete'],
        'daily_reports' => ['view', 'create', 'update', 'submit', 'approve', 'delete'],
    ];

    /**
     * Catégories de permissions portées par un bundle optionnel (catégorie →
     * slug du bundle). Une catégorie absente d'ici est du Core, toujours
     * proposée. L'UI des rôles masque les catégories dont le bundle est
     * désactivé (leurs routes n'existent alors pas) ; les clés restent
     * valides en base et réapparaissent si le bundle est réactivé.
     */
    public const CATEGORY_BUNDLES = [
        'timeoff'       => 'timeoff',
        'swaps'         => 'shift-swap',
        'timeclock'     => 'timeclock',
        'open_shifts'   => 'shift-claim',
        'feedbacks'     => 'feedback',
        'daily_reports' => 'daily-report',
    ];

    /**
     * Ensemble de permissions par défaut du rôle "Manager" seedé à l'installation.
     * Reflète les capacités de gestion de store actuelles (hors réglages
     * d'instance, réservés à Owner).
     */
    public const MANAGER_DEFAULTS = [
        'employees.view', 'employees.create', 'employees.update', 'employees.delete',
        'shifts.view', 'shifts.create', 'shifts.update', 'shifts.delete',
        'shifts.import', 'shifts.export', 'shifts.validate',
        'stores.view', 'stores.update',
        'payroll.view', 'payroll.generate', 'payroll.export',
        'documents.view', 'documents.create', 'documents.update', 'documents.delete',
        'timeoff.view', 'timeoff.create', 'timeoff.update', 'timeoff.approve', 'timeoff.delete',
        'swaps.view', 'swaps.create', 'swaps.update', 'swaps.approve', 'swaps.delete',
        'timeclock.view', 'timeclock.update', 'timeclock.delete',
        'open_shifts.view', 'open_shifts.publish', 'open_shifts.approve',
        'feedbacks.view', 'feedbacks.update', 'feedbacks.delete',
        'daily_reports.view', 'daily_reports.create', 'daily_reports.update',
        'daily_reports.submit', 'daily_reports.approve', 'daily_reports.delete',
    ];

    /** @return string[] Toutes les clés de permission ("categorie.action"), aplaties. */
    public static function all(): array
    {
        $keys = [];
        foreach (self::CATEGORIES as $category => $actions) {
            foreach ($actions as $action) {
                $keys[] = $category . '.' . $action;
            }
        }
        return $keys;
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::all(), true);
    }
}
