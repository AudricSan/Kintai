<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Container;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\Core\Services\RoleAssignmentSyncService;
use kintai\UI\Controller\Web\Staff\AdminUserController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserControllerTest extends TestCase
{
    private AdminUserController $controller;
    private UserRepositoryInterface&MockObject $users;
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private LogRepositoryInterface&MockObject $logRepo;
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $roleAssignments;
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private UserShiftTypeRateRepositoryInterface&MockObject $userRates;
    protected function setUp(): void
    {
        $this->ensureViewFile('staff.users');
        $this->ensureViewFile('staff.users-form');
        $this->ensureViewFile('staff.users-export-pdf');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $this->roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->userRates = $this->createMock(UserShiftTypeRateRepositoryInterface::class);

        // AuditLogger::log() délègue à Log::record() -> LogRepositoryInterface::record().
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new AdminUserController(
            $view,
            $this->users,
            $this->stores,
            $this->createMock(ShiftRepositoryInterface::class),
            $this->shiftTypes,
            $this->storeUsers,
            $this->userRates,
            $this->createMock(HiringReportRepositoryInterface::class),
            new AuditLogger(),
            new RoleAssignmentSyncService($this->roles, $this->roleAssignments),
        );
    }

    public function testUsersRenders(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $response = $this->controller->users($req);

        $this->assertSame(200, $response->status());
    }

    /**
     * La recherche par nom est appliquée entièrement côté client
     * (kana-search.js / users-list-search.js, voir CONTRIBUTING.md "Filter
     * bars apply instantly") pour rester instantanée et sans rechargement de
     * page. Le contrôleur ne doit donc plus retirer d'utilisateurs de la
     * liste quand `?search=` est présent — il ne sert qu'à pré-remplir le
     * champ pour un lien direct. La correspondance romaji/hiragana/kana est
     * couverte par KanaSearchTest (le service PHP reste utilisé nulle part
     * ici mais son portage JS, kana-search.js, réplique le même algorithme).
     */
    public function testUsersSearchDoesNotErrorAndDoesNotFilterServerSide(): void
    {
        $this->users->method('findAll')->willReturn([
            ['id' => 1, 'first_name' => '太郎', 'last_name' => '山田', 'furigana_last_name' => 'ヤマダ', 'furigana_first_name' => 'タロウ', 'employee_code' => 'EMP001', 'email' => 'yamada@test.com'],
            ['id' => 2, 'first_name' => 'Paul', 'last_name' => 'Martin', 'employee_code' => 'EMP002', 'email' => 'paul@test.com'],
        ]);

        $_GET = ['search' => 'yamada'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $response = $this->controller->users($req);

        $this->assertSame(200, $response->status());
        $_GET = [];
    }

    // -------------------------------------------------------------------------
    // Export employés (item 3)
    // -------------------------------------------------------------------------

    public function testExportUsersJsonReturnsExpectedFields(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->users->method('findAll')->willReturn([
            ['id' => 5, 'employee_code' => 'E005', 'last_name' => 'Dupont', 'first_name' => 'Jean',
             'display_name' => 'Jean Dupont', 'email' => 'jean@test.com', 'phone' => '0102030405',
             'mobile_phone' => '0601020304', 'postal_code' => '75001', 'address' => 'Rue X',
             'is_admin' => false, 'is_active' => 1, 'deleted_at' => null],
        ]);
        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->storeUsers->method('findByUser')->with(5)->willReturn([['store_id' => 1]]);
        $this->userRates->method('findByUser')->with(5)->willReturn([['shift_type_id' => 2, 'hourly_rate' => 1200]]);
        $this->shiftTypes->method('findAll')->willReturn([['id' => 2, 'name' => 'Salle']]);

        $response = $this->controller->exportUsersJson($req);
        $this->assertSame(200, $response->status());

        $data = json_decode($response->body(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame('E005', $data[0]['employee_code']);
        $this->assertSame('jean@test.com', $data[0]['email']);
        $this->assertSame('0601020304', $data[0]['mobile_phone']);
        $this->assertSame(['Store A'], $data[0]['stores']);
        $this->assertTrue($data[0]['is_active']);
        $this->assertSame('Salle', $data[0]['hourly_rates'][0]['shift_type']);
        $this->assertEquals(1200, $data[0]['hourly_rates'][0]['hourly_rate']);
    }

    public function testExportUsersJsonRespectsStoreFilter(): void
    {
        $_GET['store_id'] = '1';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->users->method('findAll')->willReturn([
            ['id' => 5, 'display_name' => 'In Store', 'email' => 'a@test.com', 'is_active' => 1],
            ['id' => 6, 'display_name' => 'Other Store', 'email' => 'b@test.com', 'is_active' => 1],
        ]);
        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A'], ['id' => 2, 'name' => 'Store B']]);
        $this->storeUsers->method('findByUser')->willReturnMap([
            [5, [['store_id' => 1]]],
            [6, [['store_id' => 2]]],
        ]);
        $this->userRates->method('findByUser')->willReturn([]);
        $this->shiftTypes->method('findAll')->willReturn([]);

        $response = $this->controller->exportUsersJson($req);
        $data = json_decode($response->body(), true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame('In Store', $data[0]['display_name']);
    }

    public function testExportUsersPdfReturnsPdfResponse(): void
    {
        // mPDF lit $_SERVER['PHP_SELF'], absent en environnement CLI/tests.
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([]);
        $this->shiftTypes->method('findAll')->willReturn([]);

        $response = $this->controller->exportUsersPdf($req);

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('%PDF', $response->body());
    }

    public function testEditUserRenders(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => 1]);

        $this->users->method('findById')->with(1)->willReturn(['id' => 1, 'email' => 'test@test.com']);
        $this->stores->method('findAll')->willReturn([]);
        $this->storeUsers->method('findByUser')->willReturn([]);

        $response = $this->controller->editUser($req);

        $this->assertSame(200, $response->status());
    }

    public function testEditUserLogsProfileConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => 5]);

        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'employee@test.com']);
        $this->stores->method('findAll')->willReturn([]);
        $this->storeUsers->method('findByUser')->willReturn([]);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'user.viewed', 'user', 5,
            $this->anything(), $this->anything(), $this->anything(),
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->editUser($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // quickCreateUser
    // -------------------------------------------------------------------------

    public function testQuickCreateUserCreatesUserAndReturnsJson(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John Doe',
            'email'        => 'john@example.com',
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'password'     => 'secret',
            'color'        => '#FF0000',
            'is_admin'     => '0',
            'furigana_last_name'  => 'ドウ',
            'furigana_first_name' => 'ジョン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('save')->willReturn(['id' => 7, 'display_name' => 'John Doe']);

        $response = $this->controller->quickCreateUser($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(7, $data['user']['id']);
    }

    public function testQuickCreateUserFailsWhenNameMissing(): void
    {
        $req = $this->makePostRequest([
            'first_name' => '',
            'display_name' => '',
        ]);

        $response = $this->controller->quickCreateUser($req);

        $this->assertSame(422, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['success']);
    }

    /** Le furigana est désormais obligatoire pour tous les chemins de création. */
    public function testQuickCreateUserFailsWhenFuriganaMissing(): void
    {
        $req = $this->makePostRequest([
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'display_name' => 'John',
            'email'        => 'john@example.com',
        ]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->quickCreateUser($req);

        $this->assertSame(422, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('furigana_required', $data['error']);
    }

    public function testQuickCreateUserGeneratesDisplayName(): void
    {
        $req = $this->makePostRequest([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'display_name' => '',
            'email'      => 'jane@example.com',
            'password'   => 'pass',
            'furigana_last_name'  => 'スミス',
            'furigana_first_name' => 'ジェーン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $captured = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 8];
        });

        $this->controller->quickCreateUser($req);

        $this->assertNotNull($captured);
        $this->assertSame('Jane Smith', $captured['display_name']);
    }

    public function testQuickCreateUserGeneratesEmailWhenMissing(): void
    {
        $req = $this->makePostRequest([
            'first_name'   => 'Jane',
            'last_name'    => 'Smith',
            'display_name' => 'Jane Smith',
            'email'        => '',
            'password'     => 'pass',
            'furigana_last_name'  => 'スミス',
            'furigana_first_name' => 'ジェーン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('findByEmail')->willReturn(null);
        $captured = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 8];
        });

        $this->controller->quickCreateUser($req);

        $this->assertNotNull($captured);
        $this->assertStringEndsWith('@kintai.local', $captured['email']);
        $this->assertStringContainsString('jane.smith', $captured['email']);
    }

    public function testQuickCreateUserReturns409OnEmployeeCodeConflict(): void
    {
        $req = $this->makePostRequest([
            'display_name'   => 'John',
            'first_name'     => 'John',
            'last_name'      => 'Doe',
            'email'          => 'john@example.com',
            'employee_code'  => 'EMP001',
            'password'       => 'pass',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);

        $this->users->method('findByEmployeeCode')->with('EMP001')->willReturn(['id' => 99]);

        $response = $this->controller->quickCreateUser($req);

        $this->assertSame(409, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('employee_code_taken', $data['error']);
    }

    /**
     * Régression : un email fourni explicitement (pas auto-généré) qui existe
     * déjà en base faisait planter la création avec une exception SQL non
     * gérée (contrainte unique) au lieu d'un message clair — typiquement
     * quand l'import de shifts ne reconnaît pas un employé déjà existant.
     */
    public function testQuickCreateUserReturns409OnEmailConflict(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'email'        => 'existing@example.com',
            'password'     => 'pass',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);

        $this->users->method('findByEmail')->with('existing@example.com')->willReturn(['id' => 99]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->quickCreateUser($req);

        $this->assertSame(409, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('email_taken', $data['error']);
    }

    public function testQuickCreateUserGeneratesTempPasswordWhenEmpty(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'email'        => 'john@example.com',
            'password'     => '',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $captured = null;
        $this->users->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 9];
        });

        $this->controller->quickCreateUser($req);

        $this->assertNotNull($captured);
        $this->assertStringStartsWith('$2y$', $captured['password_hash']);
    }

    public function testQuickCreateUserAssignsStoreMembership(): void
    {
        $req = $this->makePostRequest([
            'display_name'  => 'John',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'john@example.com',
            'password'      => 'pass',
            'store_id'      => '3',
            'store_role_id' => '2',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('save')->willReturn(['id' => 10, 'display_name' => 'John']);
        // Rôle dynamique id=2 accordant des permissions → colonne legacy 'manager'
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['employees.view']);
        $this->roleAssignments->method('findByUser')->willReturn([]);

        $capturedStoreUser = null;
        $this->storeUsers->method('save')->willReturnCallback(function (array $d) use (&$capturedStoreUser) {
            $capturedStoreUser = $d;
            return $d;
        });

        $this->controller->quickCreateUser($req);

        $this->assertNotNull($capturedStoreUser);
        $this->assertSame(3, $capturedStoreUser['store_id']);
        $this->assertSame(10, $capturedStoreUser['user_id']);
        $this->assertSame('manager', $capturedStoreUser['role']);
    }

    /**
     * AuthService::managedStoreIds() lit désormais role_assignments : sans
     * cette synchronisation, un manager créé via l'import de shifts ne serait
     * jamais reconnu comme gestionnaire de son store.
     */
    public function testQuickCreateUserSyncsRoleAssignmentForStoreMembership(): void
    {
        $req = $this->makePostRequest([
            'display_name'  => 'John',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'john@example.com',
            'password'      => 'pass',
            'store_id'      => '3',
            'store_role_id' => '2',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('save')->willReturn(['id' => 10, 'display_name' => 'John']);
        $this->roles->method('findBySlug')->with('owner')->willReturn(null);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'name' => 'Manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['employees.view']);
        $this->roleAssignments->method('findByUser')->willReturn([]);
        $this->roleAssignments->expects($this->once())->method('assign')->with(10, 2, 'store', 3);

        $this->controller->quickCreateUser($req);
    }

    /** Id de rôle absent/invalide → repli sur le rôle par défaut (premier rôle sans permission). */
    public function testQuickCreateUserFallsBackToDefaultRoleWhenRoleIdMissing(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'first_name'   => 'John',
            'last_name'    => 'Doe',
            'email'        => 'john@example.com',
            'password'     => 'pass',
            'store_id'     => '3',
            'furigana_last_name'  => 'ジョン',
            'furigana_first_name' => 'ジョン',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('save')->willReturn(['id' => 10, 'display_name' => 'John']);
        $this->roles->method('findAll')->willReturn([
            ['id' => 2, 'name' => 'Manager', 'is_system' => 0],
            ['id' => 3, 'name' => 'Employé', 'is_system' => 0],
        ]);
        $this->roles->method('findById')->with(3)->willReturn(['id' => 3, 'name' => 'Employé', 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturnMap([
            [2, ['employees.view']],
            [3, []],
        ]);
        $this->roleAssignments->method('findByUser')->willReturn([]);
        $this->roleAssignments->expects($this->once())->method('assign')->with(10, 3, 'store', 3);

        $capturedStoreUser = null;
        $this->storeUsers->method('save')->willReturnCallback(function (array $d) use (&$capturedStoreUser) {
            $capturedStoreUser = $d;
            return $d;
        });

        $this->controller->quickCreateUser($req);

        $this->assertSame('staff', $capturedStoreUser['role']);
    }

    public function testQuickCreateUserSyncsOwnerRoleWhenIsAdminChecked(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'Jane',
            'first_name'   => 'Jane',
            'last_name'    => 'Doe',
            'email'        => 'jane@example.com',
            'password'     => 'pass',
            'is_admin'     => '1',
            'furigana_last_name'  => 'ジェーン',
            'furigana_first_name' => 'ジェーン',
        ]);

        $this->users->method('save')->willReturn(['id' => 11, 'display_name' => 'Jane']);
        $this->roles->method('findBySlug')->with('owner')->willReturn(['id' => 1]);
        $this->roleAssignments->method('findByUser')->willReturn([]);
        $this->roleAssignments->expects($this->once())->method('assign')->with(11, 1, 'global', null);

        $this->controller->quickCreateUser($req);
    }

    // -------------------------------------------------------------------------
    // storeUser / updateUser — conflit d'email
    // -------------------------------------------------------------------------

    public function testStoreUserRedirectsWithErrorOnEmailConflict(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'email'        => 'existing@example.com',
        ]);

        $this->users->method('findByEmail')->with('existing@example.com')->willReturn(['id' => 99]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->storeUser($req);

        $this->assertSame(302, $response->status());
    }

    /** Le nom et le prénom sont désormais obligatoires à la création (formulaire principal). */
    public function testStoreUserRedirectsWithErrorWhenNameMissing(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'email'        => 'new@example.com',
        ]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->storeUser($req);

        $this->assertSame(302, $response->status());
    }

    /** Le furigana est désormais obligatoire à la création (formulaire principal). */
    public function testStoreUserRedirectsWithErrorWhenFuriganaMissing(): void
    {
        $req = $this->makePostRequest([
            'display_name' => 'John',
            'last_name'    => 'Doe',
            'first_name'   => 'John',
            'email'        => 'new@example.com',
        ]);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->storeUser($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateUserRedirectsWithErrorWhenEmailTakenByAnotherUser(): void
    {
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'me@example.com']);
        $this->users->method('findByEmail')->with('taken@example.com')->willReturn(['id' => 99]);
        $this->users->expects($this->never())->method('save');

        $req = $this->makePostRequest(['email' => 'taken@example.com']);
        $req->setRouteParams(['id' => 5]);

        $response = $this->controller->updateUser($req);

        $this->assertSame(302, $response->status());
    }

    /** Le nom et le prénom sont désormais obligatoires à l'édition, sauf s'ils sont déjà en base. */
    public function testUpdateUserRedirectsWithErrorWhenNameMissing(): void
    {
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'me@example.com', 'last_name' => null, 'first_name' => null]);
        $this->users->expects($this->never())->method('save');

        $req = $this->makePostRequest(['email' => 'me@example.com']);
        $req->setRouteParams(['id' => 5]);

        $response = $this->controller->updateUser($req);

        $this->assertSame(302, $response->status());
    }

    /** Le furigana est désormais obligatoire à l'édition, sauf s'il est déjà en base. */
    public function testUpdateUserRedirectsWithErrorWhenFuriganaMissing(): void
    {
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'me@example.com', 'last_name' => 'Doe', 'first_name' => 'John', 'furigana_last_name' => null, 'furigana_first_name' => null]);
        $this->users->expects($this->never())->method('save');

        $req = $this->makePostRequest(['email' => 'me@example.com']);
        $req->setRouteParams(['id' => 5]);

        $response = $this->controller->updateUser($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateUserAllowsKeepingOwnEmailUnchanged(): void
    {
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'me@example.com', 'last_name' => 'Doe', 'first_name' => 'John']);
        $this->users->expects($this->never())->method('findByEmail');
        $this->users->expects($this->once())->method('save')->willReturn(['id' => 5]);

        $req = $this->makePostRequest([
            'email' => 'me@example.com',
            'furigana_last_name'  => 'テスト',
            'furigana_first_name' => 'テスト',
        ]);
        $req->setRouteParams(['id' => 5]);

        $response = $this->controller->updateUser($req);

        $this->assertSame(302, $response->status());
    }

    // -------------------------------------------------------------------------
    // checkEmployeeCode
    // -------------------------------------------------------------------------

    public function testCheckEmployeeCodeReturnsTrueWhenAvailable(): void
    {
        $req = $this->makeQueryRequest(['code' => 'EMP999']);
        $this->users->method('findByEmployeeCode')->with('EMP999')->willReturn(null);

        $response = $this->controller->checkEmployeeCode($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertTrue($data['available']);
    }

    public function testCheckEmployeeCodeReturnsFalseWhenTaken(): void
    {
        $req = $this->makeQueryRequest(['code' => 'EMP001']);
        $this->users->method('findByEmployeeCode')->with('EMP001')->willReturn(['id' => 1]);

        $response = $this->controller->checkEmployeeCode($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertFalse($data['available']);
    }

    public function testCheckEmployeeCodeNormalizesToUppercase(): void
    {
        $req = $this->makeQueryRequest(['code' => 'emp001']);

        $this->users->expects($this->once())->method('findByEmployeeCode')->with('EMP001')->willReturn(['id' => 1]);

        $this->controller->checkEmployeeCode($req);
    }

    public function testCheckEmployeeCodeAvailableWhenTakenByExcludedUser(): void
    {
        // Édition : le code déjà pris par l'utilisateur en cours d'édition lui-même n'est pas un conflit.
        $req = $this->makeQueryRequest(['code' => 'EMP001', 'exclude_id' => '1']);
        $this->users->method('findByEmployeeCode')->with('EMP001')->willReturn(['id' => 1]);

        $response = $this->controller->checkEmployeeCode($req);

        $data = json_decode($response->body(), true);
        $this->assertTrue($data['available']);
    }

    public function testCheckEmployeeCodeUnavailableWhenTakenByAnotherUser(): void
    {
        $req = $this->makeQueryRequest(['code' => 'EMP001', 'exclude_id' => '2']);
        $this->users->method('findByEmployeeCode')->with('EMP001')->willReturn(['id' => 1]);

        $response = $this->controller->checkEmployeeCode($req);

        $data = json_decode($response->body(), true);
        $this->assertFalse($data['available']);
    }

    // -------------------------------------------------------------------------
    // checkEmail
    // -------------------------------------------------------------------------

    public function testCheckEmailReturnsTrueWhenAvailable(): void
    {
        $req = $this->makeQueryRequest(['email' => 'new@test.com']);
        $this->users->method('findByEmail')->with('new@test.com')->willReturn(null);

        $response = $this->controller->checkEmail($req);

        $data = json_decode($response->body(), true);
        $this->assertTrue($data['available']);
    }

    public function testCheckEmailReturnsFalseWhenTaken(): void
    {
        $req = $this->makeQueryRequest(['email' => 'taken@test.com']);
        $this->users->method('findByEmail')->with('taken@test.com')->willReturn(['id' => 1]);

        $response = $this->controller->checkEmail($req);

        $data = json_decode($response->body(), true);
        $this->assertFalse($data['available']);
    }

    public function testCheckEmailReturnsTrueForEmptyValue(): void
    {
        $req = $this->makeQueryRequest(['email' => '']);
        $this->users->expects($this->never())->method('findByEmail');

        $response = $this->controller->checkEmail($req);

        $data = json_decode($response->body(), true);
        $this->assertTrue($data['available']);
    }

    public function testCheckEmailAvailableWhenTakenByExcludedUser(): void
    {
        $req = $this->makeQueryRequest(['email' => 'own@test.com', 'exclude_id' => '5']);
        $this->users->method('findByEmail')->with('own@test.com')->willReturn(['id' => 5]);

        $response = $this->controller->checkEmail($req);

        $data = json_decode($response->body(), true);
        $this->assertTrue($data['available']);
    }

    public function testCheckEmailUnavailableWhenTakenByAnotherUser(): void
    {
        $req = $this->makeQueryRequest(['email' => 'other@test.com', 'exclude_id' => '5']);
        $this->users->method('findByEmail')->with('other@test.com')->willReturn(['id' => 9]);

        $response = $this->controller->checkEmail($req);

        $data = json_decode($response->body(), true);
        $this->assertFalse($data['available']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        Log::reset();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePostRequest(array $post): Request
    {
        $_POST = $post;
        return new Request();
    }

    private function makeQueryRequest(array $query): Request
    {
        $_GET = $query;
        return new Request();
    }

    private function ensureViewFile(string $view): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($file);
    }
}
