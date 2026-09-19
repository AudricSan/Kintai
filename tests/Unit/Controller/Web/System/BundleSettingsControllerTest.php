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
    private static string $legacyBundlesDir;

    private AppSettingsRepositoryInterface&MockObject $appSettings;

    /**
     * Depuis que TeamDirectory (le dernier bundle du monorepo) a été extrait vers son
     * propre dépôt, plus aucun bundle legacy n'est réellement présent dans src/Bundles/ :
     * ce test scanne un dossier synthétique contenant un faux bundle "team-directory"
     * plutôt que le vrai dossier du dépôt (voir docs/architecture.md "Modular Bundles").
     */
    public static function setUpBeforeClass(): void
    {
        self::$legacyBundlesDir = sys_get_temp_dir() . '/kintai-bundle-settings-legacy-' . uniqid();
        $bundleRoot = self::$legacyBundlesDir . '/TeamDirectory';
        mkdir($bundleRoot, 0777, true);

        $file = $bundleRoot . '/TeamDirectoryBundle.php';
        file_put_contents($file, <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace kintai\Bundles\TeamDirectory;
        use kintai\Core\BundleContract\Bundle;
        final class TeamDirectoryBundle extends Bundle {
            public function getName(): string { return 'team-directory'; }
            public function getLabel(): string { return 'Fake Team Directory'; }
            public function register(): void {}
        }
        PHP);

        require $file;
    }

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
            new BundleDiscoveryService(self::$legacyBundlesDir),
        );
    }

    public function testShowRendersPageForOwner(): void
    {
        $controller = $this->makeController(new FeatureManager(['team-directory']));

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $response = $controller->show($req);

        $this->assertSame(200, $response->status());
    }

    public function testSavePersistsSelectedBundlesAsJson(): void
    {
        $controller = $this->makeController(new FeatureManager(['team-directory']));

        $_POST = ['bundle_team-directory' => '1'];
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
        $this->assertSame(['team-directory'], $captured);
    }

    public function testOfficialBundlesRegistryListsBundlesShippedWithTheRepo(): void
    {
        // config/official-bundles.php est la seule source de vérité pour la distinction
        // officiel/tiers affichée sur /admin/bundles : elle ne fait confiance à aucune
        // auto-déclaration d'un bundle tiers.
        $official = require BASE_PATH . '/config/official-bundles.php';

        $this->assertContains('daily-report', $official);
        $this->assertContains('timeclock', $official);
        $this->assertNotContains('some-random-third-party-bundle', $official);
    }

    public function testSaveWithNoCheckedBundleDisablesAll(): void
    {
        $controller = $this->makeController(new FeatureManager(['team-directory']));

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
