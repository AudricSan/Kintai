<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\SalaryReport\Controllers\Web\AdminSalaryReportController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// SalaryReport — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/reports/salary', [AdminSalaryReportController::class, 'allSalaryReports'], name: 'admin.reports.salary');

    $r->get('/stores/{id}/reports/salary',              [AdminSalaryReportController::class, 'salaryReports'],      name: 'admin.stores.salary_reports');
    $r->get('/stores/{id}/reports/salary/create',       [AdminSalaryReportController::class, 'createSalaryReport'], name: 'admin.stores.salary_reports.create');
    $r->post('/stores/{id}/reports/salary/create',      [AdminSalaryReportController::class, 'storeSalaryReport'],  name: 'admin.stores.salary_reports.store');
    $r->get('/stores/{id}/reports/salary/{rid}',        [AdminSalaryReportController::class, 'showSalaryReport'],   name: 'admin.stores.salary_reports.show');
    $r->get('/stores/{id}/reports/salary/{rid}/edit',   [AdminSalaryReportController::class, 'editSalaryReport'],   name: 'admin.stores.salary_reports.edit');
    $r->post('/stores/{id}/reports/salary/{rid}/edit',  [AdminSalaryReportController::class, 'updateSalaryReport'], name: 'admin.stores.salary_reports.update');
    $r->post('/stores/{id}/reports/salary/{rid}/delete', [AdminSalaryReportController::class, 'deleteSalaryReport'], name: 'admin.stores.salary_reports.delete');
    $r->get('/stores/{id}/reports/salary/{rid}/pdf',    [AdminSalaryReportController::class, 'salaryReportPdf'],    name: 'admin.stores.salary_reports.pdf');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);
