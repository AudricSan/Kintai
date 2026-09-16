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

    public function testThemeColorDefaultsToFoxyPalette(): void
    {
        $this->assertSame('#ff9f4a', $this->makeService()->themeColor('primary'));
        $this->assertSame('#4caf50', $this->makeService()->themeColor('accent'));
    }

    public function testThemeColorReadsStoredValue(): void
    {
        $this->assertSame('#6c5ce7', $this->makeService(['app_primary_color' => '#6c5ce7'])->themeColor('primary'));
    }

    public function testThemeColorRejectsInvalidStoredValue(): void
    {
        $this->assertSame('#ff9f4a', $this->makeService(['app_primary_color' => 'not-a-color'])->themeColor('primary'));
    }

    public function testThemeColorDarkDefaultsToEmptyMeaningAutoMode(): void
    {
        $this->assertSame('', $this->makeService()->themeColorDark('primary'));
    }

    public function testThemeColorDarkReadsStoredValue(): void
    {
        $this->assertSame('#112233', $this->makeService(['app_primary_color_dark' => '#112233'])->themeColorDark('primary'));
    }

    public function testThemeDarkModeDefaultsToAuto(): void
    {
        $this->assertSame('auto', $this->makeService()->themeDarkMode());
    }

    public function testThemeDarkModeReadsManual(): void
    {
        $this->assertSame('manual', $this->makeService(['app_theme_dark_mode' => 'manual'])->themeDarkMode());
    }

    public function testThemeDarkModeRejectsUnknownValue(): void
    {
        $this->assertSame('auto', $this->makeService(['app_theme_dark_mode' => 'nightly'])->themeDarkMode());
    }

    public function testThemeColorStyleContainsGeneratedTokensForACustomColor(): void
    {
        $style = $this->makeService(['app_primary_color' => '#6c5ce7'])->themeColorStyle();

        $this->assertStringContainsString('--light-primary:#6c5ce7;', $style);
        $this->assertStringContainsString('--dark-primary:', $style);
    }

    public function testThemeColorStyleIsEmptyWhenEverythingIsAtItsDefault(): void
    {
        $this->assertSame('', $this->makeService()->themeColorStyle());
    }

    public function testThemeColorStyleUsesManualDarkColorWhenModeIsManual(): void
    {
        $style = $this->makeService([
            'app_theme_dark_mode'  => 'manual',
            'app_danger_color_dark' => '#112233',
        ])->themeColorStyle();

        $this->assertStringContainsString('--dark-danger:#112233;', $style);
    }
}
