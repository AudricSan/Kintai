<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Contrôle de l'adresse (« endpoint ») d'un abonnement web push avant de l'enregistrer.
 *
 * Le serveur enverra des requêtes POST à cette adresse : sans contrôle, un utilisateur pourrait y mettre une adresse
 * interne (réseau local, métadonnées d'un hébergeur) et faire émettre des requêtes depuis le serveur. Les services de
 * push réels (FCM, Mozilla, Apple, Windows…) sont tous en HTTPS sur un nom de domaine public ; on refuse donc le
 * reste : autre schéma, identifiants dans l'adresse, port inhabituel, adresse IP littérale, nom local, et tout nom
 * qui se résout vers une adresse privée ou réservée.
 */
final class WebPushEndpointValidator
{
    private const MAX_LENGTH = 2000;

    /** @var (callable(string): list<string>)|null Résolution DNS injectable pour les tests */
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    public function isAcceptable(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > self::MAX_LENGTH) {
            return false;
        }
        $parts = parse_url($endpoint);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if (!str_contains($host, '.') || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|test|invalid|example)$/', $host) === 1) {
            return false;
        }

        $ips = $this->resolve($host);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return true;
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }
        $ips = @gethostbynamel($host);
        return $ips === false ? [] : $ips;
    }
}
