<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Verifie localement le `license_token` signe renvoye par le serveur de
 * licence (voir config/license_server.php pour le pourquoi). Ed25519 via
 * l'extension openssl (pas sodium, indisponible sur certains environnements
 * XAMPP), avec l'algo "0" (EdDSA fait son propre hachage). C'est cette
 * verification, pas la simple presence d'un champ en base, qui fait foi pour
 * PlanLimitService : sans cle publique configuree ou avec une signature
 * invalide, verify() renvoie null et l'appelant retombe sur le plan gratuit.
 */
final class LicenseTokenVerifier
{
    public function __construct(private readonly ?string $publicKeyPem)
    {
    }

    public function isConfigured(): bool
    {
        return $this->publicKeyPem !== null;
    }

    /** @return array|null Le payload decode si la signature est valide, sinon null. */
    public function verify(string $token): ?array
    {
        if ($this->publicKeyPem === null) {
            return null;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$encodedPayload, $encodedSignature] = $parts;

        $signature = self::base64UrlDecode($encodedSignature);
        if ($signature === false) {
            return null;
        }

        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        if ($publicKey === false) {
            return null;
        }

        if (openssl_verify($encodedPayload, $signature, $publicKey, 0) !== 1) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($encodedPayload);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        return is_array($payload) ? $payload : null;
    }

    private static function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
