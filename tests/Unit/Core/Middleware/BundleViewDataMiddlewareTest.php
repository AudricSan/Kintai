<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Middleware;

use kintai\Core\Auth\PermissionService;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Extension\ExtensionRegistry;
use kintai\Core\Middleware\BundleViewDataMiddleware;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Services\DailyReportPermissionService;
use PHPUnit\Framework\TestCase;

/**
 * Variables de vue des bundles (étape 2g du contrat de bundle) : déclarations des bundles actifs
 * (Bundle::viewVariables(), contrat 1.5), noms réservés, isolation, repli historique daily-report/messaging.
 */
final class BundleViewDataMiddlewareTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /** @param array<string, mixed> $active slug => variables déclarées (array, ou \Throwable levée) */
    private function middleware(array $active): BundleViewDataMiddleware
    {
        $bundles = [];
        foreach ($active as $slug => $declared) {
            $bundle = new class extends Bundle {
                public string $slug = '';
                public mixed $declared = [];

                public function __construct() {}

                public function getName(): string
                {
                    return $this->slug;
                }

                public function register(): void {}

                public function viewVariables(): array
                {
                    if ($this->declared instanceof \Throwable) {
                        throw $this->declared;
                    }
                    return $this->declared;
                }
            };
            $bundle->slug = $slug;
            $bundle->declared = $declared;
            $bundles['B' . $slug] = $bundle;
        }
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($manager, 'bundles'))->setValue($manager, $bundles);
        $this->container->instance(ExtensionRegistry::class, new ExtensionRegistry($manager));

        return new BundleViewDataMiddleware($this->container);
    }

    private function bindMessages(int $unread): void
    {
        $messages = $this->createMock(MessageRepositoryInterface::class);
        $messages->method('countUnread')->with(7)->willReturn($unread);
        $this->container->instance(MessageRepositoryInterface::class, $messages);
    }

    private function bindDailyReports(): void
    {
        $storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsers->method('findByUser')->with(7)->willReturn([['store_id' => 1], ['store_id' => 2]]);
        $stores = $this->createMock(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturnCallback(fn(int $id): array => ['id' => $id, 'name' => 'S' . $id]);
        // Rôle de magasin accordant daily_reports.create sur le magasin 2 uniquement.
        $assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([['role_id' => 5, 'scope_type' => 'store', 'scope_id' => 2]]);
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 5, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['daily_reports.create']);
        $permissions = new DailyReportPermissionService(new PermissionService($assignments, $roles));
        $this->container->instance(StoreUserRepositoryInterface::class, $storeUsers);
        $this->container->instance(StoreRepositoryInterface::class, $stores);
        $this->container->instance(DailyReportPermissionService::class, $permissions);
    }

    /** Aucun bundle ne déclare : mêmes variables qu'avec DailyReportNavMiddleware et MessageCountMiddleware. */
    public function testLegacyFallbackMatchesTheFormerMiddlewares(): void
    {
        $this->bindMessages(3);
        $this->bindDailyReports();
        $mw = $this->middleware(['messaging' => [], 'daily-report' => []]);

        $this->assertSame(
            ['daily_report_staff_stores' => [['id' => 2, 'name' => 'S2']], 'unread_messages_count' => 3],
            $mw->variables(['id' => 7], false)
        );
        $this->assertSame([], $mw->variables(['id' => 7], true)['daily_report_staff_stores'], 'gérant : liste vide');
    }

    public function testFallbackSurvivesMissingBundleServices(): void
    {
        $mw = $this->middleware([]);

        $this->assertSame(['daily_report_staff_stores' => [], 'unread_messages_count' => 0], $mw->variables(['id' => 7], false));
    }

    public function testDeclaringBundleReplacesItsFallback(): void
    {
        $this->bindMessages(3);
        $mw = $this->middleware([
            'messaging' => ['unread_messages_count' => fn(array $u): int => 40 + $u['id']],
            'acme'      => [
                'acme_badge'      => fn(): string => 'ok',
                'auth_user'       => fn(): string => 'usurpé',
                'unread_messages_count' => fn(): int => 999,
                'acme_broken'     => fn() => throw new \RuntimeException('kaput'),
                'Bad Name'        => fn(): int => 1,
                'acme_not_closure' => 'valeur',
            ],
            'boom'      => new \RuntimeException('kaput'),
        ]);

        $vars = $mw->variables(['id' => 7], true);

        $this->assertSame(47, $vars['unread_messages_count']);
        $this->assertSame('ok', $vars['acme_badge']);
        $this->assertArrayNotHasKey('auth_user', $vars);
        $this->assertArrayNotHasKey('acme_broken', $vars);
        $this->assertArrayNotHasKey('acme_not_closure', $vars);
        $this->assertSame([], $vars['daily_report_staff_stores'], 'daily-report ne déclare rien : repli conservé');
    }
}
