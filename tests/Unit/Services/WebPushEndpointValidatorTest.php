<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\WebPushEndpointValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Une adresse d'abonnement ne doit jamais permettre au serveur d'émettre des requêtes vers l'intérieur. */
final class WebPushEndpointValidatorTest extends TestCase
{
    /** Résolveur factice : un nom « interne » se résout vers une adresse privée, tout autre vers une adresse publique. */
    private function validator(): WebPushEndpointValidator
    {
        return new WebPushEndpointValidator(static fn(string $host): array => match (true) {
            str_starts_with($host, 'interne.') => ['10.0.0.12'],
            str_starts_with($host, 'metadata.') => ['169.254.169.254'],
            str_starts_with($host, 'mixte.') => ['142.250.74.10', '192.168.1.5'],
            str_starts_with($host, 'inconnu.') => [],
            default => ['142.250.74.10'],
        });
    }

    /** @return array<string, array{string}> */
    public static function realPushServices(): array
    {
        return [
            'FCM (Chrome, Edge, Brave)' => ['https://fcm.googleapis.com/fcm/send/dXNlci1wdXNoLXRva2Vu'],
            'Mozilla'                   => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAAABk'],
            'Apple'                     => ['https://web.push.apple.com/QGxGp3Wy'],
            'Windows'                   => ['https://wns2-par02p.notify.windows.com/w/?token=BQYAAAB'],
            'port 443 explicite'        => ['https://fcm.googleapis.com:443/fcm/send/abc'],
        ];
    }

    #[DataProvider('realPushServices')]
    public function testAcceptsRealPushServices(string $endpoint): void
    {
        $this->assertTrue($this->validator()->isAcceptable($endpoint));
    }

    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        return [
            'vide'                         => [''],
            'http'                         => ['http://fcm.googleapis.com/fcm/send/abc'],
            'autre schéma'                 => ['ftp://fcm.googleapis.com/x'],
            'sans schéma'                  => ['fcm.googleapis.com/fcm/send/abc'],
            'identifiants dans l\'adresse' => ['https://user:pass@fcm.googleapis.com/x'],
            'port inhabituel'              => ['https://fcm.googleapis.com:8443/x'],
            'adresse IPv4'                 => ['https://142.250.74.10/x'],
            'IPv4 de bouclage'             => ['https://127.0.0.1/x'],
            'IPv6'                         => ['https://[::1]/x'],
            'localhost'                    => ['https://localhost/x'],
            'nom sans point'               => ['https://serveur/x'],
            'nom local'                    => ['https://imprimante.local/x'],
            'nom interne'                  => ['https://wiki.internal/x'],
            'se résout en privé'           => ['https://interne.exemple.org/x'],
            'métadonnées d\'hébergeur'     => ['https://metadata.exemple.org/latest'],
            'une adresse privée parmi d\'autres' => ['https://mixte.exemple.org/x'],
            'ne se résout pas'             => ['https://inconnu.exemple.org/x'],
            'trop longue'                  => ['https://fcm.googleapis.com/' . str_repeat('a', 2000)],
        ];
    }

    #[DataProvider('refused')]
    public function testRefusesAnythingElse(string $endpoint): void
    {
        $this->assertFalse($this->validator()->isAcceptable($endpoint));
    }
}
