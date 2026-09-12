<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\StorePhoto;

use kintai\Bundles\StorePhoto\Services\StorePhotoConsolidationService;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StorePhotoConsolidationServiceTest extends TestCase
{
    private StorePhotoRepositoryInterface&MockObject $photos;
    private string $photoDir;

    protected function setUp(): void
    {
        $this->photos   = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->photoDir = sys_get_temp_dir() . '/kintai-photo-consolidation-' . uniqid() . '/';
        mkdir($this->photoDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->photoDir);
    }

    public function testMergesSubmissionsFromSameStoreAndDayIntoTheEarliest(): void
    {
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 2, 'store_id' => 1, 'created_at' => '2026-07-10 15:00:00', 'image_count' => 1, 'notes' => 'Deuxième envoi'],
            ['id' => 1, 'store_id' => 1, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 2, 'notes' => 'Premier envoi'],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturnMap([
            [2, [['id' => 20, 'filepath' => 'storage/img/1/2/photo_1.jpg']]],
        ]);

        $this->photos->expects($this->once())->method('saveImage')->with($this->callback(
            fn (array $d) => $d['id'] === 20
                && $d['submission_id'] === 1
                && $d['sort_order'] === 2
                && $d['filepath'] === 'storage/img/1/1/photo_3.jpg'
        ));
        $this->photos->expects($this->once())->method('deleteSubmission')->with(2);
        $this->photos->expects($this->once())->method('saveSubmission')->with($this->callback(
            fn (array $d) => $d['id'] === 1
                && $d['image_count'] === 3
                && $d['notes'] === "Premier envoi\nDeuxième envoi"
        ));

        $report = $this->service()->consolidate();

        $this->assertSame(1, $report['merged_groups']);
        $this->assertSame(1, $report['merged_submissions']);
        $this->assertSame(1, $report['groups'][0]['target_id']);
        $this->assertSame([2], $report['groups'][0]['merged_ids']);
    }

    public function testLeavesSingleSubmissionPerStoreAndDayUntouched(): void
    {
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 1, 'store_id' => 1, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 2, 'notes' => null],
        ]);
        $this->photos->expects($this->never())->method('saveSubmission');
        $this->photos->expects($this->never())->method('deleteSubmission');

        $report = $this->service()->consolidate();

        $this->assertSame(0, $report['merged_groups']);
        $this->assertSame([], $report['groups']);
    }

    public function testKeepsSubmissionsFromDifferentStoresOrDaysSeparate(): void
    {
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 1, 'store_id' => 1, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 0, 'notes' => null],
            ['id' => 2, 'store_id' => 2, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 0, 'notes' => null],
            ['id' => 3, 'store_id' => 1, 'created_at' => '2026-07-11 09:00:00', 'image_count' => 0, 'notes' => null],
        ]);
        $this->photos->expects($this->never())->method('saveSubmission');
        $this->photos->expects($this->never())->method('deleteSubmission');

        $report = $this->service()->consolidate();

        $this->assertSame(0, $report['merged_groups']);
    }

    /** --dry-run doit rapporter la fusion prévue sans toucher ni à la base ni au disque. */
    public function testDryRunReportsWithoutWritingAnything(): void
    {
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 2, 'store_id' => 1, 'created_at' => '2026-07-10 15:00:00', 'image_count' => 1, 'notes' => 'B'],
            ['id' => 1, 'store_id' => 1, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 2, 'notes' => 'A'],
        ]);
        $this->photos->expects($this->never())->method('findImagesBySubmission');
        $this->photos->expects($this->never())->method('saveImage');
        $this->photos->expects($this->never())->method('saveSubmission');
        $this->photos->expects($this->never())->method('deleteSubmission');

        $report = $this->service()->consolidate(true);

        $this->assertSame(1, $report['merged_groups']);
        $this->assertSame(1, $report['merged_submissions']);
        $this->assertSame([2], $report['groups'][0]['merged_ids']);
    }

    public function testMovesImageFilesOnDiskToTheTargetSubmissionsDirectory(): void
    {
        mkdir($this->photoDir . '1/2', 0775, true);
        file_put_contents($this->photoDir . '1/2/photo_1.jpg', 'fake-image-bytes');

        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 2, 'store_id' => 1, 'created_at' => '2026-07-10 15:00:00', 'image_count' => 1, 'notes' => null],
            ['id' => 1, 'store_id' => 1, 'created_at' => '2026-07-10 09:00:00', 'image_count' => 0, 'notes' => null],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturnMap([
            [2, [['id' => 20, 'filepath' => 'storage/img/1/2/photo_1.jpg']]],
        ]);

        $this->service()->consolidate();

        $this->assertFileDoesNotExist($this->photoDir . '1/2/photo_1.jpg');
        $this->assertDirectoryDoesNotExist($this->photoDir . '1/2');
        $this->assertFileExists($this->photoDir . '1/1/photo_1.jpg');
        $this->assertSame('fake-image-bytes', file_get_contents($this->photoDir . '1/1/photo_1.jpg'));
    }

    private function service(): StorePhotoConsolidationService
    {
        return new StorePhotoConsolidationService($this->photos, $this->photoDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '*') as $f) {
            is_dir($f) ? $this->removeDir($f . '/') : unlink($f);
        }
        @rmdir($dir);
    }
}
