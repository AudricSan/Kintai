<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\WikiIntegrityChecker;
use PHPUnit\Framework\TestCase;

/** Contrôle d'un dossier de wiki avant publication : pages en trois langues, ligne de langue, liens internes. */
final class WikiIntegrityCheckerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kintai_wiki_check_' . uniqid();
        foreach (['', '/fr', '/ja', '/images'] as $sub) {
            mkdir($this->dir . $sub, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function page(string $path, string $body = "Contenu\nsur plusieurs\nlignes\nnon vides\npour ne pas\nêtre un relais\nd'ancien lien.", bool $lang = true): void
    {
        file_put_contents("{$this->dir}/$path.md", "# T\n\n" . ($lang ? "🌐 **English**\n\n" : '') . $body . "\n");
    }

    private function trio(string $slug, string $body = "Contenu\nsur plusieurs\nlignes\nnon vides\npour ne pas\nêtre un relais\nd'ancien lien."): void
    {
        foreach (['', 'fr/', 'ja/'] as $dir) {
            $this->page($dir . $slug, $body);
        }
    }

    private function sidebar(string ...$slugs): void
    {
        $lines = ["**[Home](Home)**", "", "**Group**"];
        foreach ($slugs as $s) {
            $lines[] = "- [$s]($s)";
        }
        file_put_contents($this->dir . '/_Sidebar.md', implode("\n", $lines) . "\n");
    }

    public function testACompleteWikiHasNoError(): void
    {
        $this->trio('Home');
        $this->trio('Guide', "Voir [Home](Home) et [Accueil](../Home) et [Ancre](Home#section) et <https://x.test>.\n1\n2\n3\n4\n5\n6");
        $this->sidebar('Guide');

        $result = (new WikiIntegrityChecker())->check($this->dir);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    public function testAListedPageMissingInALanguageIsAnError(): void
    {
        $this->trio('Home');
        $this->page('Guide');
        $this->page('fr/Guide');
        $this->sidebar('Guide');

        $result = (new WikiIntegrityChecker())->check($this->dir);

        $this->assertContains('page manquante (ja) : Guide', $result['errors']);
        $this->assertNotContains('page manquante (fr) : Guide', $result['errors']);
    }

    public function testBrokenLinksAreReportedWithTheirPage(): void
    {
        $this->trio('Home');
        $this->trio('Guide', "[Cassé](Nulle-part) et [image](images/absente.png) et [ok](Home).\n1\n2\n3\n4\n5\n6");
        $this->sidebar('Guide');
        file_put_contents($this->dir . '/images/presente.png', 'x');
        $this->page('fr/Guide', "[Relatif](../Home) [Cassé fr](../Nulle-part).\n1\n2\n3\n4\n5\n6");

        $errors = (new WikiIntegrityChecker())->check($this->dir)['errors'];

        $this->assertContains('lien cassé dans Guide : Nulle-part', $errors);
        $this->assertContains('lien cassé dans Guide : images/absente.png', $errors);
        $this->assertContains('lien cassé dans fr/Guide : ../Nulle-part', $errors);
        $this->assertNotContains('lien cassé dans fr/Guide : ../Home', $errors);
    }

    public function testLinkLikeTextInCodeIsIgnored(): void
    {
        $this->trio('Home');
        $this->trio('Guide', "Exemple `[x](Inexistant)` et\n```\n[y](Aussi-inexistant)\n```\n1\n2\n3\n4\n5");
        $this->sidebar('Guide');

        $this->assertSame([], (new WikiIntegrityChecker())->check($this->dir)['errors']);
    }

    public function testAPageWithoutTheLanguageLineIsAnError(): void
    {
        $this->trio('Home');
        $this->trio('Guide');
        $this->page('fr/Guide', "Sans ligne de langue\n1\n2\n3\n4\n5\n6", false);
        $this->sidebar('Guide');

        $this->assertContains('ligne de choix de langue absente : fr/Guide', (new WikiIntegrityChecker())->check($this->dir)['errors']);
    }

    public function testAnUnlistedRealPageIsAWarningButARelayStubIsNot(): void
    {
        $this->trio('Home');
        $this->trio('Guide');
        $this->trio('Orpheline');
        $this->trio('Ancien', 'Cette page a été scindée : voir [Guide](Guide).');
        $this->sidebar('Guide');

        $result = (new WikiIntegrityChecker())->check($this->dir);

        $this->assertContains('page absente de la barre latérale (en) : Orpheline', $result['warnings']);
        $this->assertNotContains('page absente de la barre latérale (en) : Ancien', $result['warnings']);
        $this->assertSame([], $result['errors']);
    }

    public function testAnUnlistedPageMissingInALanguageIsAnError(): void
    {
        $this->trio('Home');
        $this->trio('Guide');
        $this->page('Seule-en-anglais');
        $this->sidebar('Guide');

        $errors = (new WikiIntegrityChecker())->check($this->dir)['errors'];

        $this->assertContains('traduction manquante (fr) : Seule-en-anglais', $errors);
        $this->assertContains('traduction manquante (ja) : Seule-en-anglais', $errors);
    }

    public function testAMissingSidebarIsReported(): void
    {
        $this->assertSame(['_Sidebar.md introuvable'], (new WikiIntegrityChecker())->check($this->dir)['errors']);
    }
}
