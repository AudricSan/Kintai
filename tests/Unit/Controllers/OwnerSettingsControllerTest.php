<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controllers;

use kintai\Core\Container;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\Controller\Web\System\OwnerSettingsController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class OwnerSettingsControllerTest extends TestCase
{
    /** Snapshot de app_settings vu par le test courant — modifié en place par setMany(). */
    private array $stored = [];

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/system';
        $layoutDir = sys_get_temp_dir() . '/layout';
        foreach ([$viewDir, $layoutDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        touch($viewDir . '/owner-settings.php');
        touch($layoutDir . '/app.php');

        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->createMock(LogRepositoryInterface::class));
        Log::setContainer($container);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        Log::reset();
    }

    /**
     * Construit un contrôleur dont l'AppSettingsService voit $initialStored comme état
     * initial — doit être appelé APRÈS avoir préparé $this->stored, car AppSettingsService
     * met en cache repo->all() une seule fois, à la construction.
     */
    private function makeController(array $initialStored = []): OwnerSettingsController
    {
        $this->stored = $initialStored;

        $repo = $this->createMock(AppSettingsRepositoryInterface::class);
        $repo->method('all')->willReturnCallback(fn() => $this->stored);
        $repo->method('setMany')->willReturnCallback(function (array $settings): void {
            foreach ($settings as $k => $v) {
                $this->stored[(string) $k] = (string) $v;
            }
        });

        $view = new ViewRenderer(sys_get_temp_dir());

        return new OwnerSettingsController($view, new AppSettingsService($repo), new AuditLogger());
    }

    public function testShowRendersWithDefaultFoxyColors(): void
    {
        $controller = $this->makeController();

        $response = $controller->show(new Request());

        $this->assertSame(200, $response->status());
    }

    public function testSaveStoresACustomLightColor(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_success_color' => '#00FF00'];

        $controller->save(new Request());

        $this->assertSame('#00ff00', $this->stored['app_success_color']);
    }

    public function testSaveFallsBackToPreviousValueOnInvalidHex(): void
    {
        $controller = $this->makeController(['app_danger_color' => '#123456']);
        $_POST = ['app_danger_color' => 'not-a-color'];

        $controller->save(new Request());

        $this->assertSame('#123456', $this->stored['app_danger_color']);
    }

    public function testSavePersistsDarkModeToggle(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_theme_dark_mode' => 'manual'];

        $controller->save(new Request());

        $this->assertSame('manual', $this->stored['app_theme_dark_mode']);
    }

    public function testSaveRejectsUnknownDarkModeValue(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_theme_dark_mode' => 'nightly'];

        $controller->save(new Request());

        $this->assertSame('auto', $this->stored['app_theme_dark_mode']);
    }

    public function testSaveStoresAManualDarkColor(): void
    {
        $controller = $this->makeController();
        $_POST = [
            'app_theme_dark_mode'    => 'manual',
            'app_primary_color_dark' => '#112233',
        ];

        $controller->save(new Request());

        $this->assertSame('#112233', $this->stored['app_primary_color_dark']);
    }

    public function testSaveClearsAnInvalidManualDarkColorToEmptyMeaningAuto(): void
    {
        $controller = $this->makeController(['app_primary_color_dark' => '#112233']);
        $_POST = ['app_primary_color_dark' => 'nope'];

        $controller->save(new Request());

        $this->assertSame('', $this->stored['app_primary_color_dark']);
    }

    public function testSaveRedirectsWithSuccessFlag(): void
    {
        $controller = $this->makeController();

        $response = $controller->save(new Request());

        $this->assertSame(302, $response->status());
    }
}
