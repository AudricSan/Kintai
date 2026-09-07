<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Bundles\ResignationReport\Controllers\Web\AdminResignationReportController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// ResignationReport — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/reports/resignation', [AdminResignationReportController::class, 'allResignationReports'], name: 'admin.reports.resignation');
    $r->get('/reports/resignation/export/json', [AdminResignationReportController::class, 'exportResignationReportsJson'], name: 'admin.reports.resignation.export_json');
    $r->get('/reports/resignation/export/pdf',  [AdminResignationReportController::class, 'exportResignationReportsPdf'],  name: 'admin.reports.resignation.export_pdf');
    $r->get('/reports/resignation/export/pdf/download', [AdminResignationReportController::class, 'exportResignationReportsPdfDownload'], name: 'admin.reports.resignation.export_pdf_download');

    $r->get('/stores/{id}/reports/resignation',                   [AdminResignationReportController::class, 'resignationReports'],      name: 'admin.stores.resignation_reports');
    $r->get('/stores/{id}/reports/resignation/create',            [AdminResignationReportController::class, 'createResignationReport'], name: 'admin.stores.resignation_reports.create');
    $r->post('/stores/{id}/reports/resignation/create',           [AdminResignationReportController::class, 'storeResignationReport'],  name: 'admin.stores.resignation_reports.store');
    $r->get('/stores/{id}/reports/resignation/{rid}',              [AdminResignationReportController::class, 'showResignationReport'],   name: 'admin.stores.resignation_reports.show');
    $r->get('/stores/{id}/reports/resignation/{rid}/edit',         [AdminResignationReportController::class, 'editResignationReport'],   name: 'admin.stores.resignation_reports.edit');
    $r->post('/stores/{id}/reports/resignation/{rid}/edit',        [AdminResignationReportController::class, 'updateResignationReport'], name: 'admin.stores.resignation_reports.update');
    $r->post('/stores/{id}/reports/resignation/{rid}/delete',      [AdminResignationReportController::class, 'deleteResignationReport'], name: 'admin.stores.resignation_reports.delete');
    $r->post('/stores/{id}/reports/resignation/{rid}/delete-permanently', [AdminResignationReportController::class, 'deleteResignationReportPermanently'], name: 'admin.stores.resignation_reports.delete_permanently');
    $r->get('/stores/{id}/reports/resignation/{rid}/pdf',          [AdminResignationReportController::class, 'resignationReportPdf'],    name: 'admin.stores.resignation_reports.pdf');
    $r->get('/stores/{id}/reports/resignation/{rid}/pdf/download', [AdminResignationReportController::class, 'resignationReportPdfDownload'], name: 'admin.stores.resignation_reports.pdf_download');
    $r->post('/stores/{id}/reports/resignation/{rid}/reactivate',  [AdminResignationReportController::class, 'reactivateUser'],          name: 'admin.stores.resignation_reports.reactivate');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);
