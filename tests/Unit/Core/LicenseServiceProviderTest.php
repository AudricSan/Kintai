<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Container;
use kintai\Core\FeatureManager;
use kintai\Core\LicenseServiceProvider;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;

// BASE_PATH est requis par LicenseServiceProvider (fallback vers config/license.php).
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

final class LicenseServiceProviderTest extends TestCase
{
    public function testUsesEnabledBundlesFromAppSettingsWhenPresent(): void
    {
        $container = new Container();
        $appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
        $appSettings->method('get')->with(LicenseServiceProvider::SETTINGS_KEY)
            ->willReturn('["daily-report","store-photos"]');
        $container->instance(AppSettingsRepositoryInterface::class, $appSettings);

        (new LicenseServiceProvider($container))->register();

        $features = $container->make(FeatureManager::class);
        $this->assertTrue($features->isEnabled('daily-report'));
        $this->assertTrue($features->isEnabled('store-photos'));
        $this->assertFalse($features->isEnabled('messaging'));
    }

    public function testFallsBackToConfigFileWhenNoAppSettingsValueStored(): void
    {
        $container = new Container();
        $appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
        $appSettings->method('get')->with(LicenseServiceProvider::SETTINGS_KEY)->willReturn(null);
        $container->instance(AppSettingsRepositoryInterface::class, $appSettings);

        (new LicenseServiceProvider($container))->register();

        $features = $container->make(FeatureManager::class);
        // config/license.php (celui du dépôt de test) active daily-report + messaging par défaut.
        $this->assertTrue($features->isEnabled('daily-report'));
        $this->assertTrue($features->isEnabled('messaging'));
    }
}
