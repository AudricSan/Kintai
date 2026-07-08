<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use kintai\UI\Controller\Web\WikiPdfController;

final class WikiPdfControllerTokenTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        $this->lockFile = tempnam(sys_get_temp_dir(), 'kintai_lock_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    // -------------------------------------------------------------------------
    // computeToken() — fichier absent
    // -------------------------------------------------------------------------

    public function testComputeTokenReturnsEmptyWhenFileAbsent(): void
    {
        unlink($this->lockFile); // supprimer le fichier temporaire

        $this->assertSame('', WikiPdfController::computeToken($this->lockFile));
    }

    // -------------------------------------------------------------------------
    // computeToken() — fichier vide
    // -------------------------------------------------------------------------

    public function testComputeTokenReturnsEmptyWhenFileIsEmpty(): void
    {
        file_put_contents($this->lockFile, '');

        $this->assertSame('', WikiPdfController::computeToken($this->lockFile));
    }

    public function testComputeTokenReturnsEmptyWhenFileIsWhitespaceOnly(): void
    {
        file_put_contents($this->lockFile, "   \n  \t  ");

        $this->assertSame('', WikiPdfController::computeToken($this->lockFile));
    }

    // -------------------------------------------------------------------------
    // computeToken() — hash SHA-256
    // -------------------------------------------------------------------------

    public function testComputeTokenReturnsSha256Hash(): void
    {
        $content = 'abc123';
        file_put_contents($this->lockFile, $content);

        $expected = hash('sha256', 'kintai-pdf:' . $content);
        $this->assertSame($expected, WikiPdfController::computeToken($this->lockFile));
    }

    public function testComputeTokenHashIs64HexChars(): void
    {
        file_put_contents($this->lockFile, 'some-content');

        $token = WikiPdfController::computeToken($this->lockFile);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testComputeTokenStripsLeadingAndTrailingWhitespace(): void
    {
        $content = '  mytoken  ';
        file_put_contents($this->lockFile, $content);

        $expected = hash('sha256', 'kintai-pdf:' . trim($content));
        $this->assertSame($expected, WikiPdfController::computeToken($this->lockFile));
    }

    public function testComputeTokenIsDeterministic(): void
    {
        file_put_contents($this->lockFile, 'stable-content');

        $token1 = WikiPdfController::computeToken($this->lockFile);
        $token2 = WikiPdfController::computeToken($this->lockFile);
        $this->assertSame($token1, $token2);
    }

    public function testComputeTokenDiffersForDifferentContent(): void
    {
        $fileA = tempnam(sys_get_temp_dir(), 'kintai_a_');
        $fileB = tempnam(sys_get_temp_dir(), 'kintai_b_');

        file_put_contents($fileA, 'content-A');
        file_put_contents($fileB, 'content-B');

        $this->assertNotSame(
            WikiPdfController::computeToken($fileA),
            WikiPdfController::computeToken($fileB)
        );

        unlink($fileA);
        unlink($fileB);
    }

    public function testComputeTokenScopePrefix(): void
    {
        // Le token ne doit pas être le sha256 brut du contenu — il inclut le préfixe 'kintai-pdf:'
        $content = 'raw-content';
        file_put_contents($this->lockFile, $content);

        $rawHash   = hash('sha256', $content);
        $scopedHash = WikiPdfController::computeToken($this->lockFile);

        $this->assertNotSame($rawHash, $scopedHash);
    }

    public function testComputeTokenWithBinaryishContent(): void
    {
        $content = bin2hex(random_bytes(32)); // simule un vrai installed.lock
        file_put_contents($this->lockFile, $content);

        $token = WikiPdfController::computeToken($this->lockFile);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }
}
