<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use kintai\Core\FeatureManager;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\System\BundleSettingsController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BundleSettingsControllerTest extends TestCase
{
    private AppSettingsRepositoryInterface&MockObject $appSettings;

    protected function setUp(): void
    {
        $this->ensureViewFile('system.bundles');
        $this->ensureViewFile('layout.app');
        $this->appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    private function makeController(FeatureManager $features): BundleSettingsController
    {
        return new BundleSettingsController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->appSettings,
            $features,
            new AuditLogger(),
        );
    }

    public function testShowRendersPageForOwner(): void
    {
        $controller = $this->makeController(new FeatureManager(['messaging']));

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $response = $controller->show($req);

        $this->assertSame(200, $response->status());
    }

    public function testSavePersistsSelectedBundlesAsJson(): void
    {
        $controller = $this->makeController(new FeatureManager(['messaging']));

        $_POST = ['bundle_daily-report' => '1', 'bundle_store-photos' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $captured = null;
        $this->appSettings->expects($this->once())->method('set')->willReturnCallback(
            function (string $key, string $value) use (&$captured) {
                $this->assertSame('enabled_bundles', $key);
                $captured = json_decode($value, true);
            }
        );

        $response = $controller->save($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame(['daily-report', 'store-photos'], $captured);
    }

    public function testSaveWithNoCheckedBundleDisablesAll(): void
    {
        $controller = $this->makeController(new FeatureManager(['messaging', 'daily-report']));

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $captured = null;
        $this->appSettings->method('set')->willReturnCallback(
            function (string $key, string $value) use (&$captured) {
                $captured = json_decode($value, true);
            }
        );

        $controller->save($req);

        $this->assertSame([], $captured);
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
