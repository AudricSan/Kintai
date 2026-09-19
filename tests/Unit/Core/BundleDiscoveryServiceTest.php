<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Bundle;
use kintai\Core\BundleDiscoveryService;
use kintai\Core\InstalledBundleManifestStore;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

final class BundleDiscoveryServiceTest extends TestCase
{
    private string $emptyInstalledDir;
    private InstalledBundleManifestStore $emptyInstalledStore;

    protected function setUp(): void
    {
        // Isole les tests sur un dossier synthétique de leur propre installed.json,
        // sans effet de bord si un bundle est réellement installé sur cette machine
        // (storage/bundles/installed.json), et réciproquement.
        $this->emptyInstalledDir = sys_get_temp_dir() . '/kintai-installed-empty-' . uniqid();
        $this->emptyInstalledStore = new InstalledBundleManifestStore($this->emptyInstalledDir . '/installed.json');
    }

    public function testDiscoversALegacyBundleFromTheGivenDirectory(): void
    {
        // Depuis que TeamDirectory (le dernier bundle du monorepo) a été extrait vers
        // son propre dépôt, plus aucun bundle legacy n'est réellement présent dans
        // src/Bundles/ : ce test vérifie le mécanisme via un bundle synthétique plutôt
        // que contre le vrai dossier du dépôt. La découverte legacy reste utile pour un
        // bundle tiers déposé manuellement dans src/Bundles/ (voir BundleDiscoveryService).
        $dir = sys_get_temp_dir() . '/kintai-bundle-discovery-' . uniqid();
        $this->writeFakeLegacyBundle($dir, 'FakeLegacyBundle', 'fake-legacy-bundle');

        $service = new BundleDiscoveryService($dir, $this->emptyInstalledStore, $this->emptyInstalledDir);

        $discovered = $service->discover();

        $this->assertArrayHasKey('fake-legacy-bundle', $discovered);
        $this->assertNotSame('', $discovered['fake-legacy-bundle']['label']);
        $this->assertTrue(is_subclass_of($discovered['fake-legacy-bundle']['class'], Bundle::class));
        $this->assertSame('0.0.0', $discovered['fake-legacy-bundle']['version']);
    }

    public function testIgnoresDirectoriesWithNoMatchingBundleClass(): void
    {
        $dir = sys_get_temp_dir() . '/kintai-bundle-discovery-' . uniqid();
        mkdir($dir . '/NotABundle', 0777, true);
        // Pas de fichier NotABundleBundle.php dedans -> doit être ignoré silencieusement.
        file_put_contents($dir . '/a-stray-file.txt', 'noop');

        $service = new BundleDiscoveryService($dir, $this->emptyInstalledStore, $this->emptyInstalledDir);

        $this->assertSame([], $service->discover());

        unlink($dir . '/a-stray-file.txt');
        rmdir($dir . '/NotABundle');
        rmdir($dir);
    }

    public function testReturnsEmptyArrayWhenBundlesDirDoesNotExist(): void
    {
        $service = new BundleDiscoveryService(
            sys_get_temp_dir() . '/kintai-does-not-exist-' . uniqid(),
            $this->emptyInstalledStore,
            $this->emptyInstalledDir,
        );

        $this->assertSame([], $service->discover());
    }

    public function testDiscoversAnInstalledBundleFromItsManifest(): void
    {
        $installedDir = sys_get_temp_dir() . '/kintai-installed-' . uniqid();
        $this->writeFakeInstalledBundle($installedDir, 'fake-bundle', '2.1.0');

        $store = new InstalledBundleManifestStore($installedDir . '/installed.json');
        $store->setActiveVersion('fake-bundle', '2.1.0');

        $service = new BundleDiscoveryService(
            sys_get_temp_dir() . '/kintai-does-not-exist-' . uniqid(),
            $store,
            $installedDir,
        );

        $discovered = $service->discover();

        $this->assertArrayHasKey('fake-bundle', $discovered);
        $this->assertSame('2.1.0', $discovered['fake-bundle']['version']);
        $this->assertSame('Fake Installed Bundle', $discovered['fake-bundle']['label']);
    }

    public function testLegacySlugWinsOverAnInstalledBundleWithTheSameSlug(): void
    {
        $legacyDir = sys_get_temp_dir() . '/kintai-bundle-discovery-' . uniqid();
        $this->writeFakeLegacyBundle($legacyDir, 'FakeCollisionBundle', 'fake-collision-bundle');

        $installedDir = sys_get_temp_dir() . '/kintai-installed-' . uniqid();
        // Une collision entre un slug legacy et un slug installé dynamiquement ne
        // doit jamais faire gagner la version installée.
        $this->writeFakeInstalledBundle($installedDir, 'fake-collision-bundle', '9.9.9');

        $store = new InstalledBundleManifestStore($installedDir . '/installed.json');
        $store->setActiveVersion('fake-collision-bundle', '9.9.9');

        $service = new BundleDiscoveryService($legacyDir, $store, $installedDir);

        $discovered = $service->discover();

        $this->assertArrayHasKey('fake-collision-bundle', $discovered);
        $this->assertNotSame('9.9.9', $discovered['fake-collision-bundle']['version']);
    }

    private function writeFakeInstalledBundle(string $installedDir, string $slug, string $version): void
    {
        $className = 'FakeInstalledBundle_' . str_replace('-', '_', $slug) . '_' . str_replace('.', '_', $version);
        $namespace = 'kintai\\Bundles\\Installed\\' . $className;
        $bundleRoot = $installedDir . '/' . $slug . '/' . $version;
        mkdir($bundleRoot . '/src', 0777, true);

        file_put_contents($bundleRoot . '/bundle.json', json_encode([
            'slug'        => $slug,
            'name'        => 'Fake Installed Bundle',
            'version'     => $version,
            'namespace'   => $namespace,
            'entry_class' => $namespace . '\\' . $className . 'Bundle',
        ]));

        file_put_contents($bundleRoot . '/src/' . $className . 'Bundle.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use kintai\Core\BundleContract\Bundle;
        final class {$className}Bundle extends Bundle {
            public function getName(): string { return '{$slug}'; }
            public function getLabel(): string { return 'Fake Installed Bundle'; }
            public function register(): void {}
        }
        PHP);

        require $bundleRoot . '/src/' . $className . 'Bundle.php';
    }

    private function writeFakeLegacyBundle(string $legacyDir, string $dirName, string $slug): void
    {
        $namespace = 'kintai\\Bundles\\' . $dirName;
        $bundleRoot = $legacyDir . '/' . $dirName;
        mkdir($bundleRoot, 0777, true);

        file_put_contents($bundleRoot . '/' . $dirName . 'Bundle.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use kintai\Core\Bundle;
        final class {$dirName}Bundle extends Bundle {
            public function getName(): string { return '{$slug}'; }
            public function getLabel(): string { return 'Fake Legacy Bundle'; }
            public function register(): void {}
        }
        PHP);

        require $bundleRoot . '/' . $dirName . 'Bundle.php';
    }
}
