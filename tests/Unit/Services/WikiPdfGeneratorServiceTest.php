<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Services\WikiPdfGeneratorService;

// BASE_PATH est requis par le constructeur du service (fallback seulement)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

final class WikiPdfGeneratorServiceTest extends TestCase
{
    private string $tmpWiki;
    private string $tmpOut;
    private string $tmpMpdf;

    protected function setUp(): void
    {
        $base          = sys_get_temp_dir() . '/kintai_pdf_test_' . uniqid();
        $this->tmpWiki = $base . '/wiki';
        $this->tmpOut  = $base . '/out';
        $this->tmpMpdf = $base . '/mpdf';

        mkdir($this->tmpWiki, 0755, true);
        mkdir($this->tmpOut,  0755, true);
        mkdir($this->tmpMpdf, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir(sys_get_temp_dir() . '/kintai_pdf_test_' . substr($this->tmpWiki, strrpos($this->tmpWiki, '_') + 1));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function service(): WikiPdfGeneratorService
    {
        return new WikiPdfGeneratorService($this->tmpWiki, $this->tmpMpdf);
    }

    // -------------------------------------------------------------------------
    // supportedLangs()
    // -------------------------------------------------------------------------

    public function testSupportedLangsReturnsThreeEntries(): void
    {
        $this->assertCount(3, WikiPdfGeneratorService::supportedLangs());
    }

    public function testSupportedLangsContainsFrEnJa(): void
    {
        $langs = WikiPdfGeneratorService::supportedLangs();

        $this->assertContains('fr', $langs);
        $this->assertContains('en', $langs);
        $this->assertContains('ja', $langs);
    }

    public function testSupportedLangsAreStrings(): void
    {
        foreach (WikiPdfGeneratorService::supportedLangs() as $lang) {
            $this->assertIsString($lang);
        }
    }

    // -------------------------------------------------------------------------
    // generate() — langue inconnue
    // -------------------------------------------------------------------------

    public function testGenerateUnknownLangReturnsError(): void
    {
        $result = $this->service()->generate('xx', $this->tmpOut);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('xx', $result['error']);
    }

    public function testGenerateUnknownLangHasZeroSizeKb(): void
    {
        $result = $this->service()->generate('zz', $this->tmpOut);

        $this->assertSame(0, $result['size_kb']);
    }

    // -------------------------------------------------------------------------
    // generate() — fichiers wiki manquants
    // -------------------------------------------------------------------------

    public function testGenerateWithMissingWikiFilesReturnsError(): void
    {
        // tmpWiki est vide : aucun fichier markdown
        $result = $this->service()->generate('fr', $this->tmpOut);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('manquants', $result['error']);
    }

    public function testGenerateWithMissingFilesListsAllMissingFiles(): void
    {
        $result = $this->service()->generate('fr', $this->tmpOut);

        // Au moins le premier fichier attendu doit apparaître dans l'erreur
        $this->assertStringContainsString('Quick-Start.md', $result['error']);
    }

    public function testGenerateWithMissingFilesReturnsCorrectFilename(): void
    {
        $result = $this->service()->generate('fr', $this->tmpOut);

        $this->assertSame('Kintai-Guide-FR.pdf', $result['filename']);
    }

    public function testGenerateEnWithMissingFilesReturnsCorrectFilename(): void
    {
        $result = $this->service()->generate('en', $this->tmpOut);

        $this->assertSame('Kintai-Guide-EN.pdf', $result['filename']);
    }

    public function testGenerateJaWithMissingFilesReturnsCorrectFilename(): void
    {
        $result = $this->service()->generate('ja', $this->tmpOut);

        $this->assertSame('Kintai-Guide-JA.pdf', $result['filename']);
    }

    // -------------------------------------------------------------------------
    // generate() — répertoire de sortie non créable
    // -------------------------------------------------------------------------

    public function testGenerateWithUnwritableOutputDirReturnsError(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Permissions Unix non disponibles sur Windows.');
        }

        $lockedDir = $this->tmpOut . '/locked';
        mkdir($lockedDir, 0555, true); // lecture seule

        $result = $this->service()->generate('fr', $lockedDir . '/sub');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Impossible de créer', $result['error']);
    }

    // -------------------------------------------------------------------------
    // generateAll() — structure du résultat
    // -------------------------------------------------------------------------

    public function testGenerateAllReturnsArrayWithAllLangKeys(): void
    {
        $results = $this->service()->generateAll($this->tmpOut);

        foreach (WikiPdfGeneratorService::supportedLangs() as $lang) {
            $this->assertArrayHasKey($lang, $results);
        }
    }

    public function testGenerateAllResultsHaveRequiredKeys(): void
    {
        $results = $this->service()->generateAll($this->tmpOut);

        foreach ($results as $result) {
            $this->assertArrayHasKey('ok',       $result);
            $this->assertArrayHasKey('filename', $result);
            $this->assertArrayHasKey('size_kb',  $result);
            $this->assertArrayHasKey('error',    $result);
        }
    }

    public function testGenerateAllWithNoWikiFilesAllFail(): void
    {
        $results = $this->service()->generateAll($this->tmpOut);

        foreach ($results as $result) {
            $this->assertFalse($result['ok']);
        }
    }
}
