<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use kintai\Core\Container;
use kintai\Core\Router;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que staff/users.php, staff/stores.php, staff/stores-form.php et
 * staff/users-form.php gardent bien leurs liens/formulaires vers des actions
 * protégées derrière le helper $can()/$user_can, plutôt que de les afficher
 * inconditionnellement à quiconque atteint la page conteneur (protégée par
 * une permission plus large que celle des actions qu'elle donne accès à).
 */
final class StaffViewPermissionGatesTest extends TestCase
{
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->view = new ViewRenderer(dirname(__DIR__, 2) . '/src/UI/View');

        $router = new Router();
        $router->get('/admin/users', [\stdClass::class, 'x'], name: 'admin.users');
        $router->get('/admin/users/create', [\stdClass::class, 'x'], name: 'admin.users.create');
        $router->get('/admin/users/export/pdf', [\stdClass::class, 'x'], name: 'admin.users.export_pdf');
        $router->get('/admin/users/export/json', [\stdClass::class, 'x'], name: 'admin.users.export_json');
        $router->get('/admin/stores', [\stdClass::class, 'x'], name: 'admin.stores');
        $router->get('/admin/stores/create', [\stdClass::class, 'x'], name: 'admin.stores.create');
        Container::getInstance()->instance(Router::class, $router);
    }

    private function canFn(array $granted): \Closure
    {
        return fn(string $key): bool => in_array($key, $granted, true);
    }

    // -------------------------------------------------------------------------
    // staff/users.php
    // -------------------------------------------------------------------------

    public function testUsersListHidesCreateButtonAndRowActionsWithoutPermissions(): void
    {
        $html = $this->view->renderPartial('staff.users', [
            'BASE_URL'         => '',
            'users'            => [['id' => 5, 'display_name' => 'Jean Dupont', 'email' => 'j@d.com', 'is_admin' => 0, 'is_active' => 1]],
            'user_stats'       => [],
            'available_stores' => [],
            'store_names'      => [],
            'user_store_ids'   => [],
            'user_store_map'   => [5 => 1],
            'user_can'         => $this->canFn([]),
        ]);

        $this->assertStringNotContainsString('/admin/users/create', $html);
        $this->assertStringNotContainsString('employee-report/5/stats', $html);
        $this->assertStringNotContainsString('reports/salary/create?user_id=5', $html);
        $this->assertStringNotContainsString('reports/resignation/create?user_id=5', $html);
    }

    public function testUsersListShowsRowActionsWhenPermissionsGranted(): void
    {
        $html = $this->view->renderPartial('staff.users', [
            'BASE_URL'         => '',
            'users'            => [['id' => 5, 'display_name' => 'Jean Dupont', 'email' => 'j@d.com', 'is_admin' => 0, 'is_active' => 1]],
            'user_stats'       => [],
            'available_stores' => [],
            'store_names'      => [],
            'user_store_ids'   => [],
            'user_store_map'   => [5 => 1],
            'user_can'         => $this->canFn(['employees.create', 'payroll.view', 'payroll.generate', 'documents.create']),
        ]);

        $this->assertStringContainsString('/admin/users/create', $html);
        $this->assertStringContainsString('employee-report/5/stats', $html);
        $this->assertStringContainsString('reports/salary/create?user_id=5', $html);
        $this->assertStringContainsString('reports/resignation/create?user_id=5', $html);
    }

    // -------------------------------------------------------------------------
    // staff/stores.php
    // -------------------------------------------------------------------------

    public function testStoresListHidesCreateButtonAndActionsWithoutPermissions(): void
    {
        $html = $this->view->renderPartial('staff.stores', [
            'BASE_URL' => '',
            'stores'   => [['id' => 1, 'name' => 'Store A', 'code' => 'A', 'is_active' => 1]],
            'user_can' => $this->canFn([]),
        ]);

        $this->assertStringNotContainsString('/admin/stores/create', $html);
        $this->assertStringNotContainsString('/admin/stores/1/stats', $html);
        $this->assertStringNotContainsString('/admin/stores/1/employee-report', $html);
        $this->assertStringNotContainsString('/admin/stores/1/delete', $html);
    }

    public function testStoresListShowsCreateAndDeleteWhenGranted(): void
    {
        $html = $this->view->renderPartial('staff.stores', [
            'BASE_URL' => '',
            'stores'   => [['id' => 1, 'name' => 'Store A', 'code' => 'A', 'is_active' => 1]],
            'user_can' => $this->canFn(['stores.create', 'stores.delete', 'payroll.view']),
        ]);

        $this->assertStringContainsString('/admin/stores/create', $html);
        $this->assertStringContainsString('/admin/stores/1/stats', $html);
        $this->assertStringContainsString('/admin/stores/1/delete', $html);
    }

    // -------------------------------------------------------------------------
    // staff/stores-form.php
    // -------------------------------------------------------------------------

    public function testStoresFormHidesMemberManagementFormsWithoutPermissions(): void
    {
        $html = $this->view->renderPartial('staff.stores-form', [
            'BASE_URL' => '',
            'mode'     => 'edit',
            'store'    => ['id' => 1, 'name' => 'Store A'],
            'members'  => [['id' => 10, 'user_name' => 'Jean', 'user_email' => 'j@d.com', 'is_active' => 1, 'role_id' => 2]],
            'available' => [['id' => 20, 'first_name' => 'Paul', 'last_name' => 'Martin', 'email' => 'p@m.com']],
            'assignable_roles' => [['id' => 2, 'name' => 'Manager']],
            'user_can' => $this->canFn([]),
        ]);

        $this->assertStringNotContainsString('/members/10/role', $html);
        $this->assertStringNotContainsString('/members/10/deductions', $html);
        $this->assertStringNotContainsString('/members/10/delete', $html);
        $this->assertStringNotContainsString('/stores/1/members"', $html);
    }

    public function testStoresFormShowsMemberManagementFormsWhenGranted(): void
    {
        $html = $this->view->renderPartial('staff.stores-form', [
            'BASE_URL' => '',
            'mode'     => 'edit',
            'store'    => ['id' => 1, 'name' => 'Store A'],
            'members'  => [['id' => 10, 'user_name' => 'Jean', 'user_email' => 'j@d.com', 'is_active' => 1, 'role_id' => 2]],
            'available' => [['id' => 20, 'first_name' => 'Paul', 'last_name' => 'Martin', 'email' => 'p@m.com']],
            'assignable_roles' => [['id' => 2, 'name' => 'Manager']],
            'user_can' => $this->canFn(['employees.update', 'payroll.view']),
        ]);

        $this->assertStringContainsString('/members/10/role', $html);
        $this->assertStringContainsString('/members/10/deductions', $html);
        $this->assertStringContainsString('/members/10/delete', $html);
    }

    // -------------------------------------------------------------------------
    // staff/users-form.php
    // -------------------------------------------------------------------------

    public function testUsersFormIsReadOnlyWithoutUpdatePermission(): void
    {
        $html = $this->view->renderPartial('staff.users-form', [
            'BASE_URL' => '',
            'mode'     => 'edit',
            'user'     => ['id' => 5, 'display_name' => 'Jean Dupont'],
            'user_memberships' => [],
            'user_can' => $this->canFn(['employees.view']),
        ]);

        $this->assertStringContainsString('readonly_no_permission_notice', $html);
        $this->assertStringContainsString('<fieldset disabled', $html);
    }

    public function testUsersFormIsEditableWithUpdatePermission(): void
    {
        $html = $this->view->renderPartial('staff.users-form', [
            'BASE_URL' => '',
            'mode'     => 'edit',
            'user'     => ['id' => 5, 'display_name' => 'Jean Dupont'],
            'user_memberships' => [],
            'user_can' => $this->canFn(['employees.view', 'employees.update']),
        ]);

        $this->assertStringNotContainsString('readonly_no_permission_notice', $html);
        $this->assertStringNotContainsString('<fieldset disabled', $html);
    }

    public function testUsersFormAlwaysEditableInCreateMode(): void
    {
        // En création, aucune permission d'update préexistante à vérifier :
        // employees.create a déjà été validé par PermissionMiddleware pour atteindre la page.
        $html = $this->view->renderPartial('staff.users-form', [
            'BASE_URL' => '',
            'mode'     => 'create',
            'user'     => [],
            'user_memberships' => [],
            'user_can' => $this->canFn([]),
        ]);

        $this->assertStringNotContainsString('readonly_no_permission_notice', $html);
        $this->assertStringNotContainsString('<fieldset disabled', $html);
    }
}
