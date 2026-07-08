<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Bundles\DailyReport\Controllers\Web\DailyReportController;
use kintai\Bundles\DailyReport\Controllers\Api\DailyReportController as ApiDailyReportController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// Daily Report — Routes Web (admin)
// =============================================================================

$router->get('/admin/daily-reports', [DailyReportController::class, 'indexAll'], middleware: [AuthMiddleware::class, AdminMiddleware::class], name: 'admin.daily_reports.all');

$router->group('/admin', function ($r) {
    $r->get('/stores/{id}/daily-reports',              [DailyReportController::class, 'index'],        name: 'admin.daily_reports.index');
    $r->get('/stores/{id}/daily-reports/settings',     [DailyReportController::class, 'editSettings'], name: 'admin.daily_reports.settings');
    $r->post('/stores/{id}/daily-reports/settings',    [DailyReportController::class, 'saveSettings'], name: 'admin.daily_reports.settings.save');
    $r->get('/stores/{id}/daily-reports/create',       [DailyReportController::class, 'create'],       name: 'admin.daily_reports.create');
    $r->post('/stores/{id}/daily-reports/create',      [DailyReportController::class, 'store'],        name: 'admin.daily_reports.store');
    $r->get('/stores/{id}/daily-reports/{rid}',              [DailyReportController::class, 'show'],        name: 'admin.daily_reports.show');
    $r->get('/stores/{id}/daily-reports/{rid}/edit',         [DailyReportController::class, 'edit'],        name: 'admin.daily_reports.edit');
    $r->post('/stores/{id}/daily-reports/{rid}/edit',        [DailyReportController::class, 'update'],      name: 'admin.daily_reports.update');
    $r->post('/stores/{id}/daily-reports/{rid}/submit',      [DailyReportController::class, 'submit'],      name: 'admin.daily_reports.submit');
    $r->post('/stores/{id}/daily-reports/{rid}/validate',    [DailyReportController::class, 'validate'],    name: 'admin.daily_reports.validate');
    $r->post('/stores/{id}/daily-reports/{rid}/send-mail',   [DailyReportController::class, 'sendMail'],    name: 'admin.daily_reports.send_mail');
    $r->get('/stores/{id}/daily-reports/{rid}/pdf',          [DailyReportController::class, 'downloadPdf'], name: 'admin.daily_reports.pdf');
    $r->post('/stores/{id}/daily-reports/{rid}/delete',      [DailyReportController::class, 'destroy'],     name: 'admin.daily_reports.delete');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Daily Report — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->post('/daily-reports/{id}/submit',   [ApiDailyReportController::class, 'submit'],   name: 'api.v1.daily_reports.submit');
    $r->post('/daily-reports/{id}/validate', [ApiDailyReportController::class, 'validate'], name: 'api.v1.daily_reports.validate');
    $r->get('/daily-reports',                [ApiDailyReportController::class, 'index'],    name: 'api.v1.daily_reports.index');
    $r->post('/daily-reports',               [ApiDailyReportController::class, 'store'],    name: 'api.v1.daily_reports.store');
    $r->get('/daily-reports/{id}',           [ApiDailyReportController::class, 'show'],     name: 'api.v1.daily_reports.show');
    $r->put('/daily-reports/{id}',           [ApiDailyReportController::class, 'update'],   name: 'api.v1.daily_reports.update');
    $r->delete('/daily-reports/{id}',        [ApiDailyReportController::class, 'destroy'],  name: 'api.v1.daily_reports.destroy');
}, middleware: [ApiAuthMiddleware::class]);
