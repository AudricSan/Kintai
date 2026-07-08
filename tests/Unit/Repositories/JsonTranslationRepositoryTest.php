<?php
declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\JsonTranslationRepository;

final class JsonTranslationRepositoryTest extends TestCase
{
    private string $langPath;
    private JsonTranslationRepository $repo;

    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/kintai_translrepo_' . uniqid();
        mkdir($this->langPath, 0755, true);
        $this->repo = new JsonTranslationRepository($this->langPath);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->langPath . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->langPath);
    }

    public function testSaveCreatesFileAndKey(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');

        $this->assertFileExists($this->langPath . '/fr.json');
        $this->assertSame('Bonjour', $this->repo->findValue('fr', 'hello'));
    }

    public function testSaveUpsertsExistingKey(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'hello', 'Salut');

        $this->assertSame('Salut', $this->repo->findValue('fr', 'hello'));
        $this->assertCount(1, $this->repo->findByLocale('fr'));
    }

    public function testFindByLocaleReturnsAllKeysForThatLocaleOnly(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'bye', 'Au revoir');
        $this->repo->save('en', 'hello', 'Hello');

        $this->assertSame(['bye' => 'Au revoir', 'hello' => 'Bonjour'], $this->repo->findByLocale('fr'));
    }

    public function testFindValueReturnsNullWhenMissingLocaleOrKey(): void
    {
        $this->assertNull($this->repo->findValue('fr', 'unknown'));
        $this->assertNull($this->repo->findValue('unknown_locale', 'hello'));
    }

    public function testFindAllKeysUnionsAcrossLocalesButIgnoresManifest(): void
    {
        file_put_contents($this->langPath . '/languages.json', json_encode([['code' => 'fr']]));
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('en', 'hello', 'Hello');
        $this->repo->save('fr', 'bye', 'Au revoir');

        $keys = $this->repo->findAllKeys();
        sort($keys);

        $this->assertSame(['bye', 'hello'], $keys);
    }

    public function testDeleteRemovesKeyFromOneLocaleOnly(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('en', 'hello', 'Hello');

        $result = $this->repo->delete('fr', 'hello');

        $this->assertSame(1, $result);
        $this->assertNull($this->repo->findValue('fr', 'hello'));
        $this->assertSame('Hello', $this->repo->findValue('en', 'hello'));
    }

    public function testDeleteReturnsZeroWhenKeyMissing(): void
    {
        $this->assertSame(0, $this->repo->delete('fr', 'unknown'));
    }

    public function testCountByLocale(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'bye', 'Au revoir');

        $this->assertSame(2, $this->repo->countByLocale('fr'));
        $this->assertSame(0, $this->repo->countByLocale('ja'));
    }

    public function testFileIsValidJsonAfterSave(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour éàü€');

        $raw = file_get_contents($this->langPath . '/fr.json');
        $this->assertIsArray(json_decode($raw, true));
        $this->assertStringContainsString('éàü€', $raw);
    }
}
