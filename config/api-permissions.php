<?php

declare(strict_types=1);

/**
 * Autorisation de l'API v1 (nom de route → règle), consommé par
 * ApiPermissionMiddleware. Deux formats de règle :
 *
 *   'cle.permission'                               → la clé est exigée
 *   ['perm' => 'cle.permission', 'self' => 'param'] → accès direct si le
 *       paramètre de route/query nommé correspond à l'utilisateur du token
 *       (ses propres ressources), sinon la clé est exigée
 *
 * La portée : si la requête cible un store (paramètre de route ou query/body
 * `store_id`), la permission doit être accordée pour CE store (ou via une
 * affectation globale). Sans store ciblé, une affectation l'accordant
 * n'importe où suffit.
 *
 * Routes absentes de cette map : simple authentification par token (routes
 * auth.* — opérations du porteur du token — et notifications.*, désormais
 * strictement limitées à l'utilisateur du token par le contrôleur).
 * Les endpoints des bundles (timeoff, swaps, timeclock, shift claims,
 * messages, feedbacks, daily reports) seront mappés avec leurs bundles.
 */

return [

    // --- Utilisateurs -----------------------------------------------------------
    'api.v1.users.index'   => 'employees.view',
    'api.v1.users.store'   => 'employees.create',
    'api.v1.users.show'    => ['perm' => 'employees.view', 'self' => 'id'],
    'api.v1.users.update'  => 'employees.update',
    'api.v1.users.destroy' => 'employees.delete',

    // --- Préférences / taux / jetons iCal d'un utilisateur -------------------------
    'api.v1.users.dashboard_prefs.get'  => ['perm' => 'employees.view',   'self' => 'user_id'],
    'api.v1.users.dashboard_prefs.save' => ['perm' => 'employees.update', 'self' => 'user_id'],
    'api.v1.users.nav_prefs.get'        => ['perm' => 'employees.view',   'self' => 'user_id'],
    'api.v1.users.nav_prefs.save'       => ['perm' => 'employees.update', 'self' => 'user_id'],
    'api.v1.users.rates.index'          => ['perm' => 'payroll.view',     'self' => 'user_id'],
    'api.v1.users.rates.show'           => ['perm' => 'payroll.view',     'self' => 'user_id'],
    'api.v1.users.rates.store'          => 'employees.update',
    'api.v1.users.rates.update'         => 'employees.update',
    'api.v1.users.rates.destroy'        => 'employees.update',
    'api.v1.users.ical_tokens.index'      => ['perm' => 'employees.view',   'self' => 'user_id'],
    'api.v1.users.ical_tokens.show'       => ['perm' => 'employees.view',   'self' => 'user_id'],
    'api.v1.users.ical_tokens.store'      => ['perm' => 'employees.update', 'self' => 'user_id'],
    'api.v1.users.ical_tokens.regenerate' => ['perm' => 'employees.update', 'self' => 'user_id'],
    'api.v1.users.ical_tokens.destroy'    => ['perm' => 'employees.update', 'self' => 'user_id'],

    // --- Stores -----------------------------------------------------------------
    'api.v1.stores.index'   => 'stores.view',
    'api.v1.stores.store'   => 'stores.create',
    'api.v1.stores.show'    => 'stores.view',
    'api.v1.stores.update'  => 'stores.update',
    'api.v1.stores.destroy' => 'stores.delete',

    // --- Membres de store ----------------------------------------------------------
    'api.v1.store_members.index'   => 'employees.view',
    'api.v1.store_members.store'   => 'employees.update',
    'api.v1.store_members.show'    => 'employees.view',
    'api.v1.store_members.update'  => 'employees.update',
    'api.v1.store_members.destroy' => 'employees.update',

    // --- Types de shifts --------------------------------------------------------------
    'api.v1.shift_types.index'   => 'shifts.view',
    'api.v1.shift_types.store'   => 'shifts.update',
    'api.v1.shift_types.show'    => 'shifts.view',
    'api.v1.shift_types.update'  => 'shifts.update',
    'api.v1.shift_types.destroy' => 'shifts.update',

    // --- Shifts --------------------------------------------------------------------------
    'api.v1.shifts.index'   => 'shifts.view',
    'api.v1.shifts.store'   => 'shifts.create',
    'api.v1.shifts.show'    => 'shifts.view',
    'api.v1.shifts.update'  => 'shifts.update',
    'api.v1.shifts.destroy' => 'shifts.delete',

    // --- Disponibilités ---------------------------------------------------------------
    'api.v1.availabilities.index'   => 'shifts.view',
    'api.v1.availabilities.store'   => 'shifts.update',
    'api.v1.availabilities.show'    => 'shifts.view',
    'api.v1.availabilities.update'  => 'shifts.update',
    'api.v1.availabilities.destroy' => 'shifts.update',

    // --- Journal d'activité --------------------------------------------------------------
    'api.v1.activity.index' => 'stores.view',
];
