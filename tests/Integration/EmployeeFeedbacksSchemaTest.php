<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\MigrationRunner;
use kintai\Domain\Eloquent\Feedback;
use PHPUnit\Framework\TestCase;

/**
 * FeedbackController::submit() écrit category/message/anonymous depuis le tout premier commit,
 * mais la migration de création de la table ne les avait jamais définis (colonnes 'rating'/'feedback_date'
 * NOT NULL héritées d'un ancien modèle) : toute soumission de feedback échouait avec
 * "no such column: category". user_id était aussi NOT NULL alors que le contrôleur le met à null
 * pour un feedback anonyme. Ce test applique les migrations sur une vraie base et fait les insertions
 * telles que le contrôleur les produit, pour éviter la régression (les tests unitaires du contrôleur
 * mockent le repository et ne l'auraient pas détectée).
 */
final class EmployeeFeedbacksSchemaTest extends TestCase
{
    private function migratedCapsule(): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(MigrationRunner::class))->newInstanceWithoutConstructor();
        $capsuleProp = new \ReflectionProperty($runner, 'capsule');
        $capsuleProp->setAccessible(true);
        $capsuleProp->setValue($runner, $capsule);
        $pathProp = new \ReflectionProperty($runner, 'migrationsPath');
        $pathProp->setAccessible(true);
        $pathProp->setValue($runner, dirname(__DIR__, 2) . '/database/migrations/php');

        $runner->run();

        return $capsule;
    }

    public function testMigratedSchemaAcceptsControllerPayload(): void
    {
        $this->migratedCapsule();

        $record = Feedback::create([
            'store_id'   => 1,
            'user_id'    => 42,
            'shift_id'   => null,
            'category'   => 'app',
            'rating'     => null,
            'message'    => 'Le bouton export ne répond pas sur mobile.',
            'anonymous'  => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNotNull($record->id);
        $this->assertSame('app', Feedback::find($record->id)->category);
    }

    public function testMigratedSchemaAcceptsAnonymousFeedbackWithNullUserId(): void
    {
        $this->migratedCapsule();

        $record = Feedback::create([
            'store_id'   => 1,
            'user_id'    => null,
            'shift_id'   => null,
            'category'   => 'other',
            'rating'     => null,
            'message'    => 'Feedback anonyme.',
            'anonymous'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNotNull($record->id);
        $this->assertNull(Feedback::find($record->id)->user_id);
    }

    /**
     * store_id est nullable depuis la migration 2026_07_15_000004 : l'Owner (et tout
     * rôle global sans adhésion à un store) doit pouvoir signaler un bug sans être
     * rattaché à un magasin. Les colonnes page_path/app_version/device_type sont le
     * contexte technique auto-détecté (migration 2026_07_15_000003).
     */
    public function testMigratedSchemaAcceptsOwnerFeedbackWithoutStoreAndTechnicalContext(): void
    {
        $this->migratedCapsule();

        $record = Feedback::create([
            'store_id'    => null,
            'user_id'     => 99,
            'shift_id'    => null,
            'category'    => 'app',
            'rating'      => null,
            'message'     => 'Bug sur la page owner-settings.',
            'anonymous'   => 0,
            'page_path'   => '/admin/owner-settings',
            'app_version' => '0.7.2',
            'device_type' => 'desktop',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $fresh = Feedback::find($record->id);
        $this->assertNull($fresh->store_id);
        $this->assertSame('/admin/owner-settings', $fresh->page_path);
        $this->assertSame('0.7.2', $fresh->app_version);
        $this->assertSame('desktop', $fresh->device_type);
    }
}
