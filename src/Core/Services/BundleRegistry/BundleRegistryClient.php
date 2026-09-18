<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleRegistry;

use kintai\Core\Services\Log;

/**
 * Récupère et valide le fichier de listing (registry.json) d'un registry de
 * bundles. Volontairement un simple GET HTTP sur un fichier statique, jamais
 * un clone du dépôt du registry — voir docs/architecture.md "Modular Bundles".
 *
 * Même stratégie de repli curl -> wrapper de flux que
 * GithubUpdateService::httpGet() (hébergements mutualisés où l'extension
 * curl peut manquer), gardée volontairement séparée : ce client ne
 * télécharge jamais d'archive, seulement un petit JSON.
 */
final class BundleRegistryClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @param \Closure|null $fetcher fn(string $url): ?string — corps de la réponse HTTP, ou null en cas d'échec (surchargeable pour les tests)
     */
    public function __construct(
        private readonly ?\Closure $fetcher = null,
    ) {
    }

    public function fetchListing(string $url): ?BundleRegistryListing
    {
        $body = $this->fetch($url);
        if ($body === null) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            Log::warning('bundle_registry_invalid_json', ['url' => $url]);
            return null;
        }

        $listing = BundleRegistryListing::fromArray($data);
        if ($listing === null) {
            Log::warning('bundle_registry_unsupported_schema', ['url' => $url]);
            return null;
        }

        return $listing;
    }

    private function fetch(string $url): ?string
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $headers = ['User-Agent: Kintai-BundleRegistry/1.0'];

        if (function_exists('curl_init')) {
            return $this->fetchViaCurl($url, $headers);
        }

        if (!(bool) ini_get('allow_url_fopen')) {
            Log::warning('bundle_registry_no_http_method', ['url' => $url]);
            return null;
        }

        return $this->fetchViaStreamWrapper($url, $headers);
    }

    private function fetchViaCurl(string $url, array $headers): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::warning('bundle_registry_curl_failed', ['url' => $url, 'curl_error' => $error]);
            return null;
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            Log::warning('bundle_registry_http_error', ['url' => $url, 'status' => $status]);
            return null;
        }

        return (string) $body;
    }

    private function fetchViaStreamWrapper(string $url, array $headers): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $headers),
                'timeout'       => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            Log::warning('bundle_registry_stream_failed', ['url' => $url, 'php_error' => $error['message'] ?? 'raison inconnue']);
            return null;
        }

        return $response;
    }
}
