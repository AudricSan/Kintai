<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\StorePhoto;

use kintai\Bundles\StorePhoto\Services\ImageCompressionService;
use PHPUnit\Framework\TestCase;

final class ImageCompressionServiceTest extends TestCase
{
    private ImageCompressionService $service;
    private string $workDir;

    protected function setUp(): void
    {
        $this->service = new ImageCompressionService();
        $this->workDir = sys_get_temp_dir() . '/kintai-image-compression-' . uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);
    }

    public function testCompressesLargeOpaqueImageBelowTargetSizeAsJpeg(): void
    {
        $source = $this->workDir . '/source.png';
        $this->writeNoisyOpaquePng($source, 1200, 900);
        $this->assertGreaterThan(ImageCompressionService::DEFAULT_MAX_BYTES, filesize($source));

        $result = $this->service->compress($source, $this->workDir . '/out', ImageCompressionService::DEFAULT_MAX_BYTES);

        $this->assertNotNull($result);
        $this->assertSame('jpg', $result['extension']);
        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertLessThanOrEqual(ImageCompressionService::DEFAULT_MAX_BYTES, $result['size']);
        $this->assertFileExists($result['path']);
    }

    public function testKeepsPngWithAlphaChannelForTransparentImages(): void
    {
        $source = $this->workDir . '/transparent.png';
        $this->writeTransparentPng($source, 300, 300);

        $result = $this->service->compress($source, $this->workDir . '/out', ImageCompressionService::DEFAULT_MAX_BYTES);

        $this->assertNotNull($result);
        $this->assertSame('png', $result['extension']);
        $this->assertSame('image/png', $result['mime']);
    }

    public function testShrinksDimensionsWhenMinimumQualityStillExceedsTinyBudget(): void
    {
        $source = $this->workDir . '/source.png';
        $this->writeNoisyOpaquePng($source, 1200, 900);

        // Budget volontairement irréaliste pour forcer plusieurs passes de redimensionnement.
        $result = $this->service->compress($source, $this->workDir . '/out', 8 * 1024);

        $this->assertNotNull($result);
        [$width, $height] = getimagesize($result['path']);
        $this->assertLessThan(1200, $width);
        $this->assertLessThan(900, $height);
    }

    public function testReturnsNullForNonImageFile(): void
    {
        $source = $this->workDir . '/not-an-image.jpg';
        file_put_contents($source, 'this is definitely not image bytes');

        $result = $this->service->compress($source, $this->workDir . '/out');

        $this->assertNull($result);
    }

    private function writeNoisyOpaquePng(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 3) {
            for ($y = 0; $y < $height; $y += 3) {
                $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagefilledrectangle($image, $x, $y, $x + 2, $y + 2, $color);
            }
        }
        imagepng($image, $path, 0);
        imagedestroy($image);
    }

    private function writeTransparentPng(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        $opaque = imagecolorallocatealpha($image, 200, 50, 50, 0);
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), $opaque);
        imagepng($image, $path, 0);
        imagedestroy($image);
    }
}
