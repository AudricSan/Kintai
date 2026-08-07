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
    $r->get('/reports/salary/export/json', [AdminSalaryReportController::class, 'exportSalaryReportsJson'], name: 'admin.reports.salary.export_json');
    $r->get('/reports/salary/export/pdf',  [AdminSalaryReportController::class, 'exportSalaryReportsPdf'],  name: 'admin.reports.salary.export_pdf');
    $r->get('/reports/salary/export/pdf/download', [AdminSalaryReportController::class, 'exportSalaryReportsPdfDownload'], name: 'admin.reports.salary.export_pdf_download');

    $r->get('/stores/{id}/reports/salary',              [AdminSalaryReportController::class, 'salaryReports'],      name: 'admin.stores.salary_reports');
    $r->get('/stores/{id}/reports/salary/create',       [AdminSalaryReportController::class, 'createSalaryReport'], name: 'admin.stores.salary_reports.create');
    $r->post('/stores/{id}/reports/salary/create',      [AdminSalaryReportController::class, 'storeSalaryReport'],  name: 'admin.stores.salary_reports.store');
    $r->get('/stores/{id}/reports/salary/calculate',    [AdminSalaryReportController::class, 'calculateSalaryReport'], name: 'admin.stores.salary_reports.calculate');
    $r->get('/stores/{id}/reports/salary/{rid}',        [AdminSalaryReportController::class, 'showSalaryReport'],   name: 'admin.stores.salary_reports.show');
    $r->get('/stores/{id}/reports/salary/{rid}/edit',   [AdminSalaryReportController::class, 'editSalaryReport'],   name: 'admin.stores.salary_reports.edit');
    $r->post('/stores/{id}/reports/salary/{rid}/edit',  [AdminSalaryReportController::class, 'updateSalaryReport'], name: 'admin.stores.salary_reports.update');
    $r->post('/stores/{id}/reports/salary/{rid}/delete', [AdminSalaryReportController::class, 'deleteSalaryReport'], name: 'admin.stores.salary_reports.delete');
    $r->get('/stores/{id}/reports/salary/{rid}/pdf',    [AdminSalaryReportController::class, 'salaryReportPdf'],    name: 'admin.stores.salary_reports.pdf');
    $r->get('/stores/{id}/reports/salary/{rid}/pdf/download', [AdminSalaryReportController::class, 'salaryReportPdfDownload'], name: 'admin.stores.salary_reports.pdf_download');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);
