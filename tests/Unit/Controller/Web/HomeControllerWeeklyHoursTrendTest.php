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
 * Point 10 de la revue pré-1.0.0 : le dashboard admin manquait de graphiques pour
 * la prise de décision — seuls des chiffres statiques (heures/coût/stabilité du mois)
 * étaient affichés. Le widget "Aperçu statistiques" affiche désormais aussi la tendance
 * hebdomadaire des heures (stats-charts.js, déjà utilisé sur la page analytics complète),
 * mais uniquement quand exactement un store est dans le périmètre — pour plusieurs stores,
 * un graphique "heures par semaine" agrégerait sans les distinguer.
 */
final class HomeControllerWeeklyHoursTrendTest extends TestCase
{
    protected function setUp(): void
    {
        $this->ensureViewFile('dashboard.index', "<?php echo json_encode(['weeks' => \$store_stats_hours_by_week ?? null]);");
        $this->ensureViewFile('layout.app', "<?php echo \$content ?? '';");
    }

    private function buildController(StoreStatsServiceInterface $storeStats, array $stores): HomeController
    {
        $usersRepo = $this->createMock(UserRepositoryInterface::class);
        $usersRepo->method('findAll')->willReturn([]);

        $storesRepo = $this->createMock(StoreRepositoryInterface::class);
        $storesRepo->method('findAll')->willReturn($stores);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findAllByDate')->willReturn([]);

        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findAll')->willReturn([]);

        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findAll')->willReturn([]);

        $timeclocksRepo = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocksRepo->method('findAll')->willReturn([]);

        $dashboardPrefs = $this->createMock(UserDashboardPrefsRepositoryInterface::class);
        $dashboardPrefs->method('getEnabledWidgets')->willReturn(null);

        $storeUsersRepo = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsersRepo->method('findByStore')->willReturn([]);

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

    public function testFetchesWeeklyTrendWhenExactlyOneStoreInScope(): void
    {
        $storeStats = $this->createMock(StoreStatsServiceInterface::class);
        $storeStats->method('multiStoreComparison')->willReturn([
            ['store_id' => 7, 'store_name' => 'Store 7', 'total_hours' => 120.0, 'total_cost' => 0.0, 'stability' => null, 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
        ]);
        $storeStats->expects($this->once())->method('storeStats')
            ->with(7, $this->anything())
            ->willReturn(['hoursByWeek' => ['2026-30' => 38.5, '2026-31' => 40.0]]);

        $controller = $this->buildController($storeStats, [['id' => 7, 'name' => 'Store 7']]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => 0]);
        $req->setAttribute('managed_store_ids', [7]);

        $response = $controller->index($req);
        $data     = json_decode($response->body(), true);

        $this->assertSame(['2026-30' => 38.5, '2026-31' => 40], $data['weeks']);
    }

    public function testDoesNotFetchWeeklyTrendWhenMultipleStoresInScope(): void
    {
        $storeStats = $this->createMock(StoreStatsServiceInterface::class);
        $storeStats->method('multiStoreComparison')->willReturn([
            ['store_id' => 7, 'store_name' => 'Store 7', 'total_hours' => 120.0, 'total_cost' => 0.0, 'stability' => null, 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
            ['store_id' => 8, 'store_name' => 'Store 8', 'total_hours' => 80.0, 'total_cost' => 0.0, 'stability' => null, 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
        ]);
        $storeStats->expects($this->never())->method('storeStats');

        $controller = $this->buildController($storeStats, [['id' => 7, 'name' => 'Store 7'], ['id' => 8, 'name' => 'Store 8']]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => 1]);
        $req->setAttribute('managed_store_ids', null);

        $response = $controller->index($req);
        $data     = json_decode($response->body(), true);

        $this->assertSame([], $data['weeks']);
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
