<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\Controller\Api\V1\ShiftTypeController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ShiftTypeControllerTest extends TestCase
{
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private ShiftTypeController $controller;

    protected function setUp(): void
    {
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->controller = new ShiftTypeController($this->shiftTypes, new AuditLogger());
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    private function makeJsonRequest(array $body): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $body);
        return $req;
    }

    public function testShowIncludesStoreIds(): void
    {
        $this->shiftTypes->method('findById')->with(5)->willReturn(['id' => 5, 'code' => 'MORNING']);
        $this->shiftTypes->method('getStoreIds')->with(5)->willReturn([1, 2]);

        $req = new Request();
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->show($req);

        $data = json_decode($response->body(), true);
        $this->assertSame([1, 2], $data['store_ids']);
    }

    public function testStoreWithStoreIdsArraySyncsAllStores(): void
    {
        $req = $this->makeJsonRequest(['code' => 'MORNING', 'name' => 'Matin', 'store_ids' => [1, 2]]);

        $this->shiftTypes->method('save')->willReturn(['id' => 9]);
        $this->shiftTypes->expects($this->once())->method('syncStores')->with(9, [1, 2]);
        $this->shiftTypes->method('getStoreIds')->willReturn([1, 2]);

        $response = $this->controller->store($req);

        $this->assertSame(201, $response->status());
    }

    /** store_id scalaire (rétrocompatibilité) : toujours accepté, converti en tableau à 1 élément. */
    public function testStoreWithLegacyScalarStoreId(): void
    {
        $req = $this->makeJsonRequest(['code' => 'MORNING', 'name' => 'Matin', 'store_id' => 3]);

        $this->shiftTypes->method('save')->willReturn(['id' => 9]);
        $this->shiftTypes->expects($this->once())->method('syncStores')->with(9, [3]);
        $this->shiftTypes->method('getStoreIds')->willReturn([3]);

        $this->controller->store($req);
    }

    public function testStoreDoesNotPassStoreIdKeyToRepositorySave(): void
    {
        $req = $this->makeJsonRequest(['code' => 'MORNING', 'name' => 'Matin', 'store_id' => 3]);

        $captured = null;
        $this->shiftTypes->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 9];
        });
        $this->shiftTypes->method('getStoreIds')->willReturn([3]);

        $this->controller->store($req);

        $this->assertArrayNotHasKey('store_id', $captured);
        $this->assertArrayNotHasKey('store_ids', $captured);
    }

    /** Mise à jour partielle : store_ids absent du corps ne touche pas aux affectations existantes. */
    public function testUpdateWithoutStoreIdsLeavesAssignmentsUntouched(): void
    {
        $this->shiftTypes->method('findById')->with(9)->willReturn(['id' => 9, 'code' => 'MORNING']);
        $req = $this->makeJsonRequest(['name' => 'Matin renommé']);
        $req->setRouteParams(['id' => '9']);

        $this->shiftTypes->method('save')->willReturn(['id' => 9, 'name' => 'Matin renommé']);
        $this->shiftTypes->expects($this->never())->method('syncStores');
        $this->shiftTypes->method('getStoreIds')->willReturn([1]);

        $response = $this->controller->update($req);

        $this->assertSame(200, $response->status());
    }

    public function testUpdateWithStoreIdsReplacesAssignments(): void
    {
        $this->shiftTypes->method('findById')->with(9)->willReturn(['id' => 9, 'code' => 'MORNING']);
        $req = $this->makeJsonRequest(['store_ids' => [4, 5]]);
        $req->setRouteParams(['id' => '9']);

        $this->shiftTypes->method('save')->willReturn(['id' => 9]);
        $this->shiftTypes->expects($this->once())->method('syncStores')->with(9, [4, 5]);
        $this->shiftTypes->method('getStoreIds')->willReturn([4, 5]);

        $this->controller->update($req);
    }
}
