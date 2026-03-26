<?php

declare(strict_types=1);

use kintai\UI\Controller\Web\AuthController;
use kintai\UI\Controller\Web\EmployeeController;
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\Controller\Web\AdminController;
use kintai\UI\Controller\Web\AuditLogController;
use kintai\UI\Controller\Web\FeedbackController;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\UI\Controller\Api\TestApiController;
use kintai\UI\Controller\Api\UserController;
use kintai\UI\Controller\Api\StoreController;
use kintai\UI\Controller\Api\StoreUserController;
use kintai\UI\Controller\Api\ShiftTypeController;
use kintai\UI\Controller\Api\ShiftController;
use kintai\UI\Controller\Api\AvailabilityController;
use kintai\UI\Controller\Api\TimeoffRequestController;
use kintai\UI\Controller\Api\ShiftSwapRequestController;

/** @var \kintai\Core\Router $router */

// --- Routes publiques (auth) ---
$router->get('/login',  [AuthController::class, 'showLogin'], name: 'auth.login');
$router->post('/login', [AuthController::class, 'login'],     name: 'auth.login.post');
$router->post('/logout',      [AuthController::class, 'logout'],     name: 'auth.logout');
$router->post('/switch-view',   [AuthController::class, 'switchView'],   middleware: [AuthMiddleware::class], name: 'switch.view');
$router->post('/switch-device', [AuthController::class, 'switchDevice'], middleware: [AuthMiddleware::class], name: 'switch.device');
$router->get('/lang/{locale}', [AuthController::class, 'switchLanguage'], name: 'lang.switch');

// --- Routes web protégées ---
$router->get('/', [HomeController::class, 'index'], middleware: [AuthMiddleware::class], name: 'home');
$router->get('/profile',  [AuthController::class, 'showProfile'],   middleware: [AuthMiddleware::class], name: 'profile');
$router->post('/profile', [AuthController::class, 'updateProfile'], middleware: [AuthMiddleware::class], name: 'profile.post');

// --- Routes employee ---
$router->get('/employee',         [EmployeeController::class, 'dashboard'],    middleware: [AuthMiddleware::class], name: 'employee.dashboard');
$router->get('/employee/shifts',          [EmployeeController::class, 'shifts'],         middleware: [AuthMiddleware::class], name: 'employee.shifts');
$router->get('/employee/shifts/calendar', [EmployeeController::class, 'shiftsCalendar'], middleware: [AuthMiddleware::class], name: 'employee.shifts.calendar');
$router->get('/employee/shifts/day',      [EmployeeController::class, 'shiftDay'],       middleware: [AuthMiddleware::class], name: 'employee.shifts.day');
$router->get('/employee/shifts/week',     [EmployeeController::class, 'shiftsWeek'],     middleware: [AuthMiddleware::class], name: 'employee.shifts.week');

// Congés
$router->get('/employee/timeoff',               [EmployeeController::class, 'timeoff'],      middleware: [AuthMiddleware::class], name: 'employee.timeoff');
$router->post('/employee/timeoff',              [EmployeeController::class, 'storeTimeoff'], middleware: [AuthMiddleware::class], name: 'employee.timeoff.store');
$router->post('/employee/timeoff/{id}/cancel',  [EmployeeController::class, 'cancelTimeoff'], middleware: [AuthMiddleware::class], name: 'employee.timeoff.cancel');

// Feedback employé
$router->post('/employee/feedback',             [FeedbackController::class, 'submit'],     middleware: [AuthMiddleware::class], name: 'employee.feedback.submit');
$router->get('/employee/feedback/past-shifts',  [FeedbackController::class, 'pastShifts'], middleware: [AuthMiddleware::class], name: 'employee.feedback.past_shifts');

// Échanges de shifts
$router->get('/employee/swaps',                 [EmployeeController::class, 'swaps'],       middleware: [AuthMiddleware::class], name: 'employee.swaps');
$router->get('/employee/swaps/create',          [EmployeeController::class, 'createSwap'],  middleware: [AuthMiddleware::class], name: 'employee.swaps.create');
$router->post('/employee/swaps/create',         [EmployeeController::class, 'storeSwap'],   middleware: [AuthMiddleware::class], name: 'employee.swaps.store');
$router->post('/employee/swaps/{id}/accept',    [EmployeeController::class, 'acceptSwap'],  middleware: [AuthMiddleware::class], name: 'employee.swaps.accept');
$router->post('/employee/swaps/{id}/refuse',    [EmployeeController::class, 'refuseSwap'],  middleware: [AuthMiddleware::class], name: 'employee.swaps.refuse');
$router->post('/employee/swaps/{id}/cancel',    [EmployeeController::class, 'cancelSwap'],  middleware: [AuthMiddleware::class], name: 'employee.swaps.cancel');

// --- Routes admin (web) ---
$router->group('/admin', function ($r) {
    // Listes
    $r->get('/users',         [AdminController::class, 'users'],        name: 'admin.users');
    $r->get('/stores',        [AdminController::class, 'stores'],       name: 'admin.stores');
    $r->get('/shifts',        [AdminController::class, 'shifts'],       name: 'admin.shifts');
    $r->get('/shift-types',   [AdminController::class, 'shiftTypes'],   name: 'admin.shift_types');
    $r->get('/timeoff',       [AdminController::class, 'timeoff'],      name: 'admin.timeoff');
    $r->get('/swap-requests', [AdminController::class, 'swapRequests'], name: 'admin.swap_requests');

    // Shifts — Calendrier mensuel
    $r->get('/shifts/calendar',   [AdminController::class, 'shiftsCalendar'], name: 'admin.shifts.calendar');
    // Shifts — CRUD (/create et /import avant /{id} pour éviter le conflit de paramètre)
    $r->get('/shifts/create',       [AdminController::class, 'createShift'],  name: 'admin.shifts.create');
    $r->post('/shifts/create',      [AdminController::class, 'storeShift'],   name: 'admin.shifts.store');

    // Shifts — Import Excel
    $r->get('/shifts/import',          [AdminController::class, 'importShifts'],  name: 'admin.shifts.import');
    $r->post('/shifts/import',         [AdminController::class, 'processImport'], name: 'admin.shifts.import.process');
    $r->post('/shifts/import/confirm', [AdminController::class, 'confirmImport'], name: 'admin.shifts.import.confirm');
    // Shifts — Vue timeline
    $r->get('/shifts/timeline',        [AdminController::class, 'shiftsTimeline'],      name: 'admin.shifts.timeline');
    // Shifts — Document d'impression du planning
    $r->get('/shifts/timeline/print',  [AdminController::class, 'shiftsTimelinePrint'], name: 'admin.shifts.timeline.print');
    // Shifts — Drag & Drop (routes littérales avant {id} pour éviter le conflit)
    $r->post('/shifts/quick',        [AdminController::class, 'quickShift'],       name: 'admin.shifts.quick');
    // Shifts — Suppression en masse
    $r->post('/shifts/bulk-delete',  [AdminController::class, 'bulkDeleteShifts'], name: 'admin.shifts.bulk_delete');
    // Shifts — Vue des conflits de chevauchement
    $r->get('/shifts/conflicts',            [AdminController::class, 'shiftConflicts'],     name: 'admin.shifts.conflicts');
    $r->post('/shifts/conflicts/resolve-newer', [AdminController::class, 'resolveNewerConflict'], name: 'admin.shifts.resolve_newer');
    $r->get('/shifts/{id}/edit',    [AdminController::class, 'editShift'],    name: 'admin.shifts.edit');
    $r->post('/shifts/{id}/edit',   [AdminController::class, 'updateShift'],  name: 'admin.shifts.update');
    $r->post('/shifts/{id}/delete', [AdminController::class, 'deleteShift'],  name: 'admin.shifts.delete');
    $r->post('/shifts/{id}/move',   [AdminController::class, 'moveShift'],    name: 'admin.shifts.move');

    // Timeoff — actions statut
    $r->post('/timeoff/{id}/approve', [AdminController::class, 'approveTimeoff'], name: 'admin.timeoff.approve');
    $r->post('/timeoff/{id}/refuse',  [AdminController::class, 'refuseTimeoff'],  name: 'admin.timeoff.refuse');

    // Swap requests — actions statut
    $r->post('/swap-requests/{id}/approve', [AdminController::class, 'approveSwap'], name: 'admin.swap.approve');
    $r->post('/swap-requests/{id}/refuse',  [AdminController::class, 'refuseSwap'],  name: 'admin.swap.refuse');

    // Users — CRUD
    $r->get('/users/create',          [AdminController::class, 'createUser'],       name: 'admin.users.create');
    $r->post('/users/create',         [AdminController::class, 'storeUser'],        name: 'admin.users.store');
    $r->post('/users/quick-create',         [AdminController::class, 'quickCreateUser'],     name: 'admin.users.quick_create');
    $r->get('/users/check-employee-code',   [AdminController::class, 'checkEmployeeCode'],   name: 'admin.users.check_employee_code');
    $r->get('/users/{id}/edit',    [AdminController::class, 'editUser'],    name: 'admin.users.edit');
    $r->post('/users/{id}/edit',   [AdminController::class, 'updateUser'],  name: 'admin.users.update');
    $r->post('/users/{id}/delete', [AdminController::class, 'deleteUser'],  name: 'admin.users.delete');

    // Taux horaires par employé/type de shift
    $r->post('/users/{id}/rates',            [AdminController::class, 'setUserRate'],    name: 'admin.users.rates.set');
    $r->post('/users/{id}/rates/{rid}/delete', [AdminController::class, 'deleteUserRate'], name: 'admin.users.rates.delete');

    // Stores — CRUD
    $r->get('/stores/create',        [AdminController::class, 'createStore'],  name: 'admin.stores.create');
    $r->post('/stores/create',       [AdminController::class, 'storeStore'],   name: 'admin.stores.store');
    $r->get('/stores/{id}/edit',     [AdminController::class, 'editStore'],    name: 'admin.stores.edit');
    $r->post('/stores/{id}/edit',    [AdminController::class, 'updateStore'],  name: 'admin.stores.update');
    $r->post('/stores/{id}/delete',  [AdminController::class, 'deleteStore'],  name: 'admin.stores.delete');

    // Statistiques d'un store
    $r->get('/stores/{id}/stats',           [AdminController::class, 'storeStats'],    name: 'admin.stores.stats');

    // Rapport de performance des employés d'un store
    $r->get('/stores/{id}/employee-report',                      [AdminController::class, 'employeeReport'],    name: 'admin.stores.employee_report');
    // Statistiques individuelles d'un employé
    $r->get('/stores/{id}/employee-report/{uid}/stats',          [AdminController::class, 'employeeStats'],     name: 'admin.stores.employee_stats');
    // Fiche de paie individuelle (document HTML imprimable)
    $r->get('/stores/{id}/employee-report/{uid}/payslip',        [AdminController::class, 'employeePayslip'],   name: 'admin.stores.employee_payslip');
    // Fiche de paie individuelle (export PDF via mPDF)
    $r->get('/stores/{id}/employee-report/{uid}/payslip/pdf',    [AdminController::class, 'employeePayslipPdf'], name: 'admin.stores.employee_payslip_pdf');

    // Cotisations sociales par employé/store
    $r->get('/stores/{id}/members/{mid}/deductions',  [AdminController::class, 'editMemberDeductions'],   name: 'admin.stores.members.deductions');
    $r->post('/stores/{id}/members/{mid}/deductions', [AdminController::class, 'saveMemberDeductions'],   name: 'admin.stores.members.deductions.save');

    // Membres d'un store (rôles par store)
    $r->post('/stores/{id}/members',                [AdminController::class, 'addMember'],        name: 'admin.stores.members.add');
    $r->post('/stores/{id}/members/{mid}/role',     [AdminController::class, 'updateMemberRole'], name: 'admin.stores.members.role');
    $r->post('/stores/{id}/members/{mid}/delete',   [AdminController::class, 'removeMember'],     name: 'admin.stores.members.delete');

    // Shift types — CRUD
    $r->get('/shift-types/create',       [AdminController::class, 'createShiftType'],  name: 'admin.shift_types.create');
    $r->post('/shift-types/create',      [AdminController::class, 'storeShiftType'],   name: 'admin.shift_types.store');
    $r->get('/shift-types/{id}/edit',    [AdminController::class, 'editShiftType'],    name: 'admin.shift_types.edit');
    $r->post('/shift-types/{id}/edit',   [AdminController::class, 'updateShiftType'],  name: 'admin.shift_types.update');
    $r->post('/shift-types/{id}/delete', [AdminController::class, 'deleteShiftType'],  name: 'admin.shift_types.delete');

    // Journal d'activité (admin global uniquement)
    $r->get('/audit-log', [AuditLogController::class, 'index'], name: 'admin.audit_log');

    // Feedbacks employés
    $r->get('/feedbacks',              [FeedbackController::class, 'index'],  name: 'admin.feedbacks');
    $r->post('/feedbacks/{id}/delete', [FeedbackController::class, 'delete'], name: 'admin.feedbacks.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class]);

// --- Routes API ---
$router->group('/api', function ($r) {

    // Diagnostic
    $r->get('/test', [TestApiController::class, 'index'], name: 'api.test');

    // Users
    $r->get('/users',       [UserController::class, 'index'],   name: 'api.users.index');
    $r->post('/users',      [UserController::class, 'store'],   name: 'api.users.store');
    $r->get('/users/{id}',  [UserController::class, 'show'],    name: 'api.users.show');
    $r->put('/users/{id}',  [UserController::class, 'update'],  name: 'api.users.update');
    $r->delete('/users/{id}', [UserController::class, 'destroy'], name: 'api.users.destroy');

    // Stores
    $r->get('/stores',        [StoreController::class, 'index'],   name: 'api.stores.index');
    $r->post('/stores',       [StoreController::class, 'store'],   name: 'api.stores.store');
    $r->get('/stores/{id}',   [StoreController::class, 'show'],    name: 'api.stores.show');
    $r->put('/stores/{id}',   [StoreController::class, 'update'],  name: 'api.stores.update');
    $r->delete('/stores/{id}', [StoreController::class, 'destroy'], name: 'api.stores.destroy');

    // Membres d'un store (store_user)
    $r->get('/stores/{store_id}/members',       [StoreUserController::class, 'index'], name: 'api.store_users.index');
    $r->post('/stores/{store_id}/members',      [StoreUserController::class, 'store'], name: 'api.store_users.store');
    $r->delete('/stores/{store_id}/members/{id}', [StoreUserController::class, 'destroy'], name: 'api.store_users.destroy');

    // Types de shifts
    $r->get('/shift-types',       [ShiftTypeController::class, 'index'],   name: 'api.shift_types.index');
    $r->post('/shift-types',      [ShiftTypeController::class, 'store'],   name: 'api.shift_types.store');
    $r->get('/shift-types/{id}',  [ShiftTypeController::class, 'show'],    name: 'api.shift_types.show');
    $r->put('/shift-types/{id}',  [ShiftTypeController::class, 'update'],  name: 'api.shift_types.update');
    $r->delete('/shift-types/{id}', [ShiftTypeController::class, 'destroy'], name: 'api.shift_types.destroy');

    // Shifts
    $r->get('/shifts',        [ShiftController::class, 'index'],   name: 'api.shifts.index');
    $r->post('/shifts',       [ShiftController::class, 'store'],   name: 'api.shifts.store');
    $r->get('/shifts/{id}',   [ShiftController::class, 'show'],    name: 'api.shifts.show');
    $r->put('/shifts/{id}',   [ShiftController::class, 'update'],  name: 'api.shifts.update');
    $r->delete('/shifts/{id}', [ShiftController::class, 'destroy'], name: 'api.shifts.destroy');

    // Disponibilités
    $r->get('/availabilities',       [AvailabilityController::class, 'index'],   name: 'api.availabilities.index');
    $r->post('/availabilities',      [AvailabilityController::class, 'store'],   name: 'api.availabilities.store');
    $r->get('/availabilities/{id}',  [AvailabilityController::class, 'show'],    name: 'api.availabilities.show');
    $r->put('/availabilities/{id}',  [AvailabilityController::class, 'update'],  name: 'api.availabilities.update');
    $r->delete('/availabilities/{id}', [AvailabilityController::class, 'destroy'], name: 'api.availabilities.destroy');

    // Demandes de congé
    $r->get('/timeoff-requests',       [TimeoffRequestController::class, 'index'],   name: 'api.timeoff.index');
    $r->post('/timeoff-requests',      [TimeoffRequestController::class, 'store'],   name: 'api.timeoff.store');
    $r->get('/timeoff-requests/{id}',  [TimeoffRequestController::class, 'show'],    name: 'api.timeoff.show');
    $r->put('/timeoff-requests/{id}',  [TimeoffRequestController::class, 'update'],  name: 'api.timeoff.update');
    $r->delete('/timeoff-requests/{id}', [TimeoffRequestController::class, 'destroy'], name: 'api.timeoff.destroy');

    // Demandes d'échange de shifts
    $r->get('/shift-swap-requests',       [ShiftSwapRequestController::class, 'index'],   name: 'api.swap.index');
    $r->post('/shift-swap-requests',      [ShiftSwapRequestController::class, 'store'],   name: 'api.swap.store');
    $r->get('/shift-swap-requests/{id}',  [ShiftSwapRequestController::class, 'show'],    name: 'api.swap.show');
    $r->put('/shift-swap-requests/{id}',  [ShiftSwapRequestController::class, 'update'],  name: 'api.swap.update');
    $r->delete('/shift-swap-requests/{id}', [ShiftSwapRequestController::class, 'destroy'], name: 'api.swap.destroy');
});
