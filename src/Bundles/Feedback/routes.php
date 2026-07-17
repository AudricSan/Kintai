<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\Feedback\Controllers\Web\FeedbackController;
use kintai\Bundles\Feedback\Controllers\Api\FeedbackController as ApiFeedbackController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// Feedback — Routes Web Employé
// =============================================================================

$router->group('/employee', function ($r) {
    $r->post('/feedback',            [FeedbackController::class, 'submit'],     name: 'employee.feedback.submit');
    $r->get('/feedback/past-shifts', [FeedbackController::class, 'pastShifts'], name: 'employee.feedback.past_shifts');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Feedback — Routes Web Admin
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/feedbacks',              [FeedbackController::class, 'index'],  name: 'admin.feedbacks');
    $r->post('/feedbacks/{id}/delete', [FeedbackController::class, 'delete'], name: 'admin.feedbacks.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Feedback — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/feedbacks',         [ApiFeedbackController::class, 'index'],   name: 'api.v1.feedbacks.index');
    $r->post('/feedbacks',        [ApiFeedbackController::class, 'store'],   name: 'api.v1.feedbacks.store');
    $r->get('/feedbacks/{id}',    [ApiFeedbackController::class, 'show'],    name: 'api.v1.feedbacks.show');
    $r->put('/feedbacks/{id}',    [ApiFeedbackController::class, 'update'],  name: 'api.v1.feedbacks.update');
    $r->delete('/feedbacks/{id}', [ApiFeedbackController::class, 'destroy'], name: 'api.v1.feedbacks.destroy');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
