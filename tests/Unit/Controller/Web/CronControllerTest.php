<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Cron\CronJobInterface;
use kintai\Core\Cron\CronRunner;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\CronTokenRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\Controller\Web\CronController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Régression : le job cron `backup` (CronRunner + BackupJob + cron_tokens) était
 * entièrement codé mais jamais branché à une route. Ces tests couvrent le nouveau
 * point d'entrée générique /cron/run/{job}.
 */
final class CronControllerTest extends TestCase
{
    private CronTokenRepositoryInterface&MockObject $cronTokens;
    private CronController $controller;

    protected function setUp(): void
    {
        $this->cronTokens = $this->createMock(CronTokenRepositoryInterface::class);
        $runner = new CronRunner($this->cronTokens);

        $stubJob = new class implements CronJobInterface {
            public function getName(): string
            {
                return 'stub-job';
            }

            public function run(Request $request): Response
            {
                return Response::json(['ok' => true, 'ran' => $this->getName()]);
            }
        };
        $runner->register($stubJob);

        // CronController est un final class avec des dépendances lourdes
        // (DailyReportAutoValidateService) non nécessaires pour tester run() ;
        // on l'instancie sans constructeur et on injecte juste le CronRunner.
        $this->controller = (new ReflectionClass(CronController::class))->newInstanceWithoutConstructor();
        $prop = new ReflectionProperty(CronController::class, 'cronRunner');
        $prop->setAccessible(true);
        $prop->setValue($this->controller, $runner);
    }

    public function testRunDispatchesToTheRegisteredJobWithAValidToken(): void
    {
        $this->cronTokens->method('findByToken')->willReturn([
            'id' => 1, 'user_id' => 1, 'job_name' => 'stub-job', 'token' => 'hashed',
        ]);
        $this->cronTokens->expects($this->once())->method('touchLastUsed')->with(1);

        $_GET['token'] = 'raw-token';
        $req = new Request();
        $req->setRouteParams(['job' => 'stub-job']);

        $response = $this->controller->run($req);

        $this->assertSame(200, $response->status());
    }

    public function testRunRejectsMissingToken(): void
    {
        $req = new Request();
        $req->setRouteParams(['job' => 'stub-job']);
        $_GET = [];

        $this->expectException(ForbiddenException::class);
        $this->controller->run($req);
    }

    public function testRunRejectsTokenScopedToADifferentJob(): void
    {
        $this->cronTokens->method('findByToken')->willReturn([
            'id' => 1, 'user_id' => 1, 'job_name' => 'backup', 'token' => 'hashed',
        ]);

        $_GET['token'] = 'raw-token';
        $req = new Request();
        $req->setRouteParams(['job' => 'stub-job']);

        $this->expectException(ForbiddenException::class);
        $this->controller->run($req);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }
}
