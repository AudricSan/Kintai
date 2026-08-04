<?php

declare(strict_types=1);

/**
 * Permission requise par route (nom de route → clé de PermissionCatalog).
 *
 * Consommé par PermissionMiddleware : pour un non-Owner, la route n'est
 * accessible que si l'un de ses rôles accorde la clé, et la liste des stores
 * visibles (managed_store_ids) est resserrée aux stores où cette clé précise
 * est accordée. Une route absente de cette map reste soumise au seul filtre
 * grossier d'AdminMiddleware (être gestionnaire d'au moins un store).
 *
 * Les pages réservées à l'Owner (owner-settings, langues, bundles, rôles,
 * backup, update, mail-test) ne figurent pas ici : elles restent protégées
 * par leur requireOwner() explicite.
 */

return [

    // --- Employés -----------------------------------------------------------
    'admin.users'                     => 'employees.view',
    'admin.users.export_pdf'          => 'employees.view',
    'admin.users.export_json'         => 'employees.view',
    'admin.users.create'              => 'employees.create',
    'admin.users.store'               => 'employees.create',
    'admin.users.quick_create'        => 'employees.create',
    'admin.users.check_employee_code' => 'employees.view',
    'admin.users.check_email'         => 'employees.view',
    'admin.users.edit'                => 'employees.view',
    'admin.users.update'              => 'employees.update',
    'admin.users.delete'              => 'employees.delete',
    'admin.users.reset_password'      => 'employees.update',
    'admin.users.rates.set'           => 'employees.update',
    'admin.users.rates.delete'        => 'employees.update',

    // --- Stores ---------------------------------------------------------------
    'admin.stores'                    => 'stores.view',
    'admin.stores.create'             => 'stores.create',
    'admin.stores.store'              => 'stores.create',
    'admin.stores.edit'               => 'stores.view',
    'admin.stores.update'             => 'stores.update',
    'admin.stores.delete'             => 'stores.delete',
    'admin.stores.members.add'        => 'employees.update',
    'admin.stores.members.role'       => 'employees.update',
    'admin.stores.members.delete'     => 'employees.update',
    'admin.stores.members.deductions'      => 'payroll.view',
    'admin.stores.members.deductions.save' => 'payroll.generate',

    // --- Statistiques & paie ---------------------------------------------------
    'admin.stores.stats'              => 'payroll.view',
    'admin.stores.stats_export'       => 'payroll.export',
    'admin.stores.profitability'      => 'payroll.view',
    'admin.stores.employee_report'    => 'payroll.view',
    'admin.stores.employee_stats'     => 'payroll.view',

    // --- Types de shifts ---------------------------------------------------------
    'admin.shift_types'               => 'shifts.view',
    'admin.shift_types.create'        => 'shifts.update',
    'admin.shift_types.store'         => 'shifts.update',
    'admin.shift_types.edit'          => 'shifts.update',
    'admin.shift_types.update'        => 'shifts.update',
    'admin.shift_types.delete'        => 'shifts.update',

    // --- Shifts -------------------------------------------------------------------
    'admin.shifts'                    => 'shifts.view',
    'admin.shifts.calendar'           => 'shifts.view',
    'admin.shifts.timeline'           => 'shifts.view',
    'admin.shifts.timeline.print'     => 'shifts.view',
    'admin.shifts.conflicts'          => 'shifts.view',
    'admin.shifts.resolve_newer'      => 'shifts.update',
    'admin.shifts.import'             => 'shifts.import',
    'admin.shifts.import.process'     => 'shifts.import',
    'admin.shifts.import.confirm'     => 'shifts.import',
    'admin.shifts.create'             => 'shifts.create',
    'admin.shifts.store'              => 'shifts.create',
    'admin.shifts.quick'              => 'shifts.create',
    'admin.shifts.bulk_delete'        => 'shifts.delete',
    'admin.shifts.edit'               => 'shifts.view',
    'admin.shifts.update'             => 'shifts.update',
    'admin.shifts.delete'             => 'shifts.delete',
    'admin.shifts.move'               => 'shifts.update',

    // --- Congés (bundle TimeOff) ----------------------------------------------
    'admin.timeoff'                   => 'timeoff.view',
    'admin.timeoff.create'            => 'timeoff.create',
    'admin.timeoff.store'             => 'timeoff.create',
    'admin.timeoff.approve'           => 'timeoff.approve',
    'admin.timeoff.refuse'            => 'timeoff.approve',
    'admin.timeoff.delete'            => 'timeoff.delete',

    // --- Échanges de shifts (bundle ShiftSwap) --------------------------------
    'admin.swap_requests'             => 'swaps.view',
    'admin.swap_requests.create'      => 'swaps.create',
    'admin.swap_requests.store'       => 'swaps.create',
    'admin.swap.approve'              => 'swaps.approve',
    'admin.swap.refuse'               => 'swaps.approve',
    'admin.swap.delete'               => 'swaps.delete',

    // --- Pointage (bundle Timeclock) -------------------------------------------
    'admin.timeclocks'                => 'timeclock.view',
    'admin.timeclocks.edit'           => 'timeclock.update',
    'admin.timeclocks.delete'         => 'timeclock.delete',

    // --- Bourse aux shifts (bundle ShiftClaim) ---------------------------------
    'admin.open_shifts'               => 'open_shifts.view',
    'admin.open_shifts.select'        => 'open_shifts.publish',
    'admin.shifts.publish'            => 'open_shifts.publish',
    'admin.shifts.unpublish'          => 'open_shifts.publish',
    'admin.open_shifts.approve'       => 'open_shifts.approve',
    'admin.open_shifts.reject'        => 'open_shifts.approve',

    // --- Feedbacks (bundle Feedback) --------------------------------------------
    'admin.feedbacks'                 => 'feedbacks.view',
    'admin.feedbacks.delete'          => 'feedbacks.delete',

    // --- Rapports d'embauche (bundle HiringReport) → documents.* ----------------
    'admin.reports.hiring'                 => 'documents.view',
    'admin.stores.hiring_reports'          => 'documents.view',
    'admin.stores.hiring_reports.create'   => 'documents.create',
    'admin.stores.hiring_reports.store'    => 'documents.create',
    'admin.stores.hiring_reports.show'     => 'documents.view',
    'admin.stores.hiring_reports.edit'     => 'documents.view',
    'admin.stores.hiring_reports.update'   => 'documents.update',
    'admin.stores.hiring_reports.delete'   => 'documents.delete',
    'admin.stores.hiring_reports.pdf'      => 'documents.view',

    // --- Rapports de démission (bundle ResignationReport) → documents.* ---------
    'admin.reports.resignation'                  => 'documents.view',
    'admin.reports.resignation.export_json'      => 'documents.view',
    'admin.reports.resignation.export_pdf'       => 'documents.view',
    'admin.stores.resignation_reports'           => 'documents.view',
    'admin.stores.resignation_reports.create'    => 'documents.create',
    'admin.stores.resignation_reports.store'     => 'documents.create',
    'admin.stores.resignation_reports.show'      => 'documents.view',
    'admin.stores.resignation_reports.edit'      => 'documents.view',
    'admin.stores.resignation_reports.update'    => 'documents.update',
    'admin.stores.resignation_reports.delete'    => 'documents.delete',
    'admin.stores.resignation_reports.pdf'       => 'documents.view',
    'admin.stores.resignation_reports.reactivate' => 'employees.update',

    // --- Rapports de salaire (bundle SalaryReport) → payroll.* -------------------
    'admin.reports.salary'                 => 'payroll.view',
    'admin.reports.salary.export_json'     => 'payroll.view',
    'admin.reports.salary.export_pdf'      => 'payroll.view',
    'admin.stores.salary_reports'          => 'payroll.view',
    'admin.stores.salary_reports.create'   => 'payroll.generate',
    'admin.stores.salary_reports.store'    => 'payroll.generate',
    'admin.stores.salary_reports.show'     => 'payroll.view',
    'admin.stores.salary_reports.edit'     => 'payroll.view',
    'admin.stores.salary_reports.update'   => 'payroll.generate',
    'admin.stores.salary_reports.delete'   => 'payroll.generate',
    'admin.stores.salary_reports.pdf'      => 'payroll.export',

    // --- Journal d'activité --------------------------------------------------------------
    'admin.activity'                  => 'stores.view',

    // --- Photos de magasin (bundle StorePhoto) → documents.* ---------------------
    'admin.photos.index'              => 'documents.view',
    'admin.photos.show'               => 'documents.view',
    'admin.photos.create'             => 'documents.create',
    'admin.photos.store'              => 'documents.create',
    'admin.photos.upload_file'        => 'documents.create',
    'admin.photos.delete'             => 'documents.delete',
    'admin.photos.settings'           => 'documents.update',
    'admin.photos.settings.save'      => 'documents.update',
];
