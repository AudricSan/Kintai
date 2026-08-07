<?php

declare(strict_types=1);

/**
 * Autorisation de l'API v1 (nom de route → règle), consommé par
 * ApiPermissionMiddleware. Trois formats de règle :
 *
 *   'cle.permission'                                     → la clé est exigée
 *   ['perm' => 'cle.permission', 'self' => 'param']       → accès direct si le
 *       paramètre de route/query nommé correspond à l'utilisateur du token
 *       (ses propres ressources), sinon la clé est exigée
 *   ['perm' => 'cle.permission', 'membership' => true]    → accès direct si
 *       l'utilisateur est membre du store ciblé (paramètre/query/body
 *       `store_id`), sinon la clé est exigée. Porte d'entrée volontairement
 *       grossière pour un accès en libre-service (ex. bundle DailyReport) —
 *       la logique fine par ressource (statut, auteur) reste vérifiée par le
 *       contrôleur, jamais dupliquée ici.
 *
 * La portée : si la requête cible un store (paramètre de route ou query/body
 * `store_id`), la permission doit être accordée pour CE store (ou via une
 * affectation globale). Sans store ciblé, une affectation l'accordant
 * n'importe où suffit.
 *
 * Routes absentes de cette map : simple authentification par token (routes
 * auth.* — opérations du porteur du token — et notifications.* et messages.*,
 * strictement limitées à l'utilisateur du token par leur contrôleur).
 *
 * Bundle DailyReport : les routes show/update/destroy/submit/validate portent
 * l'id du RAPPORT dans le paramètre de route {id}, pas l'id du store — le
 * client doit donc passer `store_id` explicitement en query/body pour que la
 * portée 'membership' ci-dessus (et le scoping RBAC) puissent s'appliquer
 * (voir .wiki/API.md). Sans `store_id`, la requête est refusée (aucun store
 * ciblé = portée globale requise). Ces routes sont en outre protégées en
 * profondeur par des vérifications DailyReportPermissionService directement
 * dans ApiDailyReportController (auteur, statut du rapport).
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

    // --- Congés (bundle TimeOff) --------------------------------------------------
    'api.v1.timeoff.index'   => ['perm' => 'timeoff.view',   'self' => 'user_id'],
    'api.v1.timeoff.show'    => 'timeoff.view',
    'api.v1.timeoff.store'   => ['perm' => 'timeoff.create', 'self' => 'user_id'],
    'api.v1.timeoff.update'  => 'timeoff.update',
    'api.v1.timeoff.destroy' => 'timeoff.delete',

    // --- Échanges de shifts (bundle ShiftSwap) --------------------------------------
    'api.v1.swap.index'   => 'swaps.view',
    'api.v1.swap.show'    => 'swaps.view',
    'api.v1.swap.store'   => 'swaps.create',
    'api.v1.swap.update'  => 'swaps.update',
    'api.v1.swap.destroy' => 'swaps.delete',

    // --- Pointage (bundle Timeclock) — clock-in/out : un porteur de token pointe
    // pour lui-même (user_id du corps), pointer pour autrui exige timeclock.update.
    'api.v1.timeclock.clock_in'  => ['perm' => 'timeclock.update', 'self' => 'user_id'],
    'api.v1.timeclock.clock_out' => ['perm' => 'timeclock.update', 'self' => 'user_id'],
    'api.v1.timeclock.index'     => ['perm' => 'timeclock.view',   'self' => 'user_id'],
    'api.v1.timeclock.show'      => 'timeclock.view',
    'api.v1.timeclock.update'    => 'timeclock.update',
    'api.v1.timeclock.destroy'   => 'timeclock.delete',

    // --- Bourse aux shifts (bundle ShiftClaim) — revendiquer pour soi-même est
    // libre, gérer les revendications des autres exige open_shifts.approve.
    'api.v1.shift_claims.index'   => ['perm' => 'open_shifts.view',    'self' => 'user_id'],
    'api.v1.shift_claims.show'    => 'open_shifts.view',
    'api.v1.shift_claims.store'   => ['perm' => 'open_shifts.approve', 'self' => 'user_id'],
    'api.v1.shift_claims.update'  => 'open_shifts.approve',
    'api.v1.shift_claims.destroy' => 'open_shifts.approve',

    // --- Feedbacks (bundle Feedback) — soumettre son propre feedback est libre.
    'api.v1.feedbacks.index'   => 'feedbacks.view',
    'api.v1.feedbacks.show'    => 'feedbacks.view',
    'api.v1.feedbacks.store'   => ['perm' => 'feedbacks.update', 'self' => 'user_id'],
    'api.v1.feedbacks.update'  => 'feedbacks.update',
    'api.v1.feedbacks.destroy' => 'feedbacks.delete',

    // --- Rapports journaliers (bundle DailyReport) — libre-service par store ----
    'api.v1.daily_reports.index'    => ['perm' => 'daily_reports.view',   'membership' => true],
    'api.v1.daily_reports.store'    => ['perm' => 'daily_reports.create', 'membership' => true],
    'api.v1.daily_reports.show'     => ['perm' => 'daily_reports.view',   'membership' => true],
    'api.v1.daily_reports.update'   => ['perm' => 'daily_reports.update', 'membership' => true],
    'api.v1.daily_reports.submit'   => ['perm' => 'daily_reports.submit', 'membership' => true],
    'api.v1.daily_reports.validate' => 'daily_reports.approve',
    'api.v1.daily_reports.destroy'  => 'daily_reports.delete',
];
