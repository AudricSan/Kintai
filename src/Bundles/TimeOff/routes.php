<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Bundles\TimeOff\Controllers\Web\EmployeeTimeoffController;
use kintai\Bundles\TimeOff\Controllers\Web\AdminTimeoffController;
use kintai\Bundles\TimeOff\Controllers\Api\TimeoffRequestController as ApiTimeoffRequestController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// TimeOff — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/timeoff',              [EmployeeTimeoffController::class, 'timeoff'],       name: 'employee.timeoff');
    $r->post('/timeoff',             [EmployeeTimeoffController::class, 'storeTimeoff'],  name: 'employee.timeoff.store');
    $r->post('/timeoff/{id}/cancel', [EmployeeTimeoffController::class, 'cancelTimeoff'], name: 'employee.timeoff.cancel');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// TimeOff — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/timeoff',               [AdminTimeoffController::class, 'timeoff'],               name: 'admin.timeoff');
    $r->get('/timeoff/create',        [AdminTimeoffController::class, 'createTimeoff'],          name: 'admin.timeoff.create');
    $r->post('/timeoff/create',       [AdminTimeoffController::class, 'storeTimeoffForEmployee'], name: 'admin.timeoff.store');
    $r->post('/timeoff/{id}/approve', [AdminTimeoffController::class, 'approveTimeoff'],         name: 'admin.timeoff.approve');
    $r->post('/timeoff/{id}/refuse',  [AdminTimeoffController::class, 'refuseTimeoff'],          name: 'admin.timeoff.refuse');
    $r->post('/timeoff/{id}/delete',  [AdminTimeoffController::class, 'deleteTimeoff'],          name: 'admin.timeoff.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class]);

// =============================================================================
// TimeOff — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/timeoff-requests',         [ApiTimeoffRequestController::class, 'index'],   name: 'api.v1.timeoff.index');
    $r->post('/timeoff-requests',        [ApiTimeoffRequestController::class, 'store'],   name: 'api.v1.timeoff.store');
    $r->get('/timeoff-requests/{id}',    [ApiTimeoffRequestController::class, 'show'],    name: 'api.v1.timeoff.show');
    $r->put('/timeoff-requests/{id}',    [ApiTimeoffRequestController::class, 'update'],  name: 'api.v1.timeoff.update');
    $r->delete('/timeoff-requests/{id}', [ApiTimeoffRequestController::class, 'destroy'], name: 'api.v1.timeoff.destroy');
}, middleware: [ApiAuthMiddleware::class]);
