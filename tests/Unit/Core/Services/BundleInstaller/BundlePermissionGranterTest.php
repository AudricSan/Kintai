<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services\BundleInstaller;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Services\BundleInstaller\BundlePermissionGranter;
use PHPUnit\Framework\TestCase;

final class BundlePermissionGranterTest extends TestCase
{
    public function testDeclaredPermissionsKeepsOnlyWellFormedNames(): void
    {
        $this->assertSame(
            ['photos' => ['view', 'upload']],
            BundlePermissionGranter::declaredPermissions(GranterFixtureBundle::class),
        );
    }

    public function testGrantAddsOnlyMissingKeysAndKeepsTheGlobalScope(): void
    {
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findBySlug')->with('manager')->willReturn(['id' => 7, 'slug' => 'manager']);
        $roles->method('getPermissions')->with(7)->willReturn(['photos.view', 'shifts.view']);
        $roles->method('getGlobalPermissionKeys')->with(7)->willReturn(['shifts.view']);
        $roles->expects($this->once())->method('savePermissions')
            ->with(7, ['photos.view', 'shifts.view', 'photos.upload'], ['shifts.view']);

        $granted = (new BundlePermissionGranter($roles))->grantToManager(['photos' => ['view', 'upload']]);

        $this->assertSame(['photos.upload'], $granted);
    }

    public function testGrantSavesNothingWhenTheManagerAlreadyHasEverything(): void
    {
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findBySlug')->willReturn(['id' => 7, 'slug' => 'manager']);
        $roles->method('getPermissions')->willReturn(['photos.view']);
        $roles->expects($this->never())->method('savePermissions');

        $this->assertSame([], (new BundlePermissionGranter($roles))->grantToManager(['photos' => ['view']]));
    }

    public function testGrantDoesNothingWithoutAManagerRole(): void
    {
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findBySlug')->willReturn(null);
        $roles->expects($this->never())->method('savePermissions');

        $this->assertSame([], (new BundlePermissionGranter($roles))->grantToManager(['photos' => ['view']]));
    }
}

/** Bundle factice : une catégorie valide (avec une action mal formée) et une catégorie au nom invalide. */
final class GranterFixtureBundle extends Bundle
{
    public function getName(): string
    {
        return 'granter-fixture';
    }

    public function register(): void
    {
    }

    public function permissions(): array
    {
        return ['photos' => ['view', 'upload', 'Bad Action', 'view'], 'Bad-Category' => ['view'], 'empty' => []];
    }
}
