<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Services\AppSettingsService;
use PHPUnit\Framework\TestCase;

final class AppSettingsServiceTest extends TestCase
{
    private function makeService(array $stored = []): AppSettingsService
    {
        $repo = $this->createStub(AppSettingsRepositoryInterface::class);
        $repo->method('all')->willReturn($stored);
        return new AppSettingsService($repo);
    }

    public function testUpdateChannelDefaultsToRelease(): void
    {
        $this->assertSame('release', $this->makeService()->updateChannel());
    }

    public function testUpdateChannelReadsStoredValue(): void
    {
        $this->assertSame('alpha', $this->makeService(['update_channel' => 'alpha'])->updateChannel());
        $this->assertSame('beta', $this->makeService(['update_channel' => 'beta'])->updateChannel());
    }

    public function testUpdateChannelRejectsUnknownValue(): void
    {
        $this->assertSame('release', $this->makeService(['update_channel' => 'nightly'])->updateChannel());
    }

    public function testMaintenanceModeDisabledByDefault(): void
    {
        $this->assertFalse($this->makeService()->maintenanceModeEnabled());
    }

    public function testMaintenanceModeReadsStoredValue(): void
    {
        $this->assertTrue($this->makeService(['maintenance_mode_enabled' => '1'])->maintenanceModeEnabled());
        $this->assertFalse($this->makeService(['maintenance_mode_enabled' => '0'])->maintenanceModeEnabled());
    }

    public function testMaintenanceMessageDefaultsToEmpty(): void
    {
        $this->assertSame('', $this->makeService()->maintenanceMessage());
    }

    public function testMaintenanceMessageReadsStoredValue(): void
    {
        $this->assertSame(
            'Maintenance en cours.',
            $this->makeService(['maintenance_message' => 'Maintenance en cours.'])->maintenanceMessage(),
        );
    }

    public function testPrimaryColorDefaultsToFoxyOrange(): void
    {
        $this->assertSame('#ff9f4a', $this->makeService()->primaryColor());
    }

    public function testPrimaryColorReadsStoredValue(): void
    {
        $this->assertSame('#6c5ce7', $this->makeService(['app_primary_color' => '#6c5ce7'])->primaryColor());
    }

    public function testPrimaryColorRejectsInvalidStoredValue(): void
    {
        $this->assertSame('#ff9f4a', $this->makeService(['app_primary_color' => 'not-a-color'])->primaryColor());
    }

    public function testPrimaryColorStyleContainsGeneratedTokens(): void
    {
        $style = $this->makeService(['app_primary_color' => '#6c5ce7'])->primaryColorStyle();

        $this->assertStringContainsString('--light-primary:#6c5ce7;', $style);
        $this->assertStringContainsString('--dark-primary:', $style);
    }
}
