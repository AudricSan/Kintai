<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\PushNotificationService;
use kintai\Core\Services\TranslationService;
use kintai\Tests\Support\FakeBundleManagerFactory;
use kintai\UI\Controller\Web\EmployeeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Le widget "team_notes" du dashboard employé suit le même pattern de
 * résolution différée que celui du dashboard admin (voir
 * HomeControllerNotebookWidgetTest) : NotebookEntryRepositoryInterface n'est
 * lié au container que si le bundle "notebook" est actif.
 */
final class EmployeeControllerNotebookWidgetTest extends TestCase
{
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private EmployeeController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-employee-notebook-views';
        $this->writeViewFile($viewDir, 'employee.dashboard', '<?php echo json_encode(["notebook_entries" => $notebook_entries ?? null]); ?>');
        $this->writeViewFile($viewDir, 'layout.app', '<?= $content ?>');

        $this->storeUsers   = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores       = $this->createMock(StoreRepositoryInterface::class);
        $this->users        = $this->createMock(UserRepositoryInterface::class);
        $this->assignments  = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles        = $this->createMock(RoleRepositoryInterface::class);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findByUser')->willReturn([]);
        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findByUser')->willReturn([]);
        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findByRequester')->willReturn([]);
        $swapsRepo->method('findByTarget')->willReturn([]);
        $timeclocksRepo = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocksRepo->method('findActiveByUser')->willReturn(null);
        $dashboardPrefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $dashboardPrefs->method('getEnabledWidgets')->willReturn(['team_notes']);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1']]);
        $this->storeUsers->method('findByUser')->willReturn([['user_id' => 9, 'store_id' => 1]]);
        $this->users->method('findById')->willReturn(['id' => 5, 'first_name' => 'Jean', 'last_name' => 'Dupont']);

        $this->controller = new EmployeeController(
            new ViewRenderer($viewDir),
            $shiftsRepo,
            $this->createMock(ShiftTypeRepositoryInterface::class),
            $this->stores,
            $this->storeUsers,
            $this->users,
            $timeoffRepo,
            $swapsRepo,
            $this->createMock(UserShiftTypeRateRepositoryInterface::class),
            new AuditLogger(),
            $this->createMock(IcalTokenRepositoryInterface::class),
            $timeclocksRepo,
            $this->createMock(AvailabilityRepositoryInterface::class),
            $dashboardPrefs,
            new NotificationService(
                $this->createMock(NotificationRepositoryInterface::class),
                new PushNotificationService([], $this->createMock(DevicePushTokenRepositoryInterface::class)),
                new TranslationService(
                    $this->createStub(TranslationRepositoryInterface::class),
                    $this->createStub(LanguageRepositoryInterface::class),
                ),
                $this->createMock(UserRepositoryInterface::class),
            ),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
            new PermissionService($this->assignments, $this->roles),
        );
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

    private function grantNotebookView(): void
    {
        $this->assignments->method('findByUser')->with(9)->willReturn([
            ['id' => 1, 'user_id' => 9, 'role_id' => 5, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 1]);
    }

    private function request(): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        return $req;
    }

    public function testDashboardDoesNotCrashAndReturnsNoEntriesWhenNotebookBundleDisabled(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['timeoff']));
        $this->unbindNotebook();
        $this->grantNotebookView();

        $response = $this->controller->dashboard($this->request());
        $data     = json_decode($response->body(), true);

        $this->assertSame([], $data['notebook_entries']);
    }

    public function testDashboardReturnsPinnedAndRecentEntriesWhenBundleEnabled(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['notebook']));
        $this->grantNotebookView();

        $notebookRepo = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notebookRepo->method('findVisibleForStores')->willReturn([
            ['id' => 1, 'author_id' => 5, 'store_id' => 1, 'content' => 'Note récente', 'pinned' => 0, 'expires_at' => null, 'created_at' => '2026-09-20 10:00:00'],
            ['id' => 2, 'author_id' => 5, 'store_id' => null, 'content' => 'Note épinglée', 'pinned' => 1, 'expires_at' => null, 'created_at' => '2026-09-10 08:00:00'],
        ]);
        Container::getInstance()->instance(NotebookEntryRepositoryInterface::class, $notebookRepo);

        $response = $this->controller->dashboard($this->request());
        $data     = json_decode($response->body(), true);

        $this->assertSame([2, 1], array_column($data['notebook_entries'], 'id'));
    }

    public function testDashboardExcludesEntriesWhenPermissionMissing(): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs(['notebook']));
        $this->assignments->method('findByUser')->with(9)->willReturn([]);

        $notebookRepo = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notebookRepo->expects($this->never())->method('findVisibleForStores');
        Container::getInstance()->instance(NotebookEntryRepositoryInterface::class, $notebookRepo);

        $response = $this->controller->dashboard($this->request());
        $data     = json_decode($response->body(), true);

        $this->assertSame([], $data['notebook_entries']);
    }

    private function writeViewFile(string $dir, string $view, string $phpBody): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        file_put_contents($file, $phpBody);
    }
}
