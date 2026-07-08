<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Services\UpdateService;

final class UpdateServiceTest extends TestCase
{
    private string $tmpDir;
    private UpdateService $service;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/kintai_update_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/storage/app', 0775, true);
        putenv('KINTAI_STORAGE_PATH=' . $this->tmpDir . '/storage');

        $this->service = new UpdateService();
        $ref = new \ReflectionProperty(UpdateService::class, 'versionFile');
        $ref->setAccessible(true);
        $ref->setValue($this->service, $this->tmpDir . '/storage/app/version.json');
    }

    protected function tearDown(): void
    {
        $di = new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $fi = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($fi as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmpDir);
        putenv('KINTAI_STORAGE_PATH');
    }

    public function testDefaultVersionIs000(): void
    {
        $this->assertSame('0.0.0', $this->service->getCurrentVersion());
    }

    public function testGetInstalledAtReturnsNullWhenNoVersionFile(): void
    {
        $this->assertNull($this->service->getInstalledAt());
    }

    public function testSetVersionPersists(): void
    {
        $this->service->setVersion('1.2.3');
        $this->assertSame('1.2.3', $this->service->getCurrentVersion());
    }

    public function testSetVersionUpdatesUpdatedAt(): void
    {
        $this->service->setVersion('1.0.0');
        $this->assertNotNull($this->service->getUpdatedAt());
    }

    public function testMultipleVersionUpdates(): void
    {
        $this->service->setVersion('1.0.0');
        $this->service->setVersion('2.0.0');
        $this->assertSame('2.0.0', $this->service->getCurrentVersion());
    }

    public function testGetLastUpdateDurationReturnsNullByDefault(): void
    {
        $this->assertNull($this->service->getLastUpdateDuration());
    }

    public function testRecordUpdateDurationPersists(): void
    {
        $this->service->recordUpdateDuration(42);
        $this->assertSame(42, $this->service->getLastUpdateDuration());
    }

    public function testRecordUpdateDurationOverwritesPrevious(): void
    {
        $this->service->recordUpdateDuration(42);
        $this->service->recordUpdateDuration(7);
        $this->assertSame(7, $this->service->getLastUpdateDuration());
    }

    public function testRecordUpdateDurationDoesNotEraseVersion(): void
    {
        $this->service->setVersion('1.2.3');
        $this->service->recordUpdateDuration(15);
        $this->assertSame('1.2.3', $this->service->getCurrentVersion());
        $this->assertSame(15, $this->service->getLastUpdateDuration());
    }
}
