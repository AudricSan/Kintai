<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\ShiftSwap\Controllers\Web\EmployeeSwapController;
use kintai\Bundles\ShiftSwap\Controllers\Web\AdminSwapController;
use kintai\Bundles\ShiftSwap\Controllers\Api\ShiftSwapRequestController as ApiShiftSwapRequestController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// ShiftSwap — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/swaps',              [EmployeeSwapController::class, 'swaps'],       name: 'employee.swaps');
    $r->get('/swaps/create',       [EmployeeSwapController::class, 'createSwap'],  name: 'employee.swaps.create');
    $r->post('/swaps/create',      [EmployeeSwapController::class, 'storeSwap'],   name: 'employee.swaps.store');
    $r->post('/swaps/{id}/accept', [EmployeeSwapController::class, 'acceptSwap'],  name: 'employee.swaps.accept');
    $r->post('/swaps/{id}/refuse', [EmployeeSwapController::class, 'refuseSwap'],  name: 'employee.swaps.refuse');
    $r->post('/swaps/{id}/cancel', [EmployeeSwapController::class, 'cancelSwap'],  name: 'employee.swaps.cancel');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// ShiftSwap — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/swap-requests',               [AdminSwapController::class, 'swapRequests'], name: 'admin.swap_requests');
    $r->get('/swap-requests/create',        [AdminSwapController::class, 'createSwap'],   name: 'admin.swap_requests.create');
    $r->post('/swap-requests/create',       [AdminSwapController::class, 'storeSwap'],    name: 'admin.swap_requests.store');
    $r->post('/swap-requests/{id}/approve', [AdminSwapController::class, 'approveSwap'],  name: 'admin.swap.approve');
    $r->post('/swap-requests/{id}/refuse',  [AdminSwapController::class, 'refuseSwap'],   name: 'admin.swap.refuse');
    $r->post('/swap-requests/{id}/delete',  [AdminSwapController::class, 'deleteSwap'],   name: 'admin.swap.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// ShiftSwap — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/shift-swap-requests',         [ApiShiftSwapRequestController::class, 'index'],   name: 'api.v1.swap.index');
    $r->post('/shift-swap-requests',        [ApiShiftSwapRequestController::class, 'store'],   name: 'api.v1.swap.store');
    $r->get('/shift-swap-requests/{id}',    [ApiShiftSwapRequestController::class, 'show'],    name: 'api.v1.swap.show');
    $r->put('/shift-swap-requests/{id}',    [ApiShiftSwapRequestController::class, 'update'],  name: 'api.v1.swap.update');
    $r->delete('/shift-swap-requests/{id}', [ApiShiftSwapRequestController::class, 'destroy'], name: 'api.v1.swap.destroy');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
