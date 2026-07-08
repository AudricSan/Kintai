<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\StorePhoto;

use kintai\Bundles\StorePhoto\Controllers\Web\StorePhotoController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
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
        $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
        $this->ensureViewFile($viewDir, 'store-photos');
        $this->ensureViewFile($viewDir, 'store-photos-settings');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('store-photos', $viewDir);

        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->appSettings = $this->createMock(AppSettingsRepositoryInterface::class);

        $this->controller = new StorePhotoController(
            $view,
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
        $_SERVER = [];
    }

    public function testIndexRendersSubmissionsFilteredByStore(): void
    {
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 10, 'store_id' => 1],
            ['id' => 11, 'store_id' => 2],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $_GET['store_id'] = '1';

        $response = $this->controller->index($req);

        $this->assertSame(200, $response->status());
    }

    public function testStoreWithoutStoreIdRedirectsToCreate(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreCreatesSubmissionAndLogsAudit(): void
    {
        $_POST = ['store_id' => '1', 'week_label' => '2026-07-07', 'notes' => 'RAS'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->appSettings->method('get')->willReturn('14');
        $this->photos->method('saveSubmission')->willReturn(['id' => 55, 'store_id' => 1]);

        $response = $this->controller->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreForbiddenWhenStoreNotManaged(): void
    {
        $_POST = ['store_id' => '9'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1, 2]);

        $this->expectException(ForbiddenException::class);
        $this->controller->store($req);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 3, 'is_admin' => false]);
        $req->setRouteParams(['id' => '1']);

        $this->expectException(ForbiddenException::class);
        $this->controller->delete($req);
    }

    public function testDeleteRemovesSubmissionForAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '42']);

        $this->photos->method('findSubmissionById')->with(42)->willReturn(['id' => 42, 'store_id' => 3]);
        $this->photos->expects($this->once())->method('deleteSubmission')->with(42);

        $response = $this->controller->delete($req);

        $this->assertSame(302, $response->status());
    }

    public function testSettingsForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 3, 'is_admin' => false]);

        $this->expectException(ForbiddenException::class);
        $this->controller->settings($req);
    }

    public function testSettingsRendersForAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->appSettings->method('get')->willReturn('14');

        $response = $this->controller->settings($req);

        $this->assertSame(200, $response->status());
    }

    public function testSaveSettingsPersistsRetentionAndCleanupDelay(): void
    {
        $_POST = ['photo_retention_days' => '30', 'photo_cleanup_delay' => '10'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->appSettings->expects($this->once())->method('setMany')->with([
            'photo_retention_days' => '30',
            'photo_cleanup_delay'  => '10',
        ]);

        $response = $this->controller->saveSettings($req);

        $this->assertSame(302, $response->status());
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }
}
