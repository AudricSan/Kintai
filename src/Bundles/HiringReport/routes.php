<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Bundles\HiringReport\Controllers\Web\AdminHiringReportController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// HiringReport — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/stores/{id}/reports/hiring',              [AdminHiringReportController::class, 'hiringReports'],      name: 'admin.stores.hiring_reports');
    $r->get('/stores/{id}/reports/hiring/create',       [AdminHiringReportController::class, 'createHiringReport'], name: 'admin.stores.hiring_reports.create');
    $r->post('/stores/{id}/reports/hiring/create',      [AdminHiringReportController::class, 'storeHiringReport'],  name: 'admin.stores.hiring_reports.store');
    $r->get('/stores/{id}/reports/hiring/{rid}',        [AdminHiringReportController::class, 'showHiringReport'],   name: 'admin.stores.hiring_reports.show');
    $r->get('/stores/{id}/reports/hiring/{rid}/edit',   [AdminHiringReportController::class, 'editHiringReport'],   name: 'admin.stores.hiring_reports.edit');
    $r->post('/stores/{id}/reports/hiring/{rid}/edit',  [AdminHiringReportController::class, 'updateHiringReport'], name: 'admin.stores.hiring_reports.update');
    $r->post('/stores/{id}/reports/hiring/{rid}/delete', [AdminHiringReportController::class, 'deleteHiringReport'], name: 'admin.stores.hiring_reports.delete');
    $r->get('/stores/{id}/reports/hiring/{rid}/pdf',    [AdminHiringReportController::class, 'hiringReportPdf'],    name: 'admin.stores.hiring_reports.pdf');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class]);
