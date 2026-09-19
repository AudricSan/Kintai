<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use kintai\Core\BundleDiscoveryService;
use kintai\Core\InstalledBundleManifestStore;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\BundleRegistryRepositoryInterface;
use kintai\Core\Repositories\InstalledBundleRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\BundleInstaller\BundleInstallerService;
use kintai\Core\Services\BundleRegistry\BundleCatalogService;
use kintai\Core\Services\BundleRegistry\BundleRegistryClient;
use kintai\Core\Services\UpdateService;
use kintai\UI\Controller\Web\System\BundleMarketController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 5));
}

final class BundleMarketControllerTest extends TestCase
{
    private BundleRegistryRepositoryInterface&MockObject $registries;
    private InstalledBundleRepositoryInterface&MockObject $installedBundles;
    private AppSettingsRepositoryInterface&MockObject $appSettings;
    private ?\Closure $registryFetcher = null;
    private ?\Closure $releaseFetcher = null;
    private ?\Closure $zipDownloader = null;
    private string $bundlesDir;
    private string $fakeCoreBasePath;

    protected function setUp(): void
    {
        $this->ensureViewFile('system.bundle-registries');
        $this->ensureViewFile('system.bundle-market');
        $this->ensureViewFile('layout.app');
        $this->registries = $this->createMock(BundleRegistryRepositoryInterface::class);
        $this->installedBundles = $this->createMock(InstalledBundleRepositoryInterface::class);
        $this->appSettings = $this->createMock(AppSettingsRepositoryInterface::class);

        $this->bundlesDir = sys_get_temp_dir() . '/kintai-bundle-market-' . uniqid();
        mkdir($this->bundlesDir, 0777, true);

        $this->fakeCoreBasePath = sys_get_temp_dir() . '/kintai-fake-core-' . uniqid();
        mkdir($this->fakeCoreBasePath . '/config', 0777, true);
        file_put_contents($this->fakeCoreBasePath . '/config/app.php', "<?php\nreturn ['version' => '0.5.0'];\n");
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    private function makeController(): BundleMarketController
    {
        $catalog = new BundleCatalogService(
            $this->registries,
            new BundleRegistryClient($this->registryFetcher),
        );

        $installer = new BundleInstallerService(
            new UpdateService($this->fakeCoreBasePath),
            $this->installedBundles,
            new InstalledBundleManifestStore($this->bundlesDir . '/installed.json'),
            $this->bundlesDir,
            $this->releaseFetcher,
            $this->zipDownloader,
        );

        return new BundleMarketController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->registries,
            $catalog,
            $this->installedBundles,
            $installer,
            new AuditLogger(),
            $this->appSettings,
            new BundleDiscoveryService(
                $this->bundlesDir . '/no-legacy-bundles-here',
                new InstalledBundleManifestStore($this->bundlesDir . '/installed.json'),
                $this->bundlesDir,
            ),
        );
    }

    public function testIndexRendersPage(): void
    {
        $this->registries->method('all')->willReturn([]);

        $response = $this->makeController()->index(new Request());

        $this->assertSame(200, $response->status());
    }

    public function testStoreRejectsInvalidUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'not-a-url'];

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testStoreRejectsNonHttpsUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'http://example.test/registry.json'];

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testStoreRejectsDuplicateUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'https://example.test/registry.json'];
        $this->registries->method('existsByUrl')->willReturn(true);

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('error=duplicate', $this->locationOf($response));
    }

    public function testStoreCreatesRegistryAndRedirectsToSuccess(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'https://example.test/registry.json'];
        $this->registries->method('existsByUrl')->willReturn(false);
        $this->registries->expects($this->once())->method('create')
            ->with('Mon registry', 'https://example.test/registry.json')
            ->willReturn(['id' => 3, 'name' => 'Mon registry', 'url' => 'https://example.test/registry.json', 'is_official' => false]);

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('success=created', $this->locationOf($response));
    }

    public function testDestroyRefusesToDeleteTheOfficialRegistry(): void
    {
        $this->registries->method('find')->willReturn(['id' => 1, 'name' => 'Officiel', 'url' => 'https://example.test/official.json', 'is_official' => true]);
        $this->registries->expects($this->never())->method('delete');

        $req = new Request();
        $req->setRouteParams(['id' => '1']);

        $response = $this->makeController()->destroy($req);

        $this->assertStringContainsString('error=delete_official_forbidden', $this->locationOf($response));
    }

    public function testDestroyDeletesANonOfficialRegistry(): void
    {
        $this->registries->method('find')->willReturn(['id' => 2, 'name' => 'Perso', 'url' => 'https://example.test/perso.json', 'is_official' => false]);
        $this->registries->expects($this->once())->method('delete')->with(2);

        $req = new Request();
        $req->setRouteParams(['id' => '2']);

        $response = $this->makeController()->destroy($req);

        $this->assertStringContainsString('success=deleted', $this->locationOf($response));
    }

    public function testMarketRendersCatalogAggregatedFromRegistries(): void
    {
        $this->registries->method('all')->willReturn([
            ['id' => 1, 'name' => 'Registry officiel', 'url' => 'https://example.test/registry.json', 'is_official' => true],
        ]);
        $this->registryFetcher = fn(string $url) => json_encode([
            'schema_version' => 1,
            'name'           => 'Registry officiel',
            'bundles'        => [[
                'slug'            => 'feedback',
                'name'            => 'Retours utilisateurs',
                'description'     => '...',
                'repository_url'  => 'https://github.com/AudricSan/kintai-bundle-feedback',
                'versions'        => ['1.1.0', '1.0.0'],
            ]],
        ]);
        $this->installedBundles->method('all')->willReturn([
            ['slug' => 'feedback', 'active_version' => '1.0.0', 'source_registry_url' => null],
        ]);

        $response = $this->makeController()->market(new Request());

        $this->assertSame(200, $response->status());
    }

    public function testDryRunRejectsAnIncompleteRequest(): void
    {
        $_POST = ['slug' => '', 'repository_url' => '', 'version' => ''];

        $response = $this->makeController()->dryRun(new Request());

        $this->assertSame(400, $response->status());
    }

    public function testDryRunReturnsJsonResultFromInstaller(): void
    {
        $_POST = [
            'slug'            => 'fake-bundle',
            'repository_url'  => 'https://gitlab.com/someone/fake-bundle',
            'version'         => '1.0.0',
        ];

        $response = $this->makeController()->dryRun(new Request());

        $this->assertSame(422, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('non reconnue', $data['error']);
    }

    public function testInstallRedirectsWithErrorWhenRequestIsIncomplete(): void
    {
        $_POST = ['slug' => 'feedback'];

        $response = $this->makeController()->install(new Request());

        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testInstallRejectsAThirdPartyBundleWithoutExplicitConfirmation(): void
    {
        $this->registries->method('all')->willReturn([]);
        $_POST = [
            'slug'            => 'unofficial-bundle',
            'repository_url'  => 'https://github.com/someone/unofficial-bundle',
            'version'         => '1.0.0',
            // confirm_third_party volontairement absent
        ];

        $response = $this->makeController()->install(new Request());

        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testInstallSucceedsForAThirdPartyBundleWithExplicitConfirmation(): void
    {
        $zipPath = $this->buildFixtureZip();
        $this->releaseFetcher = fn() => 'https://example.test/fake.zip';
        $this->zipDownloader = function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); };

        $_POST = [
            'slug'                => 'fake-bundle',
            'repository_url'      => 'https://github.com/AudricSan/kintai-bundle-fake',
            'version'             => '1.0.0',
            'confirm_third_party' => '1',
        ];

        $this->installedBundles->expects($this->once())->method('upsert')->with('fake-bundle', '1.0.0', null);

        $response = $this->makeController()->install(new Request());

        $this->assertStringContainsString('success=fake-bundle', $this->locationOf($response));
    }

    public function testUninstallRedirectsWithErrorWhenSlugIsMissing(): void
    {
        $_POST = ['slug' => ''];

        $this->installedBundles->expects($this->never())->method('delete');

        $response = $this->makeController()->uninstall(new Request());

        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testUninstallFailsForABundleNotManagedByTheInstaller(): void
    {
        $_POST = ['slug' => 'daily-report'];
        $this->installedBundles->method('find')->willReturn(null);
        $this->installedBundles->expects($this->never())->method('delete');
        $this->appSettings->expects($this->never())->method('set');

        $response = $this->makeController()->uninstall(new Request());

        $this->assertStringNotContainsString('uninstalled=', $this->locationOf($response));
    }

    public function testUninstallRemovesTheBundleAndClearsItFromEnabledBundles(): void
    {
        mkdir($this->bundlesDir . '/feedback/1.0.0', 0777, true);
        $_POST = ['slug' => 'feedback'];
        $this->installedBundles->method('find')->willReturn(['slug' => 'feedback', 'active_version' => '1.0.0', 'source_registry_url' => null]);
        $this->installedBundles->expects($this->once())->method('delete')->with('feedback');
        $this->appSettings->method('get')->with('enabled_bundles')->willReturn(json_encode(['feedback', 'messaging']));
        $this->appSettings->expects($this->once())->method('set')
            ->with('enabled_bundles', $this->callback(fn(string $json) => json_decode($json, true) === ['messaging']));

        $response = $this->makeController()->uninstall(new Request());

        $this->assertStringContainsString('uninstalled=feedback', $this->locationOf($response));
        $this->assertDirectoryDoesNotExist($this->bundlesDir . '/feedback');
    }

    /**
     * Construit un zip qui imite un zipball GitHub : un unique dossier racine
     * contenant bundle.json + src/.
     */
    private function buildFixtureZip(): string
    {
        $manifest = [
            'slug'        => 'fake-bundle',
            'name'        => 'Fake Bundle',
            'version'     => '1.0.0',
            'namespace'   => 'kintai\\Bundles\\Installed\\FakeBundle',
            'entry_class' => 'kintai\\Bundles\\Installed\\FakeBundle\\FakeBundleBundle',
            'kintai_core' => ['min' => '0.1.0', 'max' => '0.9.0'],
        ];

        $zipPath = sys_get_temp_dir() . '/kintai-market-fixture-' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $root = 'AudricSan-kintai-bundle-fake-abc1234';
        $zip->addFromString("{$root}/bundle.json", json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->addFromString(
            "{$root}/src/FakeBundleBundle.php",
            "<?php\nnamespace kintai\\Bundles\\Installed\\FakeBundle;\nfinal class FakeBundleBundle {}\n",
        );
        $zip->close();

        return $zipPath;
    }

    private function locationOf(\kintai\Core\Response $response): string
    {
        $ref = new \ReflectionProperty($response, 'headers');
        $ref->setAccessible(true);
        return $ref->getValue($response)['Location'] ?? '';
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
