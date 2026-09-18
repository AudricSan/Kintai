<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web\System;

use kintai\Core\Repositories\BundleRegistryRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\System\BundleMarketController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 5));
}

final class BundleMarketControllerTest extends TestCase
{
    private BundleRegistryRepositoryInterface&MockObject $registries;

    protected function setUp(): void
    {
        $this->ensureViewFile('system.bundle-registries');
        $this->ensureViewFile('layout.app');
        $this->registries = $this->createMock(BundleRegistryRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    private function makeController(): BundleMarketController
    {
        return new BundleMarketController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->registries,
            new AuditLogger(),
        );
    }

    public function testIndexRendersPage(): void
    {
        $this->registries->method('all')->willReturn([]);

        $response = $this->makeController()->index(new Request());

        $this->assertSame(200, $response->status());
    }

    public function testStoreRejectsInvalidUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'not-a-url'];

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testStoreRejectsNonHttpsUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'http://example.test/registry.json'];

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('error=invalid', $this->locationOf($response));
    }

    public function testStoreRejectsDuplicateUrl(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'https://example.test/registry.json'];
        $this->registries->method('existsByUrl')->willReturn(true);

        $this->registries->expects($this->never())->method('create');

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('error=duplicate', $this->locationOf($response));
    }

    public function testStoreCreatesRegistryAndRedirectsToSuccess(): void
    {
        $_POST = ['name' => 'Mon registry', 'url' => 'https://example.test/registry.json'];
        $this->registries->method('existsByUrl')->willReturn(false);
        $this->registries->expects($this->once())->method('create')
            ->with('Mon registry', 'https://example.test/registry.json')
            ->willReturn(['id' => 3, 'name' => 'Mon registry', 'url' => 'https://example.test/registry.json', 'is_official' => false]);

        $response = $this->makeController()->store(new Request());

        $this->assertStringContainsString('success=created', $this->locationOf($response));
    }

    public function testDestroyRefusesToDeleteTheOfficialRegistry(): void
    {
        $this->registries->method('find')->willReturn(['id' => 1, 'name' => 'Officiel', 'url' => 'https://example.test/official.json', 'is_official' => true]);
        $this->registries->expects($this->never())->method('delete');

        $req = new Request();
        $req->setRouteParams(['id' => '1']);

        $response = $this->makeController()->destroy($req);

        $this->assertStringContainsString('error=delete_official_forbidden', $this->locationOf($response));
    }

    public function testDestroyDeletesANonOfficialRegistry(): void
    {
        $this->registries->method('find')->willReturn(['id' => 2, 'name' => 'Perso', 'url' => 'https://example.test/perso.json', 'is_official' => false]);
        $this->registries->expects($this->once())->method('delete')->with(2);

        $req = new Request();
        $req->setRouteParams(['id' => '2']);

        $response = $this->makeController()->destroy($req);

        $this->assertStringContainsString('success=deleted', $this->locationOf($response));
    }

    private function locationOf(\kintai\Core\Response $response): string
    {
        $ref = new \ReflectionProperty($response, 'headers');
        $ref->setAccessible(true);
        return $ref->getValue($response)['Location'] ?? '';
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
