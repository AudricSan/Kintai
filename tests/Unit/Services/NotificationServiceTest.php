<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Repositories\DatabaseNotificationRepository;
use kintai\Core\Services\NotificationService;
use kintai\Domain\Eloquent\Notification;
use PHPUnit\Framework\TestCase;

/**
 * Utilise le vrai DatabaseNotificationRepository contre un vrai schéma
 * SQLite plutôt qu'un mock — notify()/notifyMany() doivent réellement
 * pouvoir écrire une ligne, pas seulement "être appelées avec les bons
 * arguments" (ce que les tests des contrôleurs métier, qui mockent
 * NotificationService en entier, ne vérifient jamais).
 */
final class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

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

        $this->service = new NotificationService(new DatabaseNotificationRepository());
    }

    public function testNotifyInsertsARealRowWithoutThrowing(): void
    {
        $this->service->notify(5, 'timeoff_approved', 'Votre congé a été approuvé.', 42);

        $this->assertSame(1, Notification::count());
        $row = Notification::first()->toArray();
        $this->assertSame(5, (int) $row['user_id']);
        $this->assertSame('timeoff_approved', $row['type']);
        $this->assertNull($row['read_at']);
    }

    public function testNotifyWithoutReferenceIdDoesNotThrow(): void
    {
        $this->service->notify(5, 'message_received', 'Nouveau message.');

        $this->assertSame(1, Notification::count());
    }

    public function testNotifyManyInsertsOneRowPerUniqueUser(): void
    {
        $this->service->notifyMany([5, 6, 6, 7], 'open_shift_published', 'Un shift est disponible.');

        $this->assertSame(3, Notification::count());
    }
}
