<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Feedback\Api;

use kintai\Bundles\Feedback\Controllers\Api\FeedbackController;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FeedbackControllerTest extends TestCase
{
    private FeedbackRepositoryInterface&MockObject $feedbacks;
    private FeedbackController $controller;

    protected function setUp(): void
    {
        $this->feedbacks = $this->createMock(FeedbackRepositoryInterface::class);
        $this->controller = new FeedbackController($this->feedbacks);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testIndexReturnsAllFeedbacksByDefault(): void
    {
        $this->feedbacks->method('findAll')->willReturn([['id' => 1], ['id' => 2]]);

        $req = new Request();
        $response = $this->controller->index($req);

        $this->assertSame(200, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertCount(2, $body['data']);
    }

    public function testIndexFiltersByShiftId(): void
    {
        $_GET = ['shift_id' => '7'];
        $req = new Request();

        $this->feedbacks->method('findByShift')->with(7)->willReturn(['id' => 3, 'shift_id' => 7]);

        $response = $this->controller->index($req);
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
    }

    public function testShowThrowsNotFoundForMissingFeedback(): void
    {
        $this->feedbacks->method('findById')->with(99)->willReturn(null);

        $req = new Request();
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->show($req);
    }

    public function testDestroyDeletesExistingFeedback(): void
    {
        $this->feedbacks->method('findById')->with(5)->willReturn(['id' => 5]);
        $this->feedbacks->expects($this->once())->method('delete')->with(5);

        $req = new Request();
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }
}
