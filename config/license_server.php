<?php

/**
 * Configuration du client de licence distant (déblocage du plan payant).
 *
 * Le plan gratuit (voir PlanLimitService) fonctionne intégralement sans réseau —
 * cette config ne sert que si l'Owner saisit une clé de licence sur /admin/license.
 * `base_url`/`api_key` identifient le produit "kintai" auprès du serveur de
 * licence (License Manager, projet séparé) ; `license_key` (par instance,
 * saisie par l'Owner) est stockée en base, pas ici — voir LicenseClientService.
 *
 * Pour l'activer, dans .env :
 *
 *   LICENSE_SERVER_URL=https://exemple.com/LicenseManager/public/api/v1
 *   LICENSE_SERVER_API_KEY=lm_xxx...
 *   LICENSE_SERVER_PUBLIC_KEY_B64=... (cle Ed25519 publique, voir ci-dessous)
 *
 * Tant que LICENSE_SERVER_URL est vide, LicenseClientService ne fait rien
 * (no-op silencieux) — l'app reste utilisable en plan gratuit sans configuration.
 *
 * `public_key_pem` verifie le license_token signe renvoye par activate/validate
 * (voir LicenseTokenVerifier) : c'est ce jeton, pas les champs plats de la
 * reponse, qui fait foi pour les limites (stores/employes/bundles) une fois
 * decode — un client self-hosted qui editerait sa base locale ne peut pas se
 * fabriquer un token valide sans la cle PRIVEE, qui ne quitte jamais le
 * serveur de licence. Valeur fournie par l'operateur du serveur de licence
 * (cote License Manager : scripts/generate-signing-key.php), a copier telle
 * quelle en base64 dans LICENSE_SERVER_PUBLIC_KEY_B64.
 */

declare(strict_types=1);

$publicKeyB64 = (string) env('LICENSE_SERVER_PUBLIC_KEY_B64', '');

return [
    'base_url'             => rtrim((string) env('LICENSE_SERVER_URL', ''), '/'),
    'api_key'              => (string) env('LICENSE_SERVER_API_KEY', ''),
    'check_interval_hours' => (int) env('LICENSE_SERVER_CHECK_INTERVAL_HOURS', 24),
    'grace_period_days'    => (int) env('LICENSE_SERVER_GRACE_PERIOD_DAYS', 14),
    'public_key_pem'       => $publicKeyB64 !== '' ? (base64_decode($publicKeyB64, true) ?: null) : null,
];
