<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class FormUserPartialTest extends TestCase
{
    private function render(string $mode, array $user, array $all_stores): string
    {
        $BASE_URL = '';
        ob_start();
        include __DIR__ . '/../../../src/UI/View/_partials/_form-user.php';
        return ob_get_clean();
    }

    // Contexte de la modale de création rapide (import de shifts) : le store est déjà
    // fixé par un champ <input type="hidden" name="store_id"> du formulaire parent.
    // Si ce partial rendait aussi un <select name="store_id">, la valeur du champ
    // caché serait écrasée côté serveur (PHP ne retient que la dernière occurrence
    // d'un nom de champ POST dupliqué), empêchant tout rattachement au store.
    public function testCreateModeWithoutStoresOmitsStoreIdSelect(): void
    {
        $html = $this->render('create', [], []);

        $this->assertStringNotContainsString('name="store_id"', $html);
        $this->assertStringContainsString('name="store_role"', $html);
    }

    public function testCreateModeWithStoresRendersStoreIdSelect(): void
    {
        $html = $this->render('create', [], [
            ['id' => 3, 'name' => 'Store A', 'code' => 'A'],
        ]);

        $this->assertStringContainsString('name="store_id"', $html);
        $this->assertStringContainsString('name="store_role"', $html);
    }

    public function testEditModeNeverRendersStoreAssignmentBlock(): void
    {
        $html = $this->render('edit', ['id' => 1, 'display_name' => 'Jane'], [
            ['id' => 3, 'name' => 'Store A', 'code' => 'A'],
        ]);

        $this->assertStringNotContainsString('name="store_id"', $html);
        $this->assertStringNotContainsString('name="store_role"', $html);
    }
}
