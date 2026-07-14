<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\BackupService;
use kintai\Core\Services\GithubUpdateService;
use kintai\Core\Services\UpdateService;
use kintai\UI\Controller\Web\System\BackupController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class BackupControllerTest extends TestCase
{
    private string $tmpDir;
    private UpdateService $updateService;
    private BackupService $backup;
    private MigrationRunner $migrator;
    private AppSettingsService $settings;
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/kintai_backupctrl_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/storage/app', 0775, true);
        mkdir($this->tmpDir . '/storage/backups', 0775, true);
        mkdir($this->tmpDir . '/storage/uploads', 0775, true);
        mkdir($this->tmpDir . '/config', 0775, true);

        putenv('KINTAI_STORAGE_PATH=' . $this->tmpDir . '/storage');

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        mkdir($this->tmpDir . '/no-migrations', 0775, true);
        $this->migrator = (new \ReflectionClass(MigrationRunner::class))->newInstanceWithoutConstructor();
        $this->setPrivate($this->migrator, 'capsule', $capsule);
        $this->setPrivate($this->migrator, 'migrationsPath', $this->tmpDir . '/no-migrations');

        $this->updateService = new UpdateService($this->tmpDir);
        $this->setPrivate($this->updateService, 'versionFile', $this->tmpDir . '/storage/app/version.json');

        $this->backup = new BackupService($capsule);
        $this->setPrivate($this->backup, 'backupDir', $this->tmpDir . '/storage/backups');

        $settingsRepo = $this->createStub(AppSettingsRepositoryInterface::class);
        $settingsRepo->method('get')->willReturn(null);
        $settingsRepo->method('all')->willReturn([]);
        $this->settings = new AppSettingsService($settingsRepo);

        $this->view = new ViewRenderer(sys_get_temp_dir());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        putenv('KINTAI_STORAGE_PATH');
    }

    private function setPrivate(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionProperty($obj, $prop);
        $ref->setAccessible(true);
        $ref->setValue($obj, $value);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    /** La version courante est lue depuis config/app.php (voir UpdateService::getCurrentVersion). */
    private function writeAppVersion(string $version): void
    {
        file_put_contents(
            $this->tmpDir . '/config/app.php',
            '<?php return [\'version\' => ' . var_export($version, true) . '];'
        );
    }

    private function makeReleaseZip(string $destZip, array $files): void
    {
        $zip = new \ZipArchive();
        $zip->open($destZip, \ZipArchive::CREATE);
        $root = 'AudricSan-Kintai-abcdef1';
        foreach ($files as $relative => $content) {
            $zip->addFromString($root . '/' . $relative, $content);
        }
        $zip->close();
    }

    private function makeController(?\Closure $releaseFetcher = null, ?\Closure $zipDownloader = null): BackupController
    {
        $githubUpdate = new GithubUpdateService(
            $this->updateService,
            $this->backup,
            $this->migrator,
            $this->settings,
            $this->tmpDir,
            $releaseFetcher,
            $zipDownloader,
        );

        return new BackupController($this->view, $this->backup, $this->updateService, $githubUpdate, $this->migrator, $this->settings);
    }

    private function requestAs(bool $isAdmin): Request
    {
        $request = new Request();
        $request->setAttribute('auth_user', ['id' => 1, 'is_admin' => $isAdmin]);
        return $request;
    }

    private function locationOf(\kintai\Core\Response $response): string
    {
        $ref = new \ReflectionProperty($response, 'headers');
        $ref->setAccessible(true);
        return $ref->getValue($response)['Location'] ?? '';
    }

    public function testCreateRejectsNonOwner(): void
    {
        $controller = $this->makeController();

        $this->expectException(ForbiddenException::class);
        $controller->create($this->requestAs(false));
    }

    public function testCreateAllowsOwner(): void
    {
        $controller = $this->makeController();

        $response = $controller->create($this->requestAs(true));

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('success=created_', $this->locationOf($response));
    }

    public function testUpdateRejectsNonOwner(): void
    {
        $controller = $this->makeController();

        $this->expectException(ForbiddenException::class);
        $controller->update($this->requestAs(false));
    }

    public function testUpdateReturnsErrorWhenNoUpdateAvailable(): void
    {
        $this->writeAppVersion('1.0.0');
        $controller = $this->makeController(
            fn(string $repo, string $token): ?array => [[
                'tag_name'    => 'v1.0.0',
                'zipball_url' => 'https://example.test/zipball/v1.0.0',
            ]],
        );

        $response = $controller->update($this->requestAs(true));

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('success=error_no_update_available', $this->locationOf($response));
    }

    public function testUpdateAppliesReleaseAndRedirectsWithSummary(): void
    {
        $this->writeAppVersion('1.0.0');
        $controller = $this->makeController(
            fn(string $repo, string $token): ?array => [[
                'tag_name'    => 'v2.0.0',
                'zipball_url' => 'https://example.test/zipball/v2.0.0',
                'html_url'    => 'https://example.test/releases/v2.0.0',
                'body'        => 'notes',
                'published_at' => '2026-01-01T00:00:00Z',
            ]],
            function (string $url, string $dest, string $token): bool {
                $this->makeReleaseZip($dest, [
                    'README.md'    => 'v2',
                    'config/app.php' => "<?php return ['version' => '2.0.0'];",
                ]);
                return true;
            },
        );

        $response = $controller->update($this->requestAs(true));

        $this->assertSame(302, $response->status());
        $location = $this->locationOf($response);
        $this->assertStringStartsWith('/admin/update?success=updated_2.0.0_files-2_deleted-0', $location);
        $this->assertSame('2.0.0', $this->updateService->getCurrentVersion());
        $this->assertFileExists($this->tmpDir . '/README.md');
        $this->assertFalse($this->settings->maintenanceModeEnabled());
    }

    public function testUpdateEnablesMaintenanceModeDuringApplyThenDisablesIt(): void
    {
        $this->writeAppVersion('1.0.0');
        $enabledDuringApply = null;
        $controller = $this->makeController(
            fn(string $repo, string $token): ?array => [[
                'tag_name'    => 'v2.0.0',
                'zipball_url' => 'https://example.test/zipball/v2.0.0',
            ]],
            function (string $url, string $dest, string $token) use (&$enabledDuringApply): bool {
                $enabledDuringApply = $this->settings->maintenanceModeEnabled();
                $this->makeReleaseZip($dest, ['config/app.php' => "<?php return ['version' => '2.0.0'];"]);
                return true;
            },
        );

        $this->assertFalse($this->settings->maintenanceModeEnabled());

        $controller->update($this->requestAs(true));

        $this->assertTrue($enabledDuringApply);
        $this->assertFalse($this->settings->maintenanceModeEnabled());
    }

    public function testUpdateLeavesMaintenanceModeOnIfAlreadyEnabledBeforehand(): void
    {
        $this->writeAppVersion('1.0.0');
        $this->settings->setMany(['maintenance_mode_enabled' => '1']);
        $controller = $this->makeController(
            fn(string $repo, string $token): ?array => [[
                'tag_name'    => 'v2.0.0',
                'zipball_url' => 'https://example.test/zipball/v2.0.0',
            ]],
            function (string $url, string $dest, string $token): bool {
                $this->makeReleaseZip($dest, ['config/app.php' => "<?php return ['version' => '2.0.0'];"]);
                return true;
            },
        );

        $controller->update($this->requestAs(true));

        $this->assertTrue($this->settings->maintenanceModeEnabled());
    }

    public function testMigrateRejectsNonOwner(): void
    {
        $controller = $this->makeController();

        $this->expectException(ForbiddenException::class);
        $controller->migrate($this->requestAs(false));
    }

    public function testMigrateRedirectsToUpdatePage(): void
    {
        $controller = $this->makeController();

        $response = $controller->migrate($this->requestAs(true));

        $this->assertSame(302, $response->status());
        $this->assertStringStartsWith('/admin/update?success=migrated', $this->locationOf($response));
        $this->assertFalse($this->settings->maintenanceModeEnabled());
    }

    public function testSaveChannelRejectsNonOwner(): void
    {
        $controller = $this->makeController();

        $this->expectException(ForbiddenException::class);
        $controller->saveChannel($this->requestAs(false));
    }

    public function testSaveChannelPersistsValidChannel(): void
    {
        $controller = $this->makeController();

        $_POST = ['channel' => 'alpha'];
        $response = $controller->saveChannel($this->requestAs(true));
        $_POST = [];

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('success=channel_alpha', $this->locationOf($response));
        $this->assertSame('alpha', $this->settings->updateChannel());
    }

    public function testSaveChannelFallsBackToReleaseForInvalidValue(): void
    {
        $controller = $this->makeController();

        $_POST = ['channel' => 'nightly'];
        $response = $controller->saveChannel($this->requestAs(true));
        $_POST = [];

        $this->assertStringContainsString('success=channel_release', $this->locationOf($response));
        $this->assertSame('release', $this->settings->updateChannel());
    }
}
