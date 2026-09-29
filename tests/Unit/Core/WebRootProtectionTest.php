<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Seul public/ doit être servi. Si le serveur web pointe par erreur sur la racine du dépôt (cas
 * réel : un htdocs/Kintai atteint via http://localhost/Kintai/), .env, la base SQLite, les
 * sauvegardes et le code source seraient téléchargeables — vérifié sur une installation de
 * développement (HTTP 200 sur /.env et /storage/app/database.sqlite). Ces tests figent les
 * garde-fous : un .htaccess à la racine et un autre dans storage/.
 */
final class WebRootProtectionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    /** Extrait le motif de la règle RewriteRule « refuser hors public/ » du .htaccess racine. */
    private function denyPattern(): string
    {
        $file = $this->root . '/.htaccess';
        $this->assertFileExists($file, 'Le .htaccess de la racine (refus hors public/) a disparu.');

        $found = preg_match('/^\s*RewriteRule\s+(\S+)\s+-\s+\[[^\]]*\bF\b[^\]]*\]/m', (string) file_get_contents($file), $m);
        $this->assertSame(1, $found, 'Aucune RewriteRule de refus [F] dans le .htaccess racine.');

        return $m[1];
    }

    /** @return array<string, array{string}> */
    public static function sensitivePaths(): array
    {
        return [
            'env'                => ['.env'],
            'base SQLite'        => ['storage/app/database.sqlite'],
            'sauvegardes'        => ['storage/backups/backup_2026.zip'],
            'journaux'           => ['storage/logs/error.log'],
            'code source'        => ['src/Core/Application.php'],
            'composer'           => ['composer.json'],
            'configuration'      => ['config/database.local.php'],
            'dépôt git'          => ['.git/config'],
            'migrations'         => ['database/migrations/php/x.php'],
            'tests'              => ['tests/Unit/Core/RoutesTest.php'],
            'racine du dépôt'    => [''],
            'faux ami "public"'  => ['publicity/secret.txt'],
            'public déguisé'     => ['storage/public/x'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sensitivePaths')]
    public function testSensitivePathsAreRefusedByTheRootHtaccess(string $path): void
    {
        $this->assertSame(1, preg_match('~' . $this->denyPattern() . '~', $path), "« $path » devrait être refusé.");
    }

    /** @return array<string, array{string}> */
    public static function publicPaths(): array
    {
        return [
            'front controller' => ['public/index.php'],
            'css'              => ['public/assets/css/app.css'],
            'js'               => ['public/assets/js/app.js'],
            'installeur'       => ['public/install.php'],
            'pages d\'erreur'  => ['public/errors/404.html'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicPaths')]
    public function testPublicPathsAreNotRefusedByTheRootHtaccess(string $path): void
    {
        $this->assertSame(0, preg_match('~' . $this->denyPattern() . '~', $path), "« $path » ne doit pas être refusé.");
    }

    public function testDirectoryListingIsDisabledAtTheRoot(): void
    {
        $this->assertMatchesRegularExpression('/^\s*Options\s+-Indexes/m', (string) file_get_contents($this->root . '/.htaccess'));
    }

    public function testStorageDeniesAllDirectAccess(): void
    {
        $file = $this->root . '/storage/.htaccess';
        $this->assertFileExists($file, 'storage/.htaccess a disparu (refus de tout accès direct).');
        $this->assertStringContainsString('Require all denied', (string) file_get_contents($file));
        $this->assertStringContainsString('Deny from all', (string) file_get_contents($file), 'Repli Apache 2.2 manquant.');
    }

    public function testStorageHtaccessIsNotIgnoredByGit(): void
    {
        // storage/* est ignoré : sans cette exception, le fichier n'atteindrait jamais un dépôt cloné.
        $this->assertMatchesRegularExpression('#^!/storage/\.htaccess\s*$#m', (string) file_get_contents($this->root . '/.gitignore'));
    }

    public function testPublicHtaccessStillRoutesToTheFrontController(): void
    {
        // Le .htaccess racine laisse passer public/ ; c'est public/.htaccess qui route vers index.php.
        $this->assertStringContainsString('index.php', (string) file_get_contents($this->root . '/public/.htaccess'));
    }
}
