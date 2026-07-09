<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Router;
use kintai\Core\Services\WikiContentService;
use kintai\UI\Controller\Web\DocsController;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

final class WikiContentServiceTest extends TestCase
{
    private string $tmpWiki;

    protected function setUp(): void
    {
        $this->tmpWiki = sys_get_temp_dir() . '/kintai_wiki_test_' . uniqid();
        mkdir($this->tmpWiki, 0755, true);

        // route_url('docs.show', ...) est utilisé pour réécrire les liens internes :
        // on enregistre un Router minimal portant cette seule route.
        $router = new Router();
        $router->get('/docs/{lang}/{page}', [DocsController::class, 'show'], name: 'docs.show');
        Container::getInstance()->instance(Router::class, $router);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpWiki);
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

    private function writeWikiFile(string $name, string $content): void
    {
        file_put_contents($this->tmpWiki . '/' . $name, $content);
    }

    private function service(): WikiContentService
    {
        return new WikiContentService($this->tmpWiki);
    }

    // -------------------------------------------------------------------------
    // isConfigured()
    // -------------------------------------------------------------------------

    public function testIsConfiguredTrueWhenDirExists(): void
    {
        $this->assertTrue($this->service()->isConfigured());
    }

    public function testIsConfiguredFalseWhenDirMissing(): void
    {
        $service = new WikiContentService($this->tmpWiki . '/does-not-exist');
        $this->assertFalse($service->isConfigured());
    }

    // -------------------------------------------------------------------------
    // tableOfContents()
    // -------------------------------------------------------------------------

    public function testTableOfContentsReturnsSevenEntriesForFr(): void
    {
        $this->assertCount(7, $this->service()->tableOfContents('fr'));
    }

    public function testTableOfContentsFirstEntryIsQuickStart(): void
    {
        $toc = $this->service()->tableOfContents('fr');

        $this->assertSame('Quick-Start.md', $toc[0]['file']);
        $this->assertSame('Démarrage rapide', $toc[0]['title']);
    }

    public function testTableOfContentsUnknownLangReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->service()->tableOfContents('xx'));
    }

    // -------------------------------------------------------------------------
    // firstPage()
    // -------------------------------------------------------------------------

    public function testFirstPageReturnsQuickStartForFr(): void
    {
        $this->assertSame('Quick-Start.md', $this->service()->firstPage('fr'));
    }

    public function testFirstPageReturnsNullForUnknownLang(): void
    {
        $this->assertNull($this->service()->firstPage('xx'));
    }

    // -------------------------------------------------------------------------
    // pageExists()
    // -------------------------------------------------------------------------

    public function testPageExistsFalseWhenFileNotOnDisk(): void
    {
        $this->assertFalse($this->service()->pageExists('fr', 'Quick-Start.md'));
    }

    public function testPageExistsTrueWhenKnownFileIsPresent(): void
    {
        $this->writeWikiFile('Quick-Start.md', '# Démarrage');

        $this->assertTrue($this->service()->pageExists('fr', 'Quick-Start.md'));
    }

    public function testPageExistsFalseForFileNotInCatalog(): void
    {
        $this->writeWikiFile('Random-Page.md', '# Random');

        $this->assertFalse($this->service()->pageExists('fr', 'Random-Page.md'));
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function testRenderThrowsNotFoundForUnknownLang(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service()->render('xx', 'Quick-Start.md');
    }

    public function testRenderThrowsNotFoundWhenFileMissingOnDisk(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service()->render('fr', 'Quick-Start.md');
    }

    public function testRenderConvertsMarkdownToHtml(): void
    {
        $this->writeWikiFile('Quick-Start.md', "# Bienvenue\n\nCeci est un test.");

        $html = $this->service()->render('fr', 'Quick-Start.md');

        $this->assertStringContainsString('<h1>Bienvenue</h1>', $html);
        $this->assertStringContainsString('<p>Ceci est un test.</p>', $html);
    }

    public function testRenderKeepsExternalLinksUnchanged(): void
    {
        $this->writeWikiFile('Quick-Start.md', '[Site officiel](https://kintai.example.com)');

        $html = $this->service()->render('fr', 'Quick-Start.md');

        $this->assertStringContainsString('href="https://kintai.example.com"', $html);
    }

    public function testRenderRewritesInternalLinkToAppRoute(): void
    {
        $this->writeWikiFile('Quick-Start.md', '[Guide propriétaire](Guide-Owner-FR.md)');

        $html = $this->service()->render('fr', 'Quick-Start.md');

        // base_url() dépend de SCRIPT_NAME (variable en CLI) : on ne vérifie que le suffixe stable.
        $this->assertStringContainsString('docs/fr/Guide-Owner-FR.md"', $html);
        $this->assertStringContainsString('>Guide propriétaire<', $html);
    }

    public function testRenderDegradesUnrecognizedInternalLinkToPlainText(): void
    {
        $this->writeWikiFile('Quick-Start.md', 'Voir [Page inconnue](Page-Inconnue.md) pour plus de détails.');

        $html = $this->service()->render('fr', 'Quick-Start.md');

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('Page inconnue', $html);
    }
}
