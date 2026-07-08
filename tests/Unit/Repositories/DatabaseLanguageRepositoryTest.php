<?php
declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DatabaseLanguageRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Domain\Eloquent\Language as EloquentLanguage;

final class DatabaseLanguageRepositoryTest extends TestCase
{
    private DatabaseLanguageRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('languages', function ($table) {
            $table->string('code', 10)->primary();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('translations', function ($table) {
            $table->increments('id');
            $table->string('language_code', 10);
            $table->string('key', 190);
            $table->text('value');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        $this->repo = new DatabaseLanguageRepository();
    }

    public function testSaveInsertsNewLanguage(): void
    {
        $saved = $this->repo->save(['code' => 'es', 'name' => 'Español', 'is_active' => true]);
        $this->assertSame('es', $saved['code']);
        $this->assertCount(1, EloquentLanguage::all());
    }

    public function testSaveUpdatesExistingLanguage(): void
    {
        $this->repo->save(['code' => 'es', 'name' => 'Español']);
        $this->repo->save(['code' => 'es', 'name' => 'Castellano']);
        $this->assertSame('Castellano', EloquentLanguage::find('es')->name);
        $this->assertCount(1, EloquentLanguage::all());
    }

    public function testFindAllReturnsAllLanguages(): void
    {
        $this->repo->save(['code' => 'fr', 'name' => 'Français', 'sort_order' => 0]);
        $this->repo->save(['code' => 'en', 'name' => 'English', 'sort_order' => 1]);
        $this->assertCount(2, $this->repo->findAll());
    }

    public function testFindAllActiveExcludesInactive(): void
    {
        $this->repo->save(['code' => 'fr', 'name' => 'Français', 'is_active' => true]);
        $this->repo->save(['code' => 'zz', 'name' => 'Inactive', 'is_active' => false]);
        $active = $this->repo->findAllActive();
        $this->assertCount(1, $active);
        $this->assertSame('fr', $active[0]['code']);
    }

    public function testFindByCodeReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findByCode('xx'));
    }

    public function testFindDefaultReturnsDefaultLanguage(): void
    {
        $this->repo->save(['code' => 'fr', 'name' => 'Français', 'is_default' => true]);
        $this->repo->save(['code' => 'en', 'name' => 'English', 'is_default' => false]);
        $default = $this->repo->findDefault();
        $this->assertSame('fr', $default['code']);
    }

    public function testSetDefaultSwitchesDefaultLanguage(): void
    {
        $this->repo->save(['code' => 'fr', 'name' => 'Français', 'is_default' => true]);
        $this->repo->save(['code' => 'en', 'name' => 'English', 'is_default' => false]);

        $this->assertTrue($this->repo->setDefault('en'));

        $this->assertFalse((bool) EloquentLanguage::find('fr')->is_default);
        $this->assertTrue((bool) EloquentLanguage::find('en')->is_default);
    }

    public function testSetDefaultReturnsFalseWhenLanguageMissing(): void
    {
        $this->assertFalse($this->repo->setDefault('xx'));
    }

    public function testToggleActiveUpdatesFlag(): void
    {
        $this->repo->save(['code' => 'fr', 'name' => 'Français', 'is_active' => true]);
        $this->repo->toggleActive('fr', false);
        $this->assertFalse((bool) EloquentLanguage::find('fr')->is_active);
    }

    public function testDeleteRemovesLanguageAndCascadesTranslations(): void
    {
        $this->repo->save(['code' => 'es', 'name' => 'Español']);
        \kintai\Domain\Eloquent\Translation::create(['language_code' => 'es', 'key' => 'hello', 'value' => 'hola']);

        $result = $this->repo->delete('es');

        $this->assertSame(1, $result);
        $this->assertNull(EloquentLanguage::find('es'));
        $this->assertSame(0, \kintai\Domain\Eloquent\Translation::where('language_code', 'es')->count());
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete('xx'));
    }
}
