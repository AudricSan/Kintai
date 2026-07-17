<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\Timeclock\Controllers\Web\EmployeeTimeclockController;
use kintai\Bundles\Timeclock\Controllers\Web\AdminTimeclockController;
use kintai\Bundles\Timeclock\Controllers\Api\TimeclockController as ApiTimeclockController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// Timeclock — Routes Web Employé
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/timeclock',            [EmployeeTimeclockController::class, 'timeclock'], name: 'employee.timeclock');
    $r->post('/timeclock/clock-in',  [EmployeeTimeclockController::class, 'clockIn'],   name: 'employee.timeclock.clock_in');
    $r->post('/timeclock/clock-out', [EmployeeTimeclockController::class, 'clockOut'],  name: 'employee.timeclock.clock_out');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Timeclock — Routes Web Admin
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/timeclocks',              [AdminTimeclockController::class, 'timeclocks'],       name: 'admin.timeclocks');
    $r->post('/timeclocks/{id}/edit',   [AdminTimeclockController::class, 'timeclocksEdit'],   name: 'admin.timeclocks.edit');
    $r->post('/timeclocks/{id}/delete', [AdminTimeclockController::class, 'timeclocksDelete'], name: 'admin.timeclocks.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Timeclock — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->post('/timeclocks/clock-in',  [ApiTimeclockController::class, 'clockIn'],  name: 'api.v1.timeclock.clock_in');
    $r->post('/timeclocks/clock-out', [ApiTimeclockController::class, 'clockOut'], name: 'api.v1.timeclock.clock_out');
    $r->get('/timeclocks',            [ApiTimeclockController::class, 'index'],    name: 'api.v1.timeclock.index');
    $r->get('/timeclocks/{id}',       [ApiTimeclockController::class, 'show'],     name: 'api.v1.timeclock.show');
    $r->put('/timeclocks/{id}',       [ApiTimeclockController::class, 'update'],   name: 'api.v1.timeclock.update');
    $r->delete('/timeclocks/{id}',    [ApiTimeclockController::class, 'destroy'],  name: 'api.v1.timeclock.destroy');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
