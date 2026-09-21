<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\LicenseTokenVerifier;
use kintai\Tests\Support\TestEd25519Keypair;
use PHPUnit\Framework\TestCase;

final class LicenseTokenVerifierTest extends TestCase
{
    /** @var array{private: string, public: string} */
    private static array $keypair;
    /** @var array{private: string, public: string} Paire non liee, pour tester le rejet d'une mauvaise cle. */
    private static array $otherKeypair;

    public static function setUpBeforeClass(): void
    {
        self::$keypair = TestEd25519Keypair::generate();
        self::$otherKeypair = TestEd25519Keypair::generate();
    }

    private function sign(array $payload): string
    {
        $privateKey = openssl_pkey_get_private(self::$keypair['private']);
        $encode = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $encodedPayload = $encode(json_encode($payload, JSON_THROW_ON_ERROR));
        openssl_sign($encodedPayload, $signature, $privateKey, 0);

        return $encodedPayload . '.' . $encode($signature);
    }

    public function testVerifyReturnsNullWithoutConfiguredPublicKey(): void
    {
        $verifier = new LicenseTokenVerifier(null);

        $this->assertFalse($verifier->isConfigured());
        $this->assertNull($verifier->verify($this->sign(['a' => 1])));
    }

    public function testVerifyDecodesAValidToken(): void
    {
        $verifier = new LicenseTokenVerifier(self::$keypair['public']);

        $payload = $verifier->verify($this->sign(['lic_id' => 42, 'limits' => ['max_stores' => 3]]));

        $this->assertSame(['lic_id' => 42, 'limits' => ['max_stores' => 3]], $payload);
    }

    public function testVerifyRejectsTokenSignedByADifferentKey(): void
    {
        $verifier = new LicenseTokenVerifier(self::$otherKeypair['public']);

        $this->assertNull($verifier->verify($this->sign(['lic_id' => 42])));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $verifier = new LicenseTokenVerifier(self::$keypair['public']);
        $token = $this->sign(['limits' => ['max_stores' => 1]]);
        [$payload, $signature] = explode('.', $token, 2);

        $tamperedPayload = rtrim(strtr(base64_encode('{"limits":{"max_stores":9999}}'), '+/', '-_'), '=');

        $this->assertNull($verifier->verify($tamperedPayload . '.' . $signature));
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $verifier = new LicenseTokenVerifier(self::$keypair['public']);

        $this->assertNull($verifier->verify('not-a-valid-token'));
        $this->assertNull($verifier->verify(''));
    }
}
