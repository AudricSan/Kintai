<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services\BundleRegistry;

use kintai\Core\Services\BundleRegistry\BundleRegistryClient;
use PHPUnit\Framework\TestCase;

final class BundleRegistryClientTest extends TestCase
{
    public function testFetchListingParsesAValidRegistry(): void
    {
        $body = json_encode([
            'schema_version' => 1,
            'name'           => 'Registry officiel Kintai',
            'bundles'        => [
                [
                    'slug'           => 'feedback',
                    'name'           => 'Retours utilisateurs',
                    'description'    => 'Formulaire de feedback employé.',
                    'repository_url' => 'https://github.com/AudricSan/kintai-bundle-feedback',
                    'versions'       => ['1.0.0'],
                ],
            ],
        ]);

        $client = new BundleRegistryClient(fn(string $url) => $body);

        $listing = $client->fetchListing('https://example.test/registry.json');

        $this->assertNotNull($listing);
        $this->assertSame('Registry officiel Kintai', $listing->name);
        $this->assertCount(1, $listing->bundles);
        $this->assertSame('feedback', $listing->bundles[0]->slug);
        $this->assertSame(['1.0.0'], $listing->bundles[0]->versions);
    }

    public function testFetchListingReturnsNullWhenFetchFails(): void
    {
        $client = new BundleRegistryClient(fn(string $url) => null);

        $this->assertNull($client->fetchListing('https://example.test/registry.json'));
    }

    public function testFetchListingReturnsNullOnInvalidJson(): void
    {
        $client = new BundleRegistryClient(fn(string $url) => 'not json at all');

        $this->assertNull($client->fetchListing('https://example.test/registry.json'));
    }

    public function testFetchListingRejectsUnsupportedSchemaVersion(): void
    {
        $body = json_encode(['schema_version' => 2, 'name' => 'Futur registry', 'bundles' => []]);

        $client = new BundleRegistryClient(fn(string $url) => $body);

        $this->assertNull($client->fetchListing('https://example.test/registry.json'));
    }

    public function testFetchListingSkipsEntriesMissingRequiredFields(): void
    {
        $body = json_encode([
            'schema_version' => 1,
            'name'           => 'Registry',
            'bundles'        => [
                ['name' => 'Sans slug ni repository_url'],
                [
                    'slug'           => 'valid-bundle',
                    'repository_url' => 'https://github.com/AudricSan/kintai-bundle-valid',
                ],
            ],
        ]);

        $client = new BundleRegistryClient(fn(string $url) => $body);

        $listing = $client->fetchListing('https://example.test/registry.json');

        $this->assertNotNull($listing);
        $this->assertCount(1, $listing->bundles);
        $this->assertSame('valid-bundle', $listing->bundles[0]->slug);
        // Le nom manquant retombe sur le slug.
        $this->assertSame('valid-bundle', $listing->bundles[0]->name);
        $this->assertSame([], $listing->bundles[0]->versions);
    }
}
