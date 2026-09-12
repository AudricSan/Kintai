<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Request;
use kintai\UI\Controller\Web\PwaController;
use PHPUnit\Framework\TestCase;

final class PwaControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
    }

    public function testManifestExposesInstallableAndMaskableIcons(): void
    {
        $_SERVER['HTTP_HOST']   = 'kintai.example';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $response = (new PwaController())->manifest(new Request());
        $data     = json_decode($response->body(), true);

        $this->assertSame('standalone', $data['display']);

        $purposes = array_column($data['icons'], 'purpose');
        $this->assertContains('any', $purposes);
        $this->assertContains('maskable', $purposes);

        foreach ($data['icons'] as $icon) {
            $this->assertStringEndsWith('.png', $icon['src']);
            $this->assertSame('image/png', $icon['type']);
        }
    }
}
