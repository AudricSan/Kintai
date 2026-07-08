<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Bundles\StorePhoto\Controllers\Web\StorePhotoController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// Store Photos — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/photos',                         [StorePhotoController::class, 'index'],        name: 'admin.photos.index');
    $r->get('/photos/settings',                [StorePhotoController::class, 'settings'],     name: 'admin.photos.settings');
    $r->post('/photos/settings',               [StorePhotoController::class, 'saveSettings'], name: 'admin.photos.settings.save');
    $r->get('/photos/create',                  [StorePhotoController::class, 'create'],       name: 'admin.photos.create');
    $r->post('/photos/create',                 [StorePhotoController::class, 'store'],        name: 'admin.photos.store');
    $r->post('/photos/{id}/upload',            [StorePhotoController::class, 'uploadFile'],   name: 'admin.photos.upload_file');
    $r->get('/photos/{store_id}/{id}',         [StorePhotoController::class, 'show'],         name: 'admin.photos.show');
    $r->post('/photos/{store_id}/{id}/delete', [StorePhotoController::class, 'delete'],       name: 'admin.photos.delete');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class]);
