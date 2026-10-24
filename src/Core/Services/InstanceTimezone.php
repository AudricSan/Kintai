<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Fuseau horaire de l'instance : celui de toutes les heures de l'application (planning, pointage, rappels, synchronisation
 * de calendrier, flux iCal). Choisi par l'Owner dans les paramètres de l'instance, enregistré dans le fichier
 * d'environnement (APP_TIMEZONE), lu par config/app.php au démarrage. Le fuseau d'un magasin n'entre pas en jeu.
 */
final class InstanceTimezone
{
    public const ENV_KEY = 'APP_TIMEZONE';

    public function __construct(private readonly ?EnvFileWriter $envFile = null) {}

    /** Fuseau en vigueur (celui appliqué par Application au démarrage). */
    public function code(): string
    {
        return date_default_timezone_get();
    }

    /** @return list<string> identifiants IANA proposables (Europe/Paris, Asia/Tokyo…) */
    public static function identifiers(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    public static function isValid(string $identifier): bool
    {
        return in_array($identifier, self::identifiers(), true);
    }

    /**
     * Change le fuseau de l'instance (écrit dans le fichier d'environnement).
     *
     * @throws \InvalidArgumentException identifiant inconnu
     * @throws \RuntimeException         fichier d'environnement non inscriptible
     */
    public function set(string $identifier): void
    {
        if (!self::isValid($identifier)) {
            throw new \InvalidArgumentException("Fuseau horaire inconnu : {$identifier}");
        }
        ($this->envFile ?? new EnvFileWriter())->set(self::ENV_KEY, $identifier);
    }
}
