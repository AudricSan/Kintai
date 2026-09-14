<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Repositories\DatabaseNotificationRepository;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\JsonLanguageRepository;
use kintai\Core\Repositories\JsonTranslationRepository;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\PushNotificationService;
use kintai\Core\Services\TranslationService;
use kintai\Domain\Eloquent\Notification;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

/**
 * Utilise le vrai DatabaseNotificationRepository contre un vrai schéma
 * SQLite plutôt qu'un mock — notify()/notifyMany() doivent réellement
 * pouvoir écrire une ligne, pas seulement "être appelées avec les bons
 * arguments" (ce que les tests des contrôleurs métier, qui mockent
 * NotificationService en entier, ne vérifient jamais).
 *
 * TranslationService est construit avec les vrais lang/*.json du dépôt
 * (comme BundleTranslationsRealFilesTest) plutôt qu'un stub, pour vérifier
 * que la résolution de locale par destinataire (users.language) produit
 * réellement le texte attendu dans chaque langue — pas seulement qu'une
 * traduction quelconque a été appelée.
 */
final class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private UserRepositoryInterface $users;

    /** @var array<int, array{id:int, language:?string}> */
    private array $usersById = [];

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

        // FCM désactivé (config vide) : PushNotificationService::sendToUser() est un
        // no-op immédiat, aucun appel réseau n'a lieu dans ce test.
        $push = new PushNotificationService([], $this->createMock(DevicePushTokenRepositoryInterface::class));

        $translations = new TranslationService(
            new JsonTranslationRepository(BASE_PATH . '/lang', BASE_PATH . '/src/Bundles'),
            new JsonLanguageRepository(BASE_PATH . '/lang'),
        );

        $this->usersById = [];
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->users->method('findById')->willReturnCallback(
            fn(int $id) => $this->usersById[$id] ?? null
        );

        $this->service = new NotificationService(new DatabaseNotificationRepository(), $push, $translations, $this->users);
    }

    private function registerUser(int $id, ?string $language): void
    {
        $this->usersById[$id] = ['id' => $id, 'language' => $language];
    }

    private function lang(string $locale): array
    {
        return json_decode((string) file_get_contents(BASE_PATH . "/lang/{$locale}.json"), true);
    }

    public function testNotifyInsertsARealRowWithoutThrowing(): void
    {
        $this->registerUser(5, 'fr');
        $this->service->notify(5, 'timeoff_approved', 'notif_timeoff_approved_body', [], 42);

        $this->assertSame(1, Notification::count());
        $row = Notification::first()->toArray();
        $this->assertSame(5, (int) $row['user_id']);
        $this->assertSame('timeoff_approved', $row['type']);
        $this->assertNull($row['read_at']);
    }

    public function testNotifyWithoutReferenceIdDoesNotThrow(): void
    {
        $this->registerUser(5, 'fr');
        $this->service->notify(5, 'message_received', 'notif_message_received_body', ['subject' => 'Test']);

        $this->assertSame(1, Notification::count());
    }

    public function testNotifyManyInsertsOneRowPerUniqueUser(): void
    {
        $this->registerUser(5, 'fr');
        $this->registerUser(6, 'fr');
        $this->registerUser(7, 'fr');
        $this->service->notifyMany([5, 6, 6, 7], 'open_shift_published', 'notif_shift_claim_pending_body');

        $this->assertSame(3, Notification::count());
    }

    /**
     * Coeur du fix i18n : deux destinataires avec des langues différentes reçoivent
     * un corps de notification traduit dans leur propre langue, pas dans une locale
     * globale partagée.
     */
    public function testNotifyResolvesLocalePerRecipient(): void
    {
        $this->registerUser(10, 'en');
        $this->registerUser(20, 'ja');

        $this->service->notify(10, 'timeoff_approved', 'notif_timeoff_approved_body', [], 1);
        $this->service->notify(20, 'timeoff_approved', 'notif_timeoff_approved_body', [], 2);

        $rows = Notification::orderBy('user_id')->get()->toArray();
        $this->assertCount(2, $rows);

        $bodyForUser10 = json_decode((string) $rows[0]['data'], true)['body'] ?? null;
        $bodyForUser20 = json_decode((string) $rows[1]['data'], true)['body'] ?? null;

        $this->assertSame($this->lang('en')['notif_timeoff_approved_body'], $bodyForUser10);
        $this->assertSame($this->lang('ja')['notif_timeoff_approved_body'], $bodyForUser20);
        $this->assertNotSame($bodyForUser10, $bodyForUser20);
    }

    /** Utilisateur introuvable (supprimé entre-temps) → repli sur 'fr'. */
    public function testNotifyFallsBackToFrenchWhenUserNotFound(): void
    {
        // 999 n'a jamais été enregistré via registerUser() → findById() renvoie null.
        $this->service->notify(999, 'timeoff_approved', 'notif_timeoff_approved_body', [], 1);

        $row = Notification::first()->toArray();
        $body = json_decode((string) $row['data'], true)['body'] ?? null;
        $this->assertSame($this->lang('fr')['notif_timeoff_approved_body'], $body);
    }

    /** language présent mais vide → repli sur 'fr' plutôt qu'une locale invalide. */
    public function testNotifyFallsBackToFrenchWhenLanguageIsEmpty(): void
    {
        $this->registerUser(30, '');
        $this->service->notify(30, 'timeoff_approved', 'notif_timeoff_approved_body', [], 1);

        $row = Notification::first()->toArray();
        $body = json_decode((string) $row['data'], true)['body'] ?? null;
        $this->assertSame($this->lang('fr')['notif_timeoff_approved_body'], $body);
    }

    /** Les placeholders :xxx sont bien substitués dans la locale résolue. */
    public function testNotifyReplacesPlaceholdersInResolvedLocale(): void
    {
        $this->registerUser(40, 'en');
        $this->service->notify(40, 'shift_assigned', 'notif_shift_assigned_body', ['date' => '2026-09-20']);

        $row = Notification::first()->toArray();
        $body = json_decode((string) $row['data'], true)['body'] ?? null;
        $this->assertSame(
            str_replace(':date', '2026-09-20', $this->lang('en')['notif_shift_assigned_body']),
            $body
        );
    }
}
