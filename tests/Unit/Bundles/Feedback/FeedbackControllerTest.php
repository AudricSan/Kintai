<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Feedback;

use kintai\Bundles\Feedback\Controllers\Web\FeedbackController;
use kintai\Core\Container;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FeedbackControllerTest extends TestCase
{
    private FeedbackRepositoryInterface&MockObject $feedbacks;
    private ShiftRepositoryInterface&MockObject $shifts;
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private UserRepositoryInterface&MockObject $users;
    private LogRepositoryInterface&MockObject $logRepo;
    private FeedbackController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-feedback-views';
        $this->ensureViewFile($viewDir, 'feedbacks');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('feedback', $viewDir);

        $this->feedbacks = $this->createMock(FeedbackRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);

        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new FeedbackController(
            $view,
            $this->feedbacks,
            $this->shifts,
            $this->shiftTypes,
            $this->stores,
            $this->storeUsers,
            $this->users,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    public function testSubmitRejectsEmptyMessage(): void
    {
        $_POST = ['category' => 'other', 'message' => '', 'return_to' => '/employee'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->feedbacks->expects($this->never())->method('save');

        $response = $this->controller->submit($req);

        $this->assertSame(302, $response->status());
    }

    public function testSubmitSavesAnonymousFeedback(): void
    {
        $_POST = [
            'category'  => 'other',
            'message'   => 'Super app !',
            'anonymous' => '1',
            'return_to' => '/employee',
        ];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->feedbacks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['store_id'] === 5
                && $data['user_id'] === null
                && $data['message'] === 'Super app !'
                && $data['anonymous'] === 1
        ))->willReturn(['id' => 10]);

        $response = $this->controller->submit($req);

        $this->assertSame(302, $response->status());
    }

    public function testSubmitRejectsDuplicateShiftFeedback(): void
    {
        $_POST = [
            'category'  => 'shift',
            'shift_id'  => '7',
            'message'   => 'Commentaire',
            'return_to' => '/employee',
        ];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->shifts->method('findById')->with(7)->willReturn(['id' => 7, 'user_id' => 1]);
        $this->feedbacks->method('findByShift')->with(7)->willReturn(['id' => 99]);
        $this->feedbacks->expects($this->never())->method('save');

        $response = $this->controller->submit($req);

        $this->assertSame(302, $response->status());
    }

    public function testIndexRendersFeedbacksFilteredByManagedStores(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', [5]);

        $this->feedbacks->method('findByStore')->with(5)->willReturn([
            ['id' => 1, 'store_id' => 5, 'created_at' => '2026-08-01 10:00:00', 'category' => 'other'],
        ]);
        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->shifts->method('findAll')->willReturn([]);

        $response = $this->controller->index($req);

        $this->assertSame(200, $response->status());
    }

    public function testDeleteRemovesFeedbackAndLogs(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '10']);

        $this->feedbacks->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 5, 'category' => 'other']);
        $this->feedbacks->expects($this->once())->method('delete')->with(10);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'feedback.deleted', 'employee_feedback', 10,
            $this->anything(), $this->anything(), 5,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->delete($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteForbiddenForUnmanagedStore(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1, 2]);
        $req->setRouteParams(['id' => '10']);

        $this->feedbacks->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 5]);
        $this->feedbacks->expects($this->never())->method('delete');

        $response = $this->controller->delete($req);

        $this->assertSame(302, $response->status());
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }
}
