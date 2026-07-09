<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Bundle;
use kintai\Core\BundleDiscoveryService;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

final class BundleDiscoveryServiceTest extends TestCase
{
    public function testDiscoversRealBundlesShippedWithTheRepo(): void
    {
        $service = new BundleDiscoveryService();

        $discovered = $service->discover();

        $this->assertArrayHasKey('daily-report', $discovered);
        $this->assertArrayHasKey('messaging', $discovered);
        $this->assertNotSame('', $discovered['daily-report']['label']);
        $this->assertNotSame('', $discovered['messaging']['label']);
        $this->assertTrue(is_subclass_of($discovered['daily-report']['class'], Bundle::class));
    }

    public function testIgnoresDirectoriesWithNoMatchingBundleClass(): void
    {
        $dir = sys_get_temp_dir() . '/kintai-bundle-discovery-' . uniqid();
        mkdir($dir . '/NotABundle', 0777, true);
        // Pas de fichier NotABundleBundle.php dedans -> doit être ignoré silencieusement.
        file_put_contents($dir . '/a-stray-file.txt', 'noop');

        $service = new BundleDiscoveryService($dir);

        $this->assertSame([], $service->discover());

        unlink($dir . '/a-stray-file.txt');
        rmdir($dir . '/NotABundle');
        rmdir($dir);
    }

    public function testReturnsEmptyArrayWhenBundlesDirDoesNotExist(): void
    {
        $service = new BundleDiscoveryService(sys_get_temp_dir() . '/kintai-does-not-exist-' . uniqid());

        $this->assertSame([], $service->discover());
    }
}
