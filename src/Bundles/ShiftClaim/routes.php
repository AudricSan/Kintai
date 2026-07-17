<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\ShiftClaim\Controllers\Web\EmployeeShiftClaimController;
use kintai\Bundles\ShiftClaim\Controllers\Web\AdminShiftClaimController;
use kintai\Bundles\ShiftClaim\Controllers\Api\ShiftClaimController as ApiShiftClaimController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// ShiftClaim — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/open-shifts',                [EmployeeShiftClaimController::class, 'openShifts'],         name: 'employee.open_shifts');
    $r->post('/open-shifts/{id}/claim',    [EmployeeShiftClaimController::class, 'claimShift'],          name: 'employee.open_shifts.claim');
    $r->post('/open-shifts/{id}/withdraw', [EmployeeShiftClaimController::class, 'withdrawShiftClaim'],  name: 'employee.open_shifts.withdraw');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// ShiftClaim — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/open-shifts',               [AdminShiftClaimController::class, 'openShifts'],           name: 'admin.open_shifts');
    $r->get('/open-shifts/publish',       [AdminShiftClaimController::class, 'selectShiftToPublish'], name: 'admin.open_shifts.select');
    $r->post('/shifts/{id}/publish',      [AdminShiftClaimController::class, 'publishShift'],         name: 'admin.shifts.publish');
    $r->post('/shifts/{id}/unpublish',    [AdminShiftClaimController::class, 'unpublishShift'],       name: 'admin.shifts.unpublish');
    $r->post('/open-shifts/{id}/approve', [AdminShiftClaimController::class, 'approveShiftClaim'],    name: 'admin.open_shifts.approve');
    $r->post('/open-shifts/{id}/reject',  [AdminShiftClaimController::class, 'rejectShiftClaim'],     name: 'admin.open_shifts.reject');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// ShiftClaim — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/shift-claims',         [ApiShiftClaimController::class, 'index'],   name: 'api.v1.shift_claims.index');
    $r->post('/shift-claims',        [ApiShiftClaimController::class, 'store'],   name: 'api.v1.shift_claims.store');
    $r->get('/shift-claims/{id}',    [ApiShiftClaimController::class, 'show'],    name: 'api.v1.shift_claims.show');
    $r->put('/shift-claims/{id}',    [ApiShiftClaimController::class, 'update'],  name: 'api.v1.shift_claims.update');
    $r->delete('/shift-claims/{id}', [ApiShiftClaimController::class, 'destroy'], name: 'api.v1.shift_claims.destroy');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
