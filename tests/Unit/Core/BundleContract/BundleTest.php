<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\BundleContract;

use kintai\Core\Bundle as LegacyBundle;
use kintai\Core\BundleContract\Bundle;
use PHPUnit\Framework\TestCase;

final class BundleTest extends TestCase
{
    public function testLegacyBundleClassExtendsTheStableContract(): void
    {
        $this->assertTrue(is_subclass_of(LegacyBundle::class, Bundle::class));
    }

    public function testGetVersionDefaultsToZeroForBundlesWithoutAManifest(): void
    {
        $bundle = (new \ReflectionClass(FakeBundle::class))->newInstanceWithoutConstructor();

        $this->assertSame('0.0.0', $bundle->getVersion());
    }

    public function testGetVersionCanBeOverriddenByAnInstalledBundle(): void
    {
        $bundle = (new \ReflectionClass(FakeVersionedBundle::class))->newInstanceWithoutConstructor();

        $this->assertSame('1.2.0', $bundle->getVersion());
    }

    public function testGetLabelDefaultsToATitleisedSlug(): void
    {
        $bundle = (new \ReflectionClass(FakeBundle::class))->newInstanceWithoutConstructor();

        $this->assertSame('Fake Bundle', $bundle->getLabel());
    }
}

final class FakeBundle extends Bundle
{
    public function getName(): string
    {
        return 'fake-bundle';
    }

    public function register(): void
    {
    }
}

final class FakeVersionedBundle extends Bundle
{
    public function getName(): string
    {
        return 'fake-versioned-bundle';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function register(): void
    {
    }
}
