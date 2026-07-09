<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Router;
use kintai\Core\Services\WikiContentService;
use kintai\UI\Controller\Web\DocsController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

/**
 * Fixture calquée sur la vraie structure du wiki GitHub du projet :
 * pages anglaises à la racine, traductions sous fr/ et ja/, navigation
 * définie par _Sidebar.md, liens internes en chemins relatifs sans ".md".
 */
final class WikiContentServiceTest extends TestCase
{
    private string $tmpWiki;

    protected function setUp(): void
    {
        $this->tmpWiki = sys_get_temp_dir() . '/kintai_wiki_test_' . uniqid();
        mkdir($this->tmpWiki, 0755, true);
        mkdir($this->tmpWiki . '/fr', 0755, true);
        mkdir($this->tmpWiki . '/ja', 0755, true);

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

    private function writeWikiFile(string $relativePath, string $content): void
    {
        file_put_contents($this->tmpWiki . '/' . $relativePath, $content);
    }

    private function seedFixture(): void
    {
        $this->writeWikiFile('_Sidebar.md', <<<MD
        **[Home](Home)**

        🌐 [English](Home) · [Français](fr/Home) · [日本語](ja/Home)

        **Getting Started**
        - [Installation](Installation)
        MD);

        $this->writeWikiFile('Home.md', <<<MD
        # Kintai Wiki

        🌐 **English** · [Français](fr/Home) · [日本語](ja/Home)

        See [Installation](Installation), an [external link](https://example.com),
        an [anchor](#section), and a [broken link](Nonexistent-Page).
        MD);

        $this->writeWikiFile('Installation.md', "# Installation\n\nContent.");

        $this->writeWikiFile('fr/Home.md', <<<MD
        # Wiki Kintai

        🌐 [English](../Home) · **Français** · [日本語](../ja/Home)

        Voir [Installation](Installation), [English](../Home) ou [日本語](../ja/Home).
        MD);

        $this->writeWikiFile('fr/Installation.md', "# Installation FR\n\nContenu.");

        // Volontairement absent : ja/Installation.md (teste le fallback de titre + indisponibilité)
        $this->writeWikiFile('ja/Home.md', <<<MD
        # Kintaiウィキ

        🌐 [English](../Home) · [Français](../fr/Home) · **日本語**
        MD);
    }

    private function service(): WikiContentService
    {
        return new WikiContentService($this->tmpWiki);
    }

    // -------------------------------------------------------------------------
    // isConfigured() / supportedLangs()
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

    public function testSupportedLangsContainsFrEnJa(): void
    {
        $langs = WikiContentService::supportedLangs();
        $this->assertContains('fr', $langs);
        $this->assertContains('en', $langs);
        $this->assertContains('ja', $langs);
    }

    // -------------------------------------------------------------------------
    // firstPage()
    // -------------------------------------------------------------------------

    public function testFirstPageReturnsHomeFromTopOfSidebar(): void
    {
        $this->seedFixture();
        $this->assertSame('Home', $this->service()->firstPage());
    }

    public function testFirstPageReturnsNullWithoutSidebar(): void
    {
        $this->assertNull($this->service()->firstPage());
    }

    // -------------------------------------------------------------------------
    // tableOfContents()
    // -------------------------------------------------------------------------

    public function testTableOfContentsHasTopGroupAndNamedGroup(): void
    {
        $this->seedFixture();
        $toc = $this->service()->tableOfContents('en');

        $this->assertCount(2, $toc);
        $this->assertNull($toc[0]['label']);
        // Sans TranslationService lié au container, __() retombe sur la clé brute.
        $this->assertSame('docs_group_getting_started', $toc[1]['label']);
    }

    public function testTableOfContentsTopGroupContainsHome(): void
    {
        $this->seedFixture();
        $toc = $this->service()->tableOfContents('en');

        $this->assertSame('Home', $toc[0]['items'][0]['slug']);
        $this->assertSame('Kintai Wiki', $toc[0]['items'][0]['title']);
        $this->assertTrue($toc[0]['items'][0]['available']);
    }

    public function testTableOfContentsUsesFrenchTitleForFrenchLang(): void
    {
        $this->seedFixture();
        $toc = $this->service()->tableOfContents('fr');

        $this->assertSame('Wiki Kintai', $toc[0]['items'][0]['title']);
    }

    public function testTableOfContentsFallsBackToEnglishTitleWhenTranslationMissing(): void
    {
        $this->seedFixture();
        $toc = $this->service()->tableOfContents('ja');

        // ja/Installation.md n'existe pas : titre replié sur la version anglaise.
        $this->assertSame('Installation', $toc[1]['items'][0]['title']);
        $this->assertFalse($toc[1]['items'][0]['available']);
    }

    public function testTableOfContentsEmptyWithoutSidebar(): void
    {
        $this->assertSame([], $this->service()->tableOfContents('en'));
    }

    // -------------------------------------------------------------------------
    // pageExists()
    // -------------------------------------------------------------------------

    public function testPageExistsTrueForKnownPage(): void
    {
        $this->seedFixture();
        $this->assertTrue($this->service()->pageExists('en', 'Home'));
        $this->assertTrue($this->service()->pageExists('fr', 'Installation'));
    }

    public function testPageExistsFalseForMissingTranslation(): void
    {
        $this->seedFixture();
        $this->assertFalse($this->service()->pageExists('ja', 'Installation'));
    }

    public function testPageExistsFalseForUnknownLang(): void
    {
        $this->seedFixture();
        $this->assertFalse($this->service()->pageExists('de', 'Home'));
    }

    #[DataProvider('invalidSlugProvider')]
    public function testPageExistsFalseForInvalidOrTraversalSlug(string $slug): void
    {
        $this->seedFixture();
        $this->assertFalse($this->service()->pageExists('en', $slug));
    }

    public static function invalidSlugProvider(): array
    {
        return [
            'parent traversal'   => ['../../../etc/passwd'],
            'nested path'        => ['fr/Home'],
            'meta sidebar page'  => ['_Sidebar'],
            'empty slug'         => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function testRenderThrowsNotFoundForUnknownPage(): void
    {
        $this->seedFixture();
        $this->expectException(NotFoundException::class);
        $this->service()->render('en', 'Does-Not-Exist');
    }

    public function testRenderThrowsNotFoundForPathTraversalAttempt(): void
    {
        $this->seedFixture();
        $this->expectException(NotFoundException::class);
        $this->service()->render('en', '../../../../etc/passwd');
    }

    public function testRenderConvertsMarkdownToHtml(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Installation');

        $this->assertStringContainsString('<h1>Installation</h1>', $html);
        $this->assertStringContainsString('<p>Content.</p>', $html);
    }

    public function testRenderStripsLanguageSwitchLine(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Home');

        $this->assertStringNotContainsString('🌐', $html);
    }

    public function testRenderRewritesSameLanguageInternalLink(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Home');

        $this->assertStringContainsString('docs/en/Installation"', $html);
    }

    public function testRenderKeepsExternalLinkUnchanged(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Home');

        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function testRenderKeepsAnchorLinkUnchanged(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Home');

        $this->assertStringContainsString('href="#section"', $html);
    }

    public function testRenderDegradesBrokenInternalLinkToPlainText(): void
    {
        $this->seedFixture();
        $html = $this->service()->render('en', 'Home');

        $this->assertStringContainsString('broken link', $html);
        $this->assertStringNotContainsString('Nonexistent-Page', $html);
    }

    public function testRenderResolvesParentDirectoryLinkToEnglish(): void
    {
        $this->seedFixture();
        // Depuis fr/Home.md : [English](../Home) doit pointer vers /docs/en/Home
        $html = $this->service()->render('fr', 'Home');

        $this->assertStringContainsString('docs/en/Home"', $html);
    }

    public function testRenderResolvesCrossLanguageSiblingLink(): void
    {
        $this->seedFixture();
        // Depuis fr/Home.md : [日本語](../ja/Home) doit pointer vers /docs/ja/Home
        $html = $this->service()->render('fr', 'Home');

        $this->assertStringContainsString('docs/ja/Home"', $html);
    }

    public function testRenderSameLanguageLinkFromSubdirectory(): void
    {
        $this->seedFixture();
        // Depuis fr/Home.md : [Installation](Installation) doit pointer vers /docs/fr/Installation
        $html = $this->service()->render('fr', 'Home');

        $this->assertStringContainsString('docs/fr/Installation"', $html);
    }
}
