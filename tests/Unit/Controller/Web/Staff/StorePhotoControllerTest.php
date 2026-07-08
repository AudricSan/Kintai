<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\Staff;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\Staff\StorePhotoController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StorePhotoControllerTest extends TestCase
{
    private StorePhotoRepositoryInterface&MockObject $photos;
    private StoreRepositoryInterface&MockObject $stores;
    private AppSettingsRepositoryInterface&MockObject $appSettings;
    private StorePhotoController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('staff.store-photos-form');
        $this->ensureViewFile('layout.app');

        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->appSettings = $this->createMock(AppSettingsRepositoryInterface::class);

        $this->controller = new StorePhotoController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->photos,
            $this->stores,
            $this->appSettings,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testStoreForbiddenWhenPhotosDisabledForStore(): void
    {
        $_POST = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        // Une ligne existe en base mais 'photos' n'y figure pas => désactivé pour ce store.
        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock']);

        $this->expectException(ForbiddenException::class);
        $this->controller->store($req);
    }

    public function testStoreAllowedWhenPhotosEnabledForStore(): void
    {
        $_POST = ['store_id' => '1', 'week_label' => '2026-07-07'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts', 'photos']);
        $this->appSettings->method('get')->willReturn('14');
        $this->photos->method('saveSubmission')->willReturn(['id' => 5, 'store_id' => 1]);

        $response = $this->controller->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreAllowedWhenStoreHasNoFeatureConfiguration(): void
    {
        // Aucune ligne en base pour ce store => toutes les fonctionnalités actives par défaut.
        $_POST = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->stores->method('getFeatures')->with(1)->willReturn([]);
        $this->appSettings->method('get')->willReturn('14');
        $this->photos->method('saveSubmission')->willReturn(['id' => 5, 'store_id' => 1]);

        $response = $this->controller->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testCreateForbiddenWhenPhotosDisabledForRequestedStore(): void
    {
        $_GET['store_id'] = '1';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->stores->method('findAll')->willReturn([]);
        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts']);

        $this->expectException(ForbiddenException::class);
        $this->controller->create($req);
    }

    private function ensureViewFile(string $view): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($file);
    }
}
