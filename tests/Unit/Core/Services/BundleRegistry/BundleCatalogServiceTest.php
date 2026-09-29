<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services\BundleRegistry;

use kintai\Core\Repositories\BundleRegistryRepositoryInterface;
use kintai\Core\Services\BundleRegistry\BundleCatalogService;
use kintai\Core\Services\BundleRegistry\BundleRegistryClient;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 5));
}

final class BundleCatalogServiceTest extends TestCase
{
    public function testListAvailableBundlesAggregatesEveryActiveRegistry(): void
    {
        $registries = $this->createMock(BundleRegistryRepositoryInterface::class);
        $registries->method('all')->willReturn([
            ['id' => 1, 'name' => 'Registry officiel', 'url' => 'https://example.test/official.json', 'is_official' => true],
            ['id' => 2, 'name' => 'Registry perso',     'url' => 'https://example.test/perso.json',   'is_official' => false],
        ]);

        $bodies = [
            'https://example.test/official.json' => json_encode([
                'schema_version' => 1,
                'name'           => 'Registry officiel',
                'bundles'        => [[
                    'slug' => 'feedback', 'repository_url' => 'https://github.com/AudricSan/kintai-bundle-feedback',
                ]],
            ]),
            'https://example.test/perso.json' => json_encode([
                'schema_version' => 1,
                'name'           => 'Registry perso',
                'bundles'        => [[
                    'slug' => 'custom-bundle', 'repository_url' => 'https://github.com/someone/custom-bundle',
                ]],
            ]),
        ];

        $client = new BundleRegistryClient(fn(string $url) => $bodies[$url] ?? null);
        $service = new BundleCatalogService($registries, $client);

        $catalog = $service->listAvailableBundles();

        $this->assertCount(2, $catalog);
        $this->assertSame('feedback', $catalog[0]->bundle->slug);
        $this->assertSame('Registry officiel', $catalog[0]->registryName);
        $this->assertSame('custom-bundle', $catalog[1]->bundle->slug);
        $this->assertSame('Registry perso', $catalog[1]->registryName);
    }

    public function testListAvailableBundlesSkipsARegistryThatFailsToRespond(): void
    {
        $registries = $this->createMock(BundleRegistryRepositoryInterface::class);
        $registries->method('all')->willReturn([
            ['id' => 1, 'name' => 'Registry indisponible', 'url' => 'https://example.test/down.json', 'is_official' => false],
        ]);

        $client = new BundleRegistryClient(fn(string $url) => null);
        $service = new BundleCatalogService($registries, $client);

        $this->assertSame([], $service->listAvailableBundles());
    }

    public function testIsOfficialUsesTheExistingOfficialBundlesConfig(): void
    {
        $service = new BundleCatalogService(
            $this->createMock(BundleRegistryRepositoryInterface::class),
            new BundleRegistryClient(fn(string $url) => null),
        );

        $this->assertTrue($service->isOfficial('feedback'));
        $this->assertFalse($service->isOfficial('some-random-third-party-bundle'));
    }
}
