<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Request;
use kintai\UI\Controller\Api\V1\NotificationController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationControllerTest extends TestCase
{
    private NotificationRepositoryInterface&MockObject $notifications;
    private NotificationController $controller;

    protected function setUp(): void
    {
        $this->notifications = $this->createMock(NotificationRepositoryInterface::class);
        $this->controller    = new NotificationController($this->notifications);
    }

    /**
     * Régression : DELETE /api/v1/notifications/{id} appelait markRead() au lieu de
     * réellement supprimer la notification.
     */
    public function testDestroyActuallyDeletesTheNotification(): void
    {
        $this->notifications->method('findById')->with(7)->willReturn(['id' => 7, 'user_id' => 1]);
        $this->notifications->expects($this->once())->method('delete')->with(7);
        $this->notifications->expects($this->never())->method('markRead');

        $req = new Request();
        $req->setRouteParams(['id' => '7']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyThrowsNotFoundForMissingNotification(): void
    {
        $this->notifications->method('findById')->with(99)->willReturn(null);

        $req = new Request();
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->destroy($req);
    }

    public function testMarkReadStillMarksReadRatherThanDeleting(): void
    {
        $this->notifications->method('findById')->with(7)->willReturn(['id' => 7, 'user_id' => 1]);
        $this->notifications->expects($this->once())->method('markRead')->with(7, 1);
        $this->notifications->expects($this->never())->method('delete');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '7']);

        $response = $this->controller->markRead($req);

        $this->assertSame(204, $response->status());
    }
}
