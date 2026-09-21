<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\LicenseTokenVerifier;
use PHPUnit\Framework\TestCase;

/** Paire de cles generee uniquement pour ce test, distincte de la cle reelle en .env. */
final class LicenseTokenVerifierTest extends TestCase
{
    private const TEST_PRIVATE_KEY_B64 = 'LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tDQpNQzRDQVFBd0JRWURLMlZ3QkNJRUlEUVZIVmVQSCs2aUtkc2E3Z0daTGR0NVJaNDRNLzMrbnNXNjgxRmxJUnpXDQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tDQo=';
    private const TEST_PUBLIC_KEY_B64 = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUNvd0JRWURLMlZ3QXlFQUkzcFdnWks4WUlDWG95MzFNakpiZUhJUkpIVGdxdmxuanlDRDUrVEVZMlk9Ci0tLS0tRU5EIFBVQkxJQyBLRVktLS0tLQo=';
    private const OTHER_PUBLIC_KEY_B64 = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0NCk1Db3dCUVlESzJWd0F5RUFHWFZ3THNwbUdhNmxYZ2ZOa081b1J0YVJZT1RJYXNFT3dwZ1FWRDltNGFJPQ0KLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0tDQo=';

    private function sign(array $payload): string
    {
        $privateKey = openssl_pkey_get_private(base64_decode(self::TEST_PRIVATE_KEY_B64));
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
        $verifier = new LicenseTokenVerifier(base64_decode(self::TEST_PUBLIC_KEY_B64));

        $payload = $verifier->verify($this->sign(['lic_id' => 42, 'limits' => ['max_stores' => 3]]));

        $this->assertSame(['lic_id' => 42, 'limits' => ['max_stores' => 3]], $payload);
    }

    public function testVerifyRejectsTokenSignedByADifferentKey(): void
    {
        $verifier = new LicenseTokenVerifier(base64_decode(self::OTHER_PUBLIC_KEY_B64));

        $this->assertNull($verifier->verify($this->sign(['lic_id' => 42])));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $verifier = new LicenseTokenVerifier(base64_decode(self::TEST_PUBLIC_KEY_B64));
        $token = $this->sign(['limits' => ['max_stores' => 1]]);
        [$payload, $signature] = explode('.', $token, 2);

        $tamperedPayload = rtrim(strtr(base64_encode('{"limits":{"max_stores":9999}}'), '+/', '-_'), '=');

        $this->assertNull($verifier->verify($tamperedPayload . '.' . $signature));
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $verifier = new LicenseTokenVerifier(base64_decode(self::TEST_PUBLIC_KEY_B64));

        $this->assertNull($verifier->verify('not-a-valid-token'));
        $this->assertNull($verifier->verify(''));
    }
}
