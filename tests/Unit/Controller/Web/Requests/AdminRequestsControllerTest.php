<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\Requests;

use kintai\Core\Auth\PermissionService;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Tests\Support\FakeBundleManagerFactory;
use kintai\UI\Controller\Web\Requests\AdminRequestsController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Page de résumé "Demandes" (admin.requests) : agrège congés, échanges de
 * shifts et candidatures bourse aux shifts en attente, lus à travers les
 * fournisseurs (PendingRequests, repli historique ici). Chaque section n'est
 * présente que si son bundle est actif ET que l'utilisateur a la permission
 * "*.view" correspondante — sinon elle reste absente de la page.
 * ShiftClaimRepositoryInterface n'étant lié au container que par le bundle
 * ShiftClaim, la section des candidatures doit aussi disparaître proprement
 * quand ce bundle est désactivé, sans jamais faire planter le contrôleur.
 */
final class AdminRequestsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->ensureViewFile(
            'requests.index',
            // Une entrée par section affichée : clé du fournisseur => demandes (identifiant + colonnes).
            "<?php \$o = []; foreach (\$sections as \$s) { \$o[\$s['key']] = array_map(fn(\$r) => ['id' => \$r->id, 'columns' => \$r->columns], \$s['requests']); } echo json_encode(\$o);"
        );
        $this->ensureViewFile('layout.app', "<?php echo \$content ?? '';");
    }

    protected function tearDown(): void
    {
        // Réinitialise le singleton Container pour ne pas propager le
        // BundleManager injecté par enableBundles() vers d'autres tests.
        $instance = new \ReflectionProperty(Container::class, 'instance');
        $instance->setValue(null, null);
    }

    private function enableBundles(array $slugs): void
    {
        Container::getInstance()->instance(BundleManager::class, FakeBundleManagerFactory::withActiveSlugs($slugs));
    }

    private function bindShiftClaims(ShiftClaimRepositoryInterface $repo): void
    {
        Container::getInstance()->instance(ShiftClaimRepositoryInterface::class, $repo);
    }

    private function unbindShiftClaims(): void
    {
        // Simule un bundle "shift-claim" désactivé : le service n'a jamais été
        // enregistré (voir ShiftClaimBundle::registerServices()).
        $ref = new \ReflectionProperty(Container::class, 'instances');
        $ref->setAccessible(true);
        $instances = $ref->getValue(Container::getInstance());
        unset($instances[ShiftClaimRepositoryInterface::class]);
        $ref->setValue(Container::getInstance(), $instances);
    }

    private function buildController(bool $isAdmin, array $timeoff = [], array $swaps = []): AdminRequestsController
    {
        $usersRepo = $this->createMock(UserRepositoryInterface::class);
        $usersRepo->method('findAll')->willReturn([
            ['id' => 1, 'first_name' => 'Alice', 'last_name' => 'A'],
            ['id' => 2, 'first_name' => 'Bob', 'last_name' => 'B'],
        ]);

        $storesRepo = $this->createMock(StoreRepositoryInterface::class);
        $storesRepo->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1']]);

        $storeUsersRepo = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsersRepo->method('findByStore')->willReturn([]);

        $shiftsRepo = $this->createMock(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findById')->willReturn(['id' => 10, 'shift_date' => '2026-08-10', 'start_time' => '09:00:00', 'end_time' => '17:00:00']);

        $timeoffRepo = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRepo->method('findAll')->willReturn($timeoff);

        $swapsRepo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapsRepo->method('findAll')->willReturn($swaps);

        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $roleAssignments->method('findByUser')->willReturn($isAdmin ? [] : [
            ['id' => 1, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $roles = $this->createMock(RoleRepositoryInterface::class);
        $roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 0]);
        $roles->method('getPermissions')->with(5)->willReturn(['timeoff.view', 'swaps.view']);
        $permissions = new PermissionService($roleAssignments, $roles);

        // Les demandes sont lues à travers les fournisseurs (PendingRequests), qui prennent leurs dépôts dans le
        // conteneur.
        $container = Container::getInstance();
        $container->instance(TimeoffRequestRepositoryInterface::class, $timeoffRepo);
        $container->instance(ShiftSwapRequestRepositoryInterface::class, $swapsRepo);
        $container->instance(ShiftRepositoryInterface::class, $shiftsRepo);
        $container->instance(StoreRepositoryInterface::class, $storesRepo);

        return new AdminRequestsController(
            new ViewRenderer(sys_get_temp_dir()),
            $usersRepo,
            $storesRepo,
            $storeUsersRepo,
            $permissions,
        );
    }

    private function request(bool $isAdmin, ?array $managedStoreIds = null): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => $isAdmin ? 1 : 0]);
        $req->setAttribute('managed_store_ids', $managedStoreIds);
        return $req;
    }

    public function testTimeoffAndSwapsPresentButClaimsNullWhenShiftClaimBundleDisabled(): void
    {
        $this->enableBundles(['timeoff', 'shift-swap']);
        $this->unbindShiftClaims();

        $controller = $this->buildController(true);
        $response   = $controller->index($this->request(true));
        $data       = json_decode($response->body(), true);

        $this->assertSame([], $data['timeoff']);
        $this->assertSame([], $data['swaps']);
        $this->assertArrayNotHasKey('open_shifts', $data);
    }

    public function testAllSectionsNullWhenBundlesDisabled(): void
    {
        $this->enableBundles([]);
        $this->unbindShiftClaims();

        $controller = $this->buildController(true);
        $response   = $controller->index($this->request(true));
        $data       = json_decode($response->body(), true);

        $this->assertSame([], $data, 'aucune section');
    }

    public function testTimeoffFilteredToPendingAndScopedToManagedStore(): void
    {
        $this->enableBundles(['timeoff', 'shift-swap']);
        $this->unbindShiftClaims();

        $timeoff = [
            ['id' => 1, 'store_id' => 1, 'status' => 'pending', 'user_id' => 1],
            ['id' => 2, 'store_id' => 1, 'status' => 'approved', 'user_id' => 1],
            ['id' => 3, 'store_id' => 2, 'status' => 'pending', 'user_id' => 2],
        ];
        $controller = $this->buildController(false, $timeoff);
        $response   = $controller->index($this->request(false, [1]));
        $data       = json_decode($response->body(), true);

        $this->assertCount(1, $data['timeoff']);
        $this->assertSame(1, $data['timeoff'][0]['id']);
        $this->assertArrayNotHasKey('open_shifts', $data, 'pas de permission open_shifts.view');
    }

    public function testClaimsPresentAndEnrichedWithShiftInfoWhenBundleEnabled(): void
    {
        $this->enableBundles(['timeoff', 'shift-swap', 'shift-claim']);

        $claimsRepo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claimsRepo->method('findPendingByStore')->willReturn([
            ['id' => 1, 'shift_id' => 10, 'user_id' => 1, 'status' => 'pending'],
        ]);
        $this->bindShiftClaims($claimsRepo);

        $controller = $this->buildController(true);
        $response   = $controller->index($this->request(true));
        $data       = json_decode($response->body(), true);

        $this->assertCount(1, $data['open_shifts']);
        $columns = array_column($data['open_shifts'][0]['columns'], 'value', 'label');
        $this->assertSame('2026-08-10', $columns['date']);
        $this->assertSame(1, $columns['applicant']);
        $this->assertSame('09:00:00', $columns['start']);
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
