<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use kintai\Core\BundleDiscoveryService;
use kintai\Core\FeatureManager;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\System\BundleSettingsController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 5));
}

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

    /**
     * Utilise le vrai BundleDiscoveryService (scan de src/Bundles réel) : sur cette
     * branche, seuls daily-report et messaging sont réellement présents sur le disque.
     */
    private function makeController(FeatureManager $features): BundleSettingsController
    {
        return new BundleSettingsController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->appSettings,
            $features,
            new AuditLogger(),
            new BundleDiscoveryService(),
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

        $_POST = ['bundle_daily-report' => '1', 'bundle_messaging' => '1'];
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
        sort($captured);
        $this->assertSame(['daily-report', 'messaging'], $captured);
    }

    public function testOfficialBundlesRegistryListsBundlesShippedWithTheRepo(): void
    {
        // config/official-bundles.php est la seule source de vérité pour la distinction
        // officiel/tiers affichée sur /admin/bundles : elle ne fait confiance à aucune
        // auto-déclaration d'un bundle tiers.
        $official = require BASE_PATH . '/config/official-bundles.php';

        $this->assertContains('daily-report', $official);
        $this->assertContains('messaging', $official);
        $this->assertNotContains('some-random-third-party-bundle', $official);
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
