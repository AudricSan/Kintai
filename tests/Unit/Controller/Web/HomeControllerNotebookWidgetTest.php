<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\DashboardAlertService;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\Tests\Support\FakeBundleManagerFactory;
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Le widget "team_notes" du dashboard admin suit exactement le même pattern
 * de résolution différée que "open_shifts" (voir HomeControllerPendingClaimsTest) :
 * NotebookEntryRepositoryInterface n'est lié au container que si le bundle
 * "notebook" est actif — HomeController ne doit jamais le demander en
 * dépendance de constructeur.
 */
final class HomeControllerNotebookWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        $this->ensureViewFile('dashboard.index', '<?php ?>');
        $this->ensureViewFile('layout.app', "<?php echo json_encode(['notebook_entries' => \$notebook_entries ?? null]);");
    }

    protected function tearDown(): void
    {
        $instance = new \ReflectionProperty(Container::class, 'instance');
        $instance->setValue(null, null);
    }

    private function unbindNotebook(): void
    {
        $ref = new \ReflectionProperty(Container::class, 'instances');
        $ref->setAccessible(true);
        $instances = $ref->getValue(Container::getInstance());
        unset($instances[NotebookEntryRepositoryInterface::class]);
        $ref->setValue(Container::getInstance(), $instances);
    }

    /** @param string[] $enabledWidgets */
    private function buildController(array $enabledWidgets, bool $grantNotebookView): HomeController
    {
        $usersRepo = $this->createMock(UserRepositoryInterface::class);
        $usersRepo->method('findAll')->willReturn([]);
        $usersRepo->method('findById')->willReturn(['id' => 5, 'first_name' => 'Jean', 'last_name' => 'Dupont']);

        $storesRepo = $this->createMock(StoreRepositoryInterface::class);
        $storesRepo->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1']]);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findAllByDate')->willReturn([]);

        $shiftTypesRepo = $this->createMock(ShiftTypeRepositoryInterface::class);

        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findAll')->willReturn([]);

        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findAll')->willReturn([]);

        $timeclocksRepo = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocksRepo->method('findAll')->willReturn([]);

        $dashboardPrefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $dashboardPrefs->method('getEnabledWidgets')->willReturn($enabledWidgets);

        $storeUsersRepo = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsersRepo->method('findByStore')->willReturn([]);

        $storeStats = $this->createMock(StoreStatsServiceInterface::class);

        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn(
            $grantNotebookView ? [['role_id' => 1, 'scope_type' => 'global', 'scope_id' => null]] : []
        );
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 1, 'is_system' => 1]);
        $permissions = new PermissionService($roleAssignments, $roles);

        $dashboardAlerts = new DashboardAlertService(
            $shiftsRepo, $usersRepo, $storeUsersRepo, $timeoffRepo, $swapsRepo, $timeclocksRepo,
        );

        return new HomeController(
            new ViewRenderer(sys_get_temp_dir()),
            $usersRepo,
            $storesRepo,
            $shiftsRepo,
            $shiftTypesRepo,
            $timeoffRepo,
            $swapsRepo,
            $dashboardPrefs,
            $timeclocksRepo,
            $storeUsersRepo,
            $storeStats,
            $dashboardAlerts,
            $permissions,
        );
    }

    private function request(): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 99, 'is_admin' => 1]);
        $req->setAttribute('managed_store_ids', null);
        return $req;
    }

    public function testDashboardDoesNotCrashAndReturnsNoEntriesWhenNotebookBundleDisabled(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['timeoff']));
        $this->unbindNotebook();

        $controller = $this->buildController(['kpi_counters', 'team_notes'], grantNotebookView: true);
        $response   = $controller->index($this->request());
        $data       = json_decode($response->body(), true);

        $this->assertSame([], $data['notebook_entries']);
    }

    public function testDashboardReturnsPinnedAndRecentEntriesWhenBundleEnabled(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['notebook']));

        $notebookRepo = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notebookRepo->method('findVisibleForStores')->willReturn([
            ['id' => 1, 'author_id' => 5, 'store_id' => 1, 'content' => 'Note récente', 'pinned' => 0, 'expires_at' => null, 'created_at' => '2026-09-20 10:00:00'],
            ['id' => 2, 'author_id' => 5, 'store_id' => null, 'content' => 'Note épinglée', 'pinned' => 1, 'expires_at' => null, 'created_at' => '2026-09-10 08:00:00'],
            ['id' => 3, 'author_id' => 5, 'store_id' => 1, 'content' => 'Note expirée', 'pinned' => 0, 'expires_at' => '2020-01-01 00:00:00', 'created_at' => '2026-09-21 10:00:00'],
        ]);
        Container::getInstance()->instance(NotebookEntryRepositoryInterface::class, $notebookRepo);

        $controller = $this->buildController(['kpi_counters', 'team_notes'], grantNotebookView: true);
        $response   = $controller->index($this->request());
        $data       = json_decode($response->body(), true);

        // L'épinglée passe en premier malgré une date plus ancienne ; l'expirée est exclue.
        $this->assertSame([2, 1], array_column($data['notebook_entries'], 'id'));
        $this->assertSame('Dupont Jean', $data['notebook_entries'][0]['author_name']);
    }

    public function testDashboardSkipsNotebookQueryWhenWidgetDisabled(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['notebook']));

        $notebookRepo = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notebookRepo->expects($this->never())->method('findVisibleForStores');
        Container::getInstance()->instance(NotebookEntryRepositoryInterface::class, $notebookRepo);

        $controller = $this->buildController(['kpi_counters'], grantNotebookView: true);
        $controller->index($this->request());
    }

    public function testDashboardExcludesEntriesWhenPermissionMissing(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['notebook']));

        $notebookRepo = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notebookRepo->expects($this->never())->method('findVisibleForStores');
        Container::getInstance()->instance(NotebookEntryRepositoryInterface::class, $notebookRepo);

        $controller = $this->buildController(['kpi_counters', 'team_notes'], grantNotebookView: false);
        $response   = $controller->index($this->request());
        $data       = json_decode($response->body(), true);

        $this->assertSame([], $data['notebook_entries']);
    }

    private function ensureViewFile(string $view, string $content): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, $content);
    }
}
