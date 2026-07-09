<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Timeclock\Api;

use kintai\Bundles\Timeclock\Controllers\Api\TimeclockController;
use kintai\Core\Exceptions\ConflictException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&MockObject $timeclocks;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private TimeclockController $controller;

    protected function setUp(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->controller = new TimeclockController($this->timeclocks, $this->storeUsers, new AuditLogger());
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testIndexReturnsEmptyWithoutFilters(): void
    {
        $req = new Request();
        $response = $this->controller->index($req);

        $body = json_decode($response->body(), true);
        $this->assertSame([], $body['data']);
    }

    public function testIndexFiltersByStoreAndDate(): void
    {
        $_GET = ['store_id' => '5', 'date' => '2026-08-01'];
        $req = new Request();

        $this->timeclocks->method('findByStoreAndDate')->with(5, '2026-08-01')->willReturn([['id' => 1]]);

        $response = $this->controller->index($req);
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
    }

    public function testClockInRejectsWhenAlreadyActive(): void
    {
        $req = $this->requestWithJson(['user_id' => 1]);

        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(['id' => 5]);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(ConflictException::class);
        $this->controller->clockIn($req);
    }

    public function testClockOutThrowsWhenNoActiveEntry(): void
    {
        $req = $this->requestWithJson(['user_id' => 1]);

        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->clockOut($req);
    }

    /**
     * Simule un corps JSON via réflexion : lire php://input n'est pas possible
     * dans un test unitaire (cf. tests/Unit/Controller/Api/V1/AuthControllerTest.php).
     */
    private function requestWithJson(array $json): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        return $req;
    }

    public function testDestroyDeletesExistingEntry(): void
    {
        $this->timeclocks->method('findById')->with(7)->willReturn(['id' => 7]);
        $this->timeclocks->expects($this->once())->method('delete')->with(7);

        $req = new Request();
        $req->setRouteParams(['id' => '7']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }
}
