<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class FormUserPartialTest extends TestCase
{
    private function render(string $mode, array $user, array $all_stores, array $assignable_roles = [], int $default_store_role_id = 0): string
    {
        $BASE_URL = '';
        ob_start();
        include __DIR__ . '/../../../src/UI/View/_partials/_form-user.php';
        return ob_get_clean();
    }

    private const ROLES = [
        ['id' => 2, 'name' => 'Manager', 'is_managing' => true],
        ['id' => 3, 'name' => 'Employé', 'is_managing' => false],
    ];

    // Contexte de la modale de création rapide (import de shifts) : le store est déjà
    // fixé par un champ <input type="hidden" name="store_id"> du formulaire parent.
    // Si ce partial rendait aussi un <select name="store_id">, la valeur du champ
    // caché serait écrasée côté serveur (PHP ne retient que la dernière occurrence
    // d'un nom de champ POST dupliqué), empêchant tout rattachement au store.
    public function testCreateModeWithoutStoresOmitsStoreIdSelect(): void
    {
        $html = $this->render('create', [], [], self::ROLES, 3);

        $this->assertStringNotContainsString('name="store_id"', $html);
        $this->assertStringContainsString('name="store_role_id"', $html);
    }

    public function testCreateModeWithStoresRendersStoreIdSelect(): void
    {
        $html = $this->render('create', [], [
            ['id' => 3, 'name' => 'Store A', 'code' => 'A'],
        ], self::ROLES, 3);

        $this->assertStringContainsString('name="store_id"', $html);
        $this->assertStringContainsString('name="store_role_id"', $html);
    }

    public function testStoreRoleSelectListsDynamicRolesAndSelectsDefault(): void
    {
        $html = $this->render('create', [], [], self::ROLES, 3);

        $this->assertStringContainsString('Manager', $html);
        $this->assertStringContainsString('Employé', $html);
        $this->assertStringContainsString('<option value="3" selected>', $html);
    }

    public function testStoreRoleSelectOmittedWithoutAssignableRoles(): void
    {
        $html = $this->render('create', [], []);

        $this->assertStringNotContainsString('name="store_role_id"', $html);
    }

    public function testEditModeNeverRendersStoreAssignmentBlock(): void
    {
        $html = $this->render('edit', ['id' => 1, 'display_name' => 'Jane'], [
            ['id' => 3, 'name' => 'Store A', 'code' => 'A'],
        ], self::ROLES, 3);

        $this->assertStringNotContainsString('name="store_id"', $html);
        $this->assertStringNotContainsString('name="store_role_id"', $html);
    }
}
