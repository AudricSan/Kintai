<?php
declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DatabaseTranslationRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Domain\Eloquent\Translation as EloquentTranslation;

final class DatabaseTranslationRepositoryTest extends TestCase
{
    private DatabaseTranslationRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('translations', function ($table) {
            $table->increments('id');
            $table->string('language_code', 10);
            $table->string('key', 190);
            $table->text('value');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        $this->repo = new DatabaseTranslationRepository();
    }

    public function testSaveInsertsNewOverride(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->assertSame('Bonjour', $this->repo->findValue('fr', 'hello'));
    }

    public function testSaveUpsertsExistingOverride(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'hello', 'Salut');
        $this->assertSame('Salut', $this->repo->findValue('fr', 'hello'));
        $this->assertCount(1, EloquentTranslation::all());
    }

    public function testFindByLocaleReturnsKeyValueMap(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'bye', 'Au revoir');
        $this->repo->save('en', 'hello', 'Hello');

        $this->assertSame(['hello' => 'Bonjour', 'bye' => 'Au revoir'], $this->repo->findByLocale('fr'));
    }

    public function testFindValueReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findValue('fr', 'unknown'));
    }

    public function testFindAllKeysReturnsDistinctKeysAcrossLocales(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('en', 'hello', 'Hello');
        $this->repo->save('fr', 'bye', 'Au revoir');

        $keys = $this->repo->findAllKeys();
        sort($keys);
        $this->assertSame(['bye', 'hello'], $keys);
    }

    public function testDeleteRemovesOneOverride(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $result = $this->repo->delete('fr', 'hello');
        $this->assertSame(1, $result);
        $this->assertNull($this->repo->findValue('fr', 'hello'));
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete('fr', 'unknown'));
    }

    public function testDeleteKeyRemovesAcrossAllLocales(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('en', 'hello', 'Hello');
        $this->repo->save('fr', 'bye', 'Au revoir');

        $result = $this->repo->deleteKey('hello');

        $this->assertSame(2, $result);
        $this->assertNull($this->repo->findValue('fr', 'hello'));
        $this->assertNull($this->repo->findValue('en', 'hello'));
        $this->assertNotNull($this->repo->findValue('fr', 'bye'));
    }

    public function testCountByLocale(): void
    {
        $this->repo->save('fr', 'hello', 'Bonjour');
        $this->repo->save('fr', 'bye', 'Au revoir');
        $this->repo->save('en', 'hello', 'Hello');

        $this->assertSame(2, $this->repo->countByLocale('fr'));
        $this->assertSame(0, $this->repo->countByLocale('ja'));
    }
}
