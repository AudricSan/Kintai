<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use PHPUnit\Framework\TestCase;

final class FakeIsActiveBundle extends Bundle
{
    public function getName(): string
    {
        return 'fake-bundle';
    }

    public function register(): void
    {
    }
}

/**
 * BundleManager::isActive() reflète l'état réel après boot (découvert ET activé),
 * contrairement à FeatureManager::isEnabled() qui ne reflète que le réglage stocké
 * — voir AuthMiddleware, qui s'est fait piéger par cet écart lors de l'extraction
 * du bundle Feedback hors du monorepo (le flag restait "activé" alors que le
 * bundle avait disparu du disque, faisant planter la modale de feedback).
 */
final class BundleManagerTest extends TestCase
{
    public function testIsActiveReturnsFalseWhenNoBundleWasRegistered(): void
    {
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();

        $this->assertFalse($manager->isActive('fake-bundle'));
    }

    public function testIsActiveReturnsTrueForARegisteredBundle(): void
    {
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();
        $bundle  = (new \ReflectionClass(FakeIsActiveBundle::class))->newInstanceWithoutConstructor();

        $bundlesProperty = new \ReflectionProperty(BundleManager::class, 'bundles');
        $bundlesProperty->setAccessible(true);
        $bundlesProperty->setValue($manager, [FakeIsActiveBundle::class => $bundle]);

        $this->assertTrue($manager->isActive('fake-bundle'));
        $this->assertFalse($manager->isActive('some-other-slug'));
    }
}
