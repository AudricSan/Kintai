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
use kintai\Core\Services\UpdateService;
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
    private string $tmpDir;

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

        $this->tmpDir = sys_get_temp_dir() . '/kintai_feedbackctrl_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/config', 0775, true);
        file_put_contents(
            $this->tmpDir . '/config/app.php',
            "<?php return ['version' => '9.9.9'];"
        );

        $this->controller = new FeedbackController(
            $view,
            $this->feedbacks,
            $this->shifts,
            $this->shiftTypes,
            $this->stores,
            $this->storeUsers,
            $this->users,
            new AuditLogger(),
            new UpdateService($this->tmpDir),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmpDir);
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
            'return_to' => '/employee/planning?week=2026-07-13',
        ];
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->feedbacks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['store_id'] === 5
                && $data['user_id'] === null
                && $data['message'] === 'Super app !'
                && $data['anonymous'] === 1
                && $data['page_path'] === '/employee/planning'
                && $data['app_version'] === '9.9.9'
                && $data['device_type'] === 'desktop'
        ))->willReturn(['id' => 10]);

        $response = $this->controller->submit($req);

        $this->assertSame(302, $response->status());
    }

    public function testSubmitDetectsMobileDeviceFromUserAgent(): void
    {
        $_POST = ['category' => 'app', 'message' => 'Le bouton export ne répond pas.', 'return_to' => '/employee'];
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->feedbacks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['device_type'] === 'mobile'
        ))->willReturn(['id' => 11]);

        $response = $this->controller->submit($req);

        $this->assertSame(302, $response->status());
    }

    public function testSubmitAllowsUserWithNoStoreMembership(): void
    {
        // L'Owner (ou tout rôle global) n'a pas forcément d'adhésion à un store
        // (voir install.php, qui n'assigne que role_assignments) — le feedback
        // doit rester soumissible, avec store_id à null plutôt qu'une erreur.
        $_POST = ['category' => 'app', 'message' => 'Bug sur la page owner-settings', 'return_to' => '/admin/owner-settings'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 99]);

        $this->storeUsers->method('findByUser')->with(99)->willReturn([]);
        $this->feedbacks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['store_id'] === null && $data['user_id'] === 99
        ))->willReturn(['id' => 12]);

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

    public function testDeleteForbiddenForManagerOnStorelessFeedback(): void
    {
        // Un feedback sans store (soumis par l'Owner) ne doit jamais être supprimable
        // par un manager scopé à des stores précis — seul l'Owner (managed_store_ids null) le peut.
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1, 2]);
        $req->setRouteParams(['id' => '10']);

        $this->feedbacks->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => null]);
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
