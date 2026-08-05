<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Repositories;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Repositories\DatabaseNotificationRepository;
use kintai\Domain\Eloquent\Notification;
use PHPUnit\Framework\TestCase;

/**
 * Insère contre le vrai schéma de la table `notifications` (id, user_id,
 * type, data, read_at, created_at) — pas de mock. C'est précisément le test
 * qui manquait : NotificationService::notify() écrivait body/reference_id/
 * is_read, des colonnes qui n'existent pas, et l'erreur SQL résultante
 * n'était jamais détectée car tous les tests de contrôleurs mockent
 * NotificationService/NotificationRepositoryInterface en entier.
 */
final class DatabaseNotificationRepositoryTest extends TestCase
{
    private DatabaseNotificationRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('notifications', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->repo = new DatabaseNotificationRepository();
    }

    public function testSaveInsertsAgainstRealSchemaWithBodyAndReferenceId(): void
    {
        $saved = $this->repo->save([
            'user_id'      => 5,
            'type'         => 'timeoff_approved',
            'reference_id' => 42,
            'body'         => 'Votre demande de congé a été approuvée.',
            'is_read'      => 0,
            'created_at'   => '2026-08-05 10:00:00',
        ]);

        $this->assertSame(5, (int) $saved['user_id']);
        $this->assertSame('timeoff_approved', $saved['type']);
        $this->assertSame(42, (int) $saved['reference_id']);
        $this->assertSame('Votre demande de congé a été approuvée.', $saved['body']);
        $this->assertSame(0, (int) $saved['is_read']);

        $this->assertSame(1, Notification::count());
    }

    public function testSaveWithoutReferenceIdStoresNull(): void
    {
        $saved = $this->repo->save([
            'user_id'    => 5,
            'type'       => 'message_received',
            'body'       => 'Nouveau message.',
            'is_read'    => 0,
            'created_at' => '2026-08-05 10:00:00',
        ]);

        $this->assertNull($saved['reference_id']);
    }

    public function testFindByUserReturnsBodyAndReferenceIdDecoded(): void
    {
        $this->repo->save([
            'user_id' => 5, 'type' => 'shift_assigned', 'body' => 'Nouveau shift.',
            'reference_id' => 7, 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00',
        ]);

        $rows = $this->repo->findByUser(5);

        $this->assertCount(1, $rows);
        $this->assertSame('Nouveau shift.', $rows[0]['body']);
        $this->assertSame(7, (int) $rows[0]['reference_id']);
        $this->assertSame(0, (int) $rows[0]['is_read']);
    }

    public function testMarkReadUpdatesIsReadDerivedField(): void
    {
        $saved = $this->repo->save([
            'user_id' => 5, 'type' => 'shift_assigned', 'body' => 'Nouveau shift.',
            'is_read' => 0, 'created_at' => '2026-08-05 10:00:00',
        ]);

        $this->repo->markRead((int) $saved['id'], 5);

        $found = $this->repo->findById((int) $saved['id']);
        $this->assertSame(1, (int) $found['is_read']);
        $this->assertNotNull($found['read_at']);
    }

    public function testMarkReadIgnoresOtherUsersNotification(): void
    {
        $saved = $this->repo->save([
            'user_id' => 5, 'type' => 'shift_assigned', 'body' => 'Nouveau shift.',
            'is_read' => 0, 'created_at' => '2026-08-05 10:00:00',
        ]);

        $this->repo->markRead((int) $saved['id'], 999);

        $found = $this->repo->findById((int) $saved['id']);
        $this->assertSame(0, (int) $found['is_read']);
    }

    public function testCountUnreadOnlyCountsUnreadForThatUser(): void
    {
        $this->repo->save(['user_id' => 5, 'type' => 't', 'body' => 'a', 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00']);
        $this->repo->save(['user_id' => 5, 'type' => 't', 'body' => 'b', 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00']);
        $this->repo->save(['user_id' => 6, 'type' => 't', 'body' => 'c', 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00']);

        $this->assertSame(2, $this->repo->countUnread(5));

        $this->repo->markAllRead(5);
        $this->assertSame(0, $this->repo->countUnread(5));
        $this->assertSame(1, $this->repo->countUnread(6));
    }

    public function testFindUnreadSinceFiltersByDateAndReadState(): void
    {
        $this->repo->save(['user_id' => 5, 'type' => 't', 'body' => 'old', 'is_read' => 0, 'created_at' => '2026-08-01 10:00:00']);
        $this->repo->save(['user_id' => 5, 'type' => 't', 'body' => 'new', 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00']);

        $recent = $this->repo->findUnreadSince(5, '2026-08-02 00:00:00');

        $this->assertCount(1, $recent);
        $this->assertSame('new', $recent[0]['body']);
    }

    public function testDeleteRemovesRow(): void
    {
        $saved = $this->repo->save(['user_id' => 5, 'type' => 't', 'body' => 'a', 'is_read' => 0, 'created_at' => '2026-08-05 10:00:00']);

        $this->assertSame(1, $this->repo->delete((int) $saved['id']));
        $this->assertNull($this->repo->findById((int) $saved['id']));
    }
}
