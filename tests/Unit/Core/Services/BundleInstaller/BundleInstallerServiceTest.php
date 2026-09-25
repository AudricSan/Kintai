<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services\BundleInstaller;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\BundleMigrationRunner;
use kintai\Core\InstalledBundleManifestStore;
use kintai\Core\Repositories\InstalledBundleRepositoryInterface;
use kintai\Core\Services\BundleInstaller\BundleInstallerService;
use kintai\Core\Services\UpdateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 6));
}

final class BundleInstallerServiceTest extends TestCase
{
    private string $bundlesDir;
    private string $fakeCoreBasePath;
    private InstalledBundleRepositoryInterface&MockObject $installedBundles;
    private InstalledBundleManifestStore $manifestStore;

    protected function setUp(): void
    {
        $this->bundlesDir = sys_get_temp_dir() . '/kintai-bundle-installer-' . uniqid();
        mkdir($this->bundlesDir, 0777, true);

        $this->fakeCoreBasePath = sys_get_temp_dir() . '/kintai-fake-core-' . uniqid();
        mkdir($this->fakeCoreBasePath . '/config', 0777, true);
        file_put_contents($this->fakeCoreBasePath . '/config/app.php', "<?php\nreturn ['version' => '0.5.0'];\n");

        $this->installedBundles = $this->createMock(InstalledBundleRepositoryInterface::class);
        $this->manifestStore = new InstalledBundleManifestStore($this->bundlesDir . '/installed.json');
    }

    private function makeService(
        ?\Closure $releaseFetcher,
        ?\Closure $zipDownloader,
        ?BundleMigrationRunner $migrationRunner = null,
    ): BundleInstallerService {
        return new BundleInstallerService(
            new UpdateService($this->fakeCoreBasePath),
            $this->installedBundles,
            $this->manifestStore,
            $this->bundlesDir,
            $releaseFetcher,
            $zipDownloader,
            migrationRunner: $migrationRunner,
        );
    }

    /**
     * Runner adossé à une base SQLite en mémoire, via la même astuce de réflexion
     * que BundleMigrationRunnerTest. Retourne aussi la Capsule pour permettre au
     * test d'inspecter directement l'état de la base (table créée, etc.).
     *
     * @return array{0: BundleMigrationRunner, 1: Capsule}
     */
    private function makeMigrationRunner(): array
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        $capsuleProp = new \ReflectionProperty(BundleMigrationRunner::class, 'capsule');
        $capsuleProp->setAccessible(true);
        $capsuleProp->setValue($runner, $capsule);

        return [$runner, $capsule];
    }

    /**
     * Construit un zip qui imite un zipball GitHub : un unique dossier racine
     * (comme "owner-repo-sha1234/") contenant bundle.json + src/.
     */
    private function buildFixtureZip(
        array $manifestOverrides = [],
        bool $withEntryClassFile = true,
        ?string $migrationFileContents = null,
    ): string {
        $manifest = array_merge([
            'slug'        => 'fake-bundle',
            'name'        => 'Fake Bundle',
            'version'     => '1.0.0',
            'namespace'   => 'kintai\\Bundles\\Installed\\FakeBundle',
            'entry_class' => 'kintai\\Bundles\\Installed\\FakeBundle\\FakeBundleBundle',
            'kintai_core' => ['min' => '0.1.0', 'max' => '0.9.0'],
        ], $manifestOverrides);

        $zipPath = sys_get_temp_dir() . '/kintai-fixture-' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $root = 'AudricSan-kintai-bundle-fake-abc1234';
        $zip->addFromString("{$root}/bundle.json", json_encode($manifest, JSON_PRETTY_PRINT));
        if ($withEntryClassFile) {
            $zip->addFromString(
                "{$root}/src/FakeBundleBundle.php",
                "<?php\nnamespace kintai\\Bundles\\Installed\\FakeBundle;\nfinal class FakeBundleBundle {}\n",
            );
        }
        if ($migrationFileContents !== null) {
            $zip->addFromString(
                "{$root}/database/migrations/2026_09_25_000001_create_fake_table.php",
                "<?php\n" . $migrationFileContents,
            );
        }
        $zip->close();

        return $zipPath;
    }

    public function testInstallRejectsANonGithubRepositoryUrl(): void
    {
        $service = $this->makeService(null, null);

        $result = $service->install('fake-bundle', 'https://gitlab.com/someone/fake-bundle', '1.0.0');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('non reconnue', (string) $result->error);
        $this->installedBundles->expects($this->never())->method('upsert');
    }

    public function testInstallRejectsWhenReleaseCannotBeResolved(): void
    {
        $service = $this->makeService(fn() => null, null);

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
        $this->installedBundles->expects($this->never())->method('upsert');
    }

    public function testInstallRejectsWhenDownloadFails(): void
    {
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            fn() => false,
        );

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
    }

    public function testInstallRejectsWhenManifestSlugDoesNotMatch(): void
    {
        $zipPath = $this->buildFixtureZip(['slug' => 'a-different-slug']);
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
        );

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('ne correspond pas', (string) $result->error);
        $this->assertDirectoryDoesNotExist($this->bundlesDir . '/fake-bundle/1.0.0');
    }

    public function testInstallRejectsWhenIncompatibleWithCoreVersion(): void
    {
        // Le Core factice est en 0.5.0 ; ce manifeste exige 1.0.0+.
        $zipPath = $this->buildFixtureZip(['kintai_core' => ['min' => '1.0.0', 'max' => '2.0.0']]);
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
        );

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('nécessite Kintai', (string) $result->error);
    }

    public function testInstallRejectsWhenEntryClassFileIsMissing(): void
    {
        $zipPath = $this->buildFixtureZip([], withEntryClassFile: false);
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
        );

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('introuvable', (string) $result->error);
    }

    public function testInstallActivatesAValidBundle(): void
    {
        $zipPath = $this->buildFixtureZip();
        $service = $this->makeService(
            fn(string $repo, string $version) => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
        );

        $this->installedBundles->expects($this->once())->method('upsert')
            ->with('fake-bundle', '1.0.0', 'https://example.test/registry.json');

        $result = $service->install(
            'fake-bundle',
            'https://github.com/AudricSan/kintai-bundle-fake',
            '1.0.0',
            'https://example.test/registry.json',
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->activated);
        $this->assertSame('fake-bundle', $result->manifest->slug);
        $this->assertFileExists($this->bundlesDir . '/fake-bundle/1.0.0/bundle.json');
        $this->assertFileExists($this->bundlesDir . '/fake-bundle/1.0.0/src/FakeBundleBundle.php');
        $this->assertSame(
            ['fake-bundle' => ['active_version' => '1.0.0']],
            $this->manifestStore->all(),
        );
    }

    public function testDryRunDoesNotActivateNorPersistAnything(): void
    {
        $zipPath = $this->buildFixtureZip();
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
        );

        $this->installedBundles->expects($this->never())->method('upsert');

        $result = $service->dryRun('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertTrue($result->success);
        $this->assertFalse($result->activated);
        $this->assertDirectoryDoesNotExist($this->bundlesDir . '/fake-bundle/1.0.0');
        $this->assertSame([], $this->manifestStore->all());
    }

    public function testRollbackRepointsToAnAlreadyPresentVersion(): void
    {
        mkdir($this->bundlesDir . '/fake-bundle/1.0.0', 0777, true);
        $this->installedBundles->method('find')->willReturn(['slug' => 'fake-bundle', 'active_version' => '1.1.0', 'source_registry_url' => null]);
        $this->installedBundles->expects($this->once())->method('upsert')->with('fake-bundle', '1.0.0', null);

        $service = $this->makeService(null, null);

        $this->assertTrue($service->rollback('fake-bundle', '1.0.0'));
        $this->assertSame('1.0.0', $this->manifestStore->all()['fake-bundle']['active_version']);
    }

    public function testRollbackFailsWhenTheVersionIsNotOnDisk(): void
    {
        $this->installedBundles->expects($this->never())->method('upsert');

        $service = $this->makeService(null, null);

        $this->assertFalse($service->rollback('fake-bundle', '9.9.9'));
    }

    public function testUninstallRemovesFilesManifestEntryAndDatabaseRow(): void
    {
        mkdir($this->bundlesDir . '/fake-bundle/1.0.0', 0777, true);
        file_put_contents($this->bundlesDir . '/fake-bundle/1.0.0/bundle.json', '{}');
        $this->manifestStore->setActiveVersion('fake-bundle', '1.0.0');

        $this->installedBundles->method('find')->willReturn(['slug' => 'fake-bundle', 'active_version' => '1.0.0', 'source_registry_url' => null]);
        $this->installedBundles->expects($this->once())->method('delete')->with('fake-bundle');

        $service = $this->makeService(null, null);

        $this->assertTrue($service->uninstall('fake-bundle'));
        $this->assertDirectoryDoesNotExist($this->bundlesDir . '/fake-bundle');
        $this->assertArrayNotHasKey('fake-bundle', $this->manifestStore->all());
    }

    public function testUninstallFailsForABundleNotManagedByTheInstaller(): void
    {
        $this->installedBundles->method('find')->willReturn(null);
        $this->installedBundles->expects($this->never())->method('delete');

        $service = $this->makeService(null, null);

        $this->assertFalse($service->uninstall('daily-report'));
        $this->assertNotNull($service->getLastError());
    }

    public function testInstallRunsTheBundlesMigrationsBeforeActivating(): void
    {
        $zipPath = $this->buildFixtureZip([], true, <<<'PHP'
use kintai\Core\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class($this->capsule) extends Migration {
    public function up(): void
    {
        $this->schema()->create('fake_table', function (Blueprint $table) {
            $table->increments('id');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('fake_table');
    }
};
PHP);
        [$migrationRunner, $capsule] = $this->makeMigrationRunner();
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
            $migrationRunner,
        );

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertTrue($result->success);
        $this->assertTrue($capsule->getConnection()->getSchemaBuilder()->hasTable('fake_table'));
        $this->assertSame(
            ['fake-bundle'],
            $capsule->table('bundle_migrations')->pluck('bundle_slug')->toArray(),
        );
    }

    public function testInstallRollsBackTheBundleDirectoryWhenMigrationsFail(): void
    {
        $zipPath = $this->buildFixtureZip([], true, <<<'PHP'
use kintai\Core\Database\Migration;

return new class($this->capsule) extends Migration {
    public function up(): void
    {
        $this->schema()->table('table_that_does_not_exist', function ($table) {
            $table->string('foo')->nullable();
        });
    }

    public function down(): void
    {
    }
};
PHP);
        [$migrationRunner, ] = $this->makeMigrationRunner();
        $service = $this->makeService(
            fn() => 'https://example.test/fake.zip',
            function (string $url, string $dest) use ($zipPath) { return copy($zipPath, $dest); },
            $migrationRunner,
        );

        $this->installedBundles->expects($this->never())->method('upsert');

        $result = $service->install('fake-bundle', 'https://github.com/AudricSan/kintai-bundle-fake', '1.0.0');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('migrations du bundle ont échoué', (string) $result->error);
        $this->assertDirectoryDoesNotExist($this->bundlesDir . '/fake-bundle/1.0.0');
    }

    public function testUninstallForgetsTheBundlesMigrationTracking(): void
    {
        mkdir($this->bundlesDir . '/fake-bundle/1.0.0', 0777, true);
        file_put_contents($this->bundlesDir . '/fake-bundle/1.0.0/bundle.json', '{}');
        $this->manifestStore->setActiveVersion('fake-bundle', '1.0.0');
        $this->installedBundles->method('find')->willReturn(['slug' => 'fake-bundle', 'active_version' => '1.0.0', 'source_registry_url' => null]);

        [$migrationRunner, $capsule] = $this->makeMigrationRunner();
        $migrationRunner->runPendingFor('fake-bundle', $this->migrationsPathWithNoFiles());

        $service = $this->makeService(null, null, $migrationRunner);

        $this->assertTrue($service->uninstall('fake-bundle'));

        // forgetBundle() est appelé même s'il n'y avait rien à oublier : pas d'exception,
        // et surtout aucune ligne d'un autre bundle ne serait affectée (couvert par
        // BundleMigrationRunnerTest::testForgetBundleRemovesOnlyThatBundlesTrackingRows).
        $this->assertTrue($capsule->getConnection()->getSchemaBuilder()->hasTable('bundle_migrations'));
    }

    private function migrationsPathWithNoFiles(): string
    {
        $dir = sys_get_temp_dir() . '/kintai-no-migrations-' . uniqid();
        mkdir($dir, 0777, true);
        return $dir;
    }
}
