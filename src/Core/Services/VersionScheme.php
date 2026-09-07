<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Connaissance du schéma de version interne "X.Y.Z[-LN]" (voir
 * docs/releasing.md) : X.Y.Z est bumpé à la main par release, tandis que
 * -LN (lettre de semaine + sous-version) est calculé et ajouté au tag Git
 * par .github/workflows/release.yml — jamais stocké dans config/app.php,
 * qui ne contient toujours que X.Y.Z (voir UpdateService::getCurrentVersion()).
 */
final class VersionScheme
{
    /** @return array{0: string, 1: ?string} [X.Y.Z, LN ou null] */
    public static function split(string $version): array
    {
        if (preg_match('/^(\d+\.\d+\.\d+)-([a-z]+\d+)$/i', $version, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        return [$version, null];
    }

    public static function baseOf(string $version): string
    {
        return self::split($version)[0];
    }

    /**
     * true si $latest est postérieure à $current. version_compare() natif
     * classe un suffixe alphabétique non reconnu ("ak3") EN DESSOUS d'une
     * chaîne sans suffixe, ce qui masquerait à tort une prerelease dont la
     * base X.Y.Z est déjà installée (le suffixe n'étant jamais conservé une
     * fois la release appliquée) : on compare donc la base numériquement en
     * premier, et on ne compare le suffixe que si les deux bases sont égales.
     */
    public static function isNewer(string $latest, string $current): bool
    {
        [$latestBase, $latestSuffix] = self::split($latest);
        [$currentBase, $currentSuffix] = self::split($current);

        $baseComparison = version_compare($latestBase, $currentBase);
        if ($baseComparison !== 0) {
            return $baseComparison > 0;
        }

        if ($latestSuffix === null) {
            return false;
        }
        if ($currentSuffix === null) {
            return true;
        }

        return version_compare($latestSuffix, $currentSuffix, '>');
    }
}
