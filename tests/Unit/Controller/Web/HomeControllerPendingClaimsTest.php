<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\FeatureManager;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
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
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Le KPI "Demandes en attente" du dashboard admin additionne aussi les
 * candidatures bourse aux shifts en attente. ShiftClaimRepositoryInterface
 * n'est lié au container que par le bundle ShiftClaim (voir
 * ShiftClaimBundle::registerServices()) : HomeController ne doit jamais le
 * demander en dépendance de constructeur, sous peine de planter le dashboard
 * entier dès que ce bundle est désactivé. Ce test couvre les deux cas.
 */
final class HomeControllerPendingClaimsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->ensureViewFile('dashboard.index', '<?php ?>');
        $this->ensureViewFile('layout.app', "<?php echo json_encode(['stats' => \$stats]);");
    }

    private function unbindShiftClaims(): void
    {
        $ref = new \ReflectionProperty(Container::class, 'instances');
        $ref->setAccessible(true);
        $instances = $ref->getValue(Container::getInstance());
        unset($instances[ShiftClaimRepositoryInterface::class]);
        $ref->setValue(Container::getInstance(), $instances);
    }

    private function buildController(): HomeController
    {
        $usersRepo = $this->createMock(UserRepositoryInterface::class);
        $usersRepo->method('findAll')->willReturn([]);

        $storesRepo = $this->createMock(StoreRepositoryInterface::class);
        $storesRepo->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1']]);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findAllByDate')->willReturn([]);

        $shiftTypesRepo = $this->createMock(ShiftTypeRepositoryInterface::class);

        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'status' => 'pending'],
        ]);

        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findAll')->willReturn([]);

        $timeclocksRepo = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocksRepo->method('findAll')->willReturn([]);

        $dashboardPrefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $dashboardPrefs->method('getEnabledWidgets')->willReturn(['kpi_counters']);

        $storeUsersRepo = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsersRepo->method('findByStore')->willReturn([]);

        $storeStats = $this->createMock(StoreStatsServiceInterface::class);

        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn([]);
        $roles = $this->createMock(RoleRepositoryInterface::class);
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

    public function testDashboardDoesNotCrashAndExcludesClaimsWhenShiftClaimBundleDisabled(): void
    {
        Container::getInstance()->instance(FeatureManager::class, new FeatureManager(['timeoff']));
        $this->unbindShiftClaims();

        $controller = $this->buildController();
        $response   = $controller->index($this->request());
        $data       = json_decode($response->body(), true);

        // 1 congé pending, aucune candidature (bundle désactivé) : total = 1.
        $this->assertSame(1, $data['stats']['pending_requests']);
    }

    public function testPendingRequestsIncludesPendingClaimsWhenBundleEnabled(): void
    {
        Container::getInstance()->instance(FeatureManager::class, new FeatureManager(['timeoff', 'shift-claim']));

        $claimsRepo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claimsRepo->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'status' => 'pending'],
            ['id' => 2, 'store_id' => 1, 'status' => 'approved'],
        ]);
        Container::getInstance()->instance(ShiftClaimRepositoryInterface::class, $claimsRepo);

        $controller = $this->buildController();
        $response   = $controller->index($this->request());
        $data       = json_decode($response->body(), true);

        // 1 congé pending + 1 candidature pending (l'approuvée est exclue) = 2.
        $this->assertSame(2, $data['stats']['pending_requests']);
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
