<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Régression : le dashboard admin (HomeController::index) ignorait totalement
 * managed_store_ids et affichait toujours les données de TOUS les stores
 * (utilisateurs, shifts, congés, échanges, pointages), même pour un manager
 * restreint par AdminMiddleware à un sous-ensemble de stores.
 */
final class HomeControllerScopingTest extends TestCase
{
    protected function setUp(): void
    {
        $this->ensureViewFile('dashboard.index', '<?php ?>');
        $this->ensureViewFile('layout.app', "<?php echo json_encode(['stats' => \$stats, 'shifts_today' => \$shifts_today]);");
    }

    private function buildController(
        array $users,
        array $stores,
        array $shiftsToday,
        array $timeoff,
        array $swaps,
        array $timeclocks,
        array $storeMembers,
    ): HomeController {
        $usersRepo = $this->createMock(UserRepositoryInterface::class);
        $usersRepo->method('findAll')->willReturn($users);

        $storesRepo = $this->createMock(StoreRepositoryInterface::class);
        $storesRepo->method('findAll')->willReturn($stores);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findAllByDate')->willReturn($shiftsToday);

        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findAll')->willReturn($timeoff);

        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findAll')->willReturn($swaps);

        $timeclocksRepo = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocksRepo->method('findAll')->willReturn($timeclocks);

        $dashboardPrefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $dashboardPrefs->method('getEnabledWidgets')->willReturn(null);

        $storeUsersRepo = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsersRepo->method('findByStore')->willReturnCallback(
            fn(int $storeId) => $storeMembers[$storeId] ?? []
        );

        $storeStats = $this->createMock(StoreStatsServiceInterface::class);
        $storeStats->method('multiStoreComparison')->willReturn([]);

        // Rôle non-système accordant payroll.view à n'importe quel store, pour que
        // canViewStats soit vrai aussi côté manager (is_admin = 0) dans ces tests.
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([
            ['id' => 1, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 0]);
        $roles->method('getPermissions')->with(5)->willReturn(['payroll.view']);
        $permissions = new PermissionService($roleAssignments, $roles);

        return new HomeController(
            new ViewRenderer(sys_get_temp_dir()),
            $usersRepo,
            $storesRepo,
            $shiftsRepo,
            $timeoffRepo,
            $swapsRepo,
            $dashboardPrefs,
            $timeclocksRepo,
            $storeUsersRepo,
            $storeStats,
            $permissions,
        );
    }

    /** @return array{0: array, 1: array, 2: array, 3: array, 4: array, 5: array, 6: array} */
    private function fixtures(): array
    {
        $users  = [['id' => 1, 'email' => 'a@test.com'], ['id' => 2, 'email' => 'b@test.com']];
        $stores = [['id' => 1, 'name' => 'Store 1'], ['id' => 2, 'name' => 'Store 2']];
        $shifts = [
            ['id' => 10, 'store_id' => 1, 'user_id' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ['id' => 11, 'store_id' => 2, 'user_id' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
        ];
        $timeoff = [['id' => 1, 'store_id' => 1, 'user_id' => 1, 'status' => 'pending']];
        $swaps   = [['id' => 1, 'store_id' => 2, 'requester_id' => 2, 'status' => 'pending']];
        $timeclocks = [
            ['id' => 1, 'store_id' => 1, 'user_id' => 1, 'shift_date' => date('Y-m-d'), 'clock_in_time' => date('Y-m-d') . ' 09:00:00', 'clock_out_time' => null],
        ];
        $storeMembers = [
            1 => [['user_id' => 1]],
            2 => [['user_id' => 2]],
        ];

        return [$users, $stores, $shifts, $timeoff, $swaps, $timeclocks, $storeMembers];
    }

    public function testGlobalAdminSeesAllStores(): void
    {
        [$users, $stores, $shifts, $timeoff, $swaps, $timeclocks, $storeMembers] = $this->fixtures();
        $controller = $this->buildController($users, $stores, $shifts, $timeoff, $swaps, $timeclocks, $storeMembers);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 99, 'is_admin' => 1]);
        $req->setAttribute('managed_store_ids', null);

        $response = $controller->index($req);
        $data     = json_decode($this->body($response), true);

        $this->assertSame(2, $data['stats']['users']);
        $this->assertSame(2, $data['stats']['stores']);
        $this->assertCount(2, $data['shifts_today']);
    }

    public function testScopedManagerOnlySeesOwnStore(): void
    {
        [$users, $stores, $shifts, $timeoff, $swaps, $timeclocks, $storeMembers] = $this->fixtures();
        $controller = $this->buildController($users, $stores, $shifts, $timeoff, $swaps, $timeclocks, $storeMembers);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => 0]);
        $req->setAttribute('managed_store_ids', [1]);

        $response = $controller->index($req);
        $data     = json_decode($this->body($response), true);

        $this->assertSame(1, $data['stats']['users'], 'ne doit compter que les membres du store géré');
        $this->assertSame(1, $data['stats']['stores']);
        $this->assertCount(1, $data['shifts_today']);
        $this->assertSame(1, (int) $data['shifts_today'][0]['store_id']);
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

    private function body(Response $response): string
    {
        return $response->body();
    }
}
