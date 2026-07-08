<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Cron;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use kintai\Core\Cron\CronJobInterface;
use kintai\Core\Cron\CronRunner;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\CronTokenRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class CronRunnerTest extends TestCase
{
    private CronTokenRepositoryInterface&MockObject $cronTokens;
    private CronJobInterface&MockObject $sampleJob;
    private CronRunner $runner;

    protected function setUp(): void
    {
        $this->cronTokens = $this->createMock(CronTokenRepositoryInterface::class);
        $this->sampleJob  = $this->createMock(CronJobInterface::class);
        $this->sampleJob->method('getName')->willReturn('test-job');

        $this->runner = new CronRunner($this->cronTokens);
        $this->runner->register($this->sampleJob);
    }

    // -------------------------------------------------------------------------
    // register() / getJob() / getJobs() / getJobMeta()
    // -------------------------------------------------------------------------

    public function testGetJobReturnsRegisteredJob(): void
    {
        $this->assertSame($this->sampleJob, $this->runner->getJob('test-job'));
    }

    public function testGetJobReturnsNullForUnknownJob(): void
    {
        $this->assertNull($this->runner->getJob('nope'));
    }

    public function testGetJobsReturnsAllRegisteredJobs(): void
    {
        $this->assertCount(1, $this->runner->getJobs());
        $this->assertArrayHasKey('test-job', $this->runner->getJobs());
    }

    public function testGetJobMetaReturnsStructuredArray(): void
    {
        $meta = $this->runner->getJobMeta();
        $this->assertCount(1, $meta);
        $this->assertSame('test-job', $meta[0]['name']);
        $this->assertNotEmpty($meta[0]['label']);
    }

    // -------------------------------------------------------------------------
    // run() — validation du token
    // -------------------------------------------------------------------------

    public function testRunWithValidQueryTokenExecutesJob(): void
    {
        $req = $this->requestWithQuery('valid-raw-token');

        $this->cronTokens->method('findByToken')
            ->willReturnCallback(fn(string $h) => hash_equals(hash_token('valid-raw-token'), $h)
                ? ['id' => 1, 'user_id' => 1, 'job_name' => 'test-job', 'token' => $h, 'expires_at' => null]
                : null);

        $this->cronTokens->expects($this->once())->method('touchLastUsed')->with(1);
        $this->sampleJob->expects($this->once())->method('run')->with($req)->willReturn(Response::json(['ok' => true]));

        $response = $this->runner->run('test-job', $req);
        $this->assertSame(200, $response->status());
    }

    public function testRunWithValidBearerTokenExecutesJob(): void
    {
        $req = $this->requestWithBearer('valid-bearer-token');

        $this->cronTokens->method('findByToken')
            ->willReturnCallback(fn(string $h) => hash_equals(hash_token('valid-bearer-token'), $h)
                ? ['id' => 2, 'user_id' => 1, 'job_name' => 'test-job', 'token' => $h, 'expires_at' => null]
                : null);

        $this->cronTokens->expects($this->once())->method('touchLastUsed')->with(2);
        $this->sampleJob->method('run')->willReturn(Response::json(['ok' => true]));

        $response = $this->runner->run('test-job', $req);
        $this->assertSame(200, $response->status());
    }

    public function testRunThrowsForbiddenWhenNoToken(): void
    {
        $req = $this->makeCleanRequest();

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Token cron requis.');
        $this->runner->run('test-job', $req);
    }

    public function testRunThrowsForbiddenWhenTokenNotFound(): void
    {
        $req = $this->requestWithQuery('unknown-token');

        $this->cronTokens->method('findByToken')->willReturn(null);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Token cron invalide.');
        $this->runner->run('test-job', $req);
    }

    public function testRunThrowsForbiddenWhenTokenExpired(): void
    {
        $req = $this->requestWithQuery('expired-token');

        $this->cronTokens->method('findByToken')
            ->willReturnCallback(fn(string $h) => hash_equals(hash_token('expired-token'), $h)
                ? ['id' => 3, 'user_id' => 1, 'job_name' => 'test-job', 'token' => $h, 'expires_at' => '2020-01-01 00:00:00']
                : null);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Token cron expiré.');
        $this->runner->run('test-job', $req);
    }

    public function testRunThrowsNotFoundWhenJobUnknown(): void
    {
        $req = $this->requestWithQuery('some-token');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Job cron 'unknown-job' inconnu.");
        $this->runner->run('unknown-job', $req);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCleanRequest(): Request
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        return new Request();
    }

    private function requestWithQuery(string $token): Request
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/cron/run/test?token=$token", 'SCRIPT_NAME' => '/index.php'];
        $_GET    = ['token' => $token];
        $_POST   = $_COOKIE = $_FILES = [];
        return new Request();
    }

    private function requestWithBearer(string $token): Request
    {
        $_SERVER = [
            'REQUEST_METHOD'           => 'GET',
            'REQUEST_URI'              => '/api/v1/cron/run/test',
            'SCRIPT_NAME'              => '/index.php',
            'HTTP_AUTHORIZATION'       => "Bearer $token",
        ];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        return new Request();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET    = [];
        $_POST   = [];
    }
}
