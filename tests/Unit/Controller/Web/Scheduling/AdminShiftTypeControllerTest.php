<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\Scheduling;

use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\Controller\Web\Scheduling\AdminShiftTypeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminShiftTypeControllerTest extends TestCase
{
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private StoreRepositoryInterface&MockObject $stores;
    private AdminShiftTypeController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('scheduling.shift-types');
        $this->ensureViewFile('scheduling.shift-types-form');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());

        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->stores      = $this->createMock(StoreRepositoryInterface::class);

        $this->controller = new AdminShiftTypeController(
            $view,
            $this->shiftTypes,
            $this->stores,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET  = [];
        $_POST = [];
        Log::reset();
    }

    // -------------------------------------------------------------------------
    // storeShiftType — création
    // -------------------------------------------------------------------------

    public function testStoreShiftTypeRejectsEmptyStoreSelection(): void
    {
        $_POST = ['code' => 'MORNING', 'name' => 'Matin'];
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->expects($this->never())->method('save');

        $response = $this->controller->storeShiftType($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreShiftTypeSyncsSelectedStores(): void
    {
        $_POST = ['code' => 'morning', 'name' => 'Matin', 'store_ids' => ['1', '2']];
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('save')->willReturn(['id' => 5, 'code' => 'MORNING']);
        $this->shiftTypes->expects($this->once())->method('syncStores')->with(5, [1, 2]);

        $response = $this->controller->storeShiftType($req);

        $this->assertSame(302, $response->status());
    }

    /** Un manager ne gérant aucun des stores cochés ne peut pas créer le type. */
    public function testStoreShiftTypeForbiddenWhenNoManagedStoreSelected(): void
    {
        $_POST = ['code' => 'MORNING', 'name' => 'Matin', 'store_ids' => ['99']];
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', [1, 2]);

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller->storeShiftType($req);
    }

    // -------------------------------------------------------------------------
    // updateShiftType — édition
    // -------------------------------------------------------------------------

    public function testUpdateShiftTypeRejectsEmptyStoreSelection(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING', 'name' => 'Matin']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([1]);

        $_POST = ['code' => 'MORNING', 'name' => 'Matin'];
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 5]);

        $this->shiftTypes->expects($this->never())->method('syncStores');

        $response = $this->controller->updateShiftType($req);

        $this->assertSame(302, $response->status());
    }

    /**
     * Un manager scopé sur le store 1 ne voit dans son formulaire que ce
     * store : décocher (implicite, absent du POST) ne doit pas faire
     * disparaître l'affectation existante au store 2 qu'il ne gère pas.
     */
    public function testUpdateShiftTypePreservesOutOfScopeStoreAssignments(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING', 'name' => 'Matin']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([1, 2]); // 2 = hors scope du manager
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);

        $_POST = ['code' => 'MORNING', 'name' => 'Matin', 'store_ids' => ['1']]; // ne peut pas cocher le 2
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', [1]); // ne gère que le store 1
        $req->setRouteParams(['id' => 5]);

        $this->shiftTypes->expects($this->once())->method('syncStores')->with(
            5,
            $this->callback(function ($ids) {
                sort($ids);
                return $ids === [1, 2];
            })
        );

        $response = $this->controller->updateShiftType($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateShiftTypeForbiddenWhenManagingNoneOfExistingStores(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING', 'name' => 'Matin']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([2]);

        $_POST = ['code' => 'MORNING', 'name' => 'Matin', 'store_ids' => ['2']];
        $req = $this->makePostRequest($_POST);
        $req->setAttribute('managed_store_ids', [1]);
        $req->setRouteParams(['id' => 5]);

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller->updateShiftType($req);
    }

    // -------------------------------------------------------------------------
    // deleteShiftType
    // -------------------------------------------------------------------------

    public function testDeleteShiftTypeForbiddenWhenNotManagingAnyOfItsStores(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([2]);

        $req = $this->makePostRequest([]);
        $req->setAttribute('managed_store_ids', [1]);
        $req->setRouteParams(['id' => 5]);

        $this->shiftTypes->expects($this->never())->method('delete');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller->deleteShiftType($req);
    }

    public function testDeleteShiftTypeAllowedWhenManagingOneOfItsStores(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([1, 2]);

        $req = $this->makePostRequest([]);
        $req->setAttribute('managed_store_ids', [1]);
        $req->setRouteParams(['id' => 5]);

        $this->shiftTypes->expects($this->once())->method('delete')->with(5);

        $response = $this->controller->deleteShiftType($req);

        $this->assertSame(302, $response->status());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePostRequest(array $post): Request
    {
        $_POST = $post;
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
