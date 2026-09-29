<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleRegistry;

/**
 * Une entrée d'un fichier de listing de registry (registry.json) : un bundle
 * disponible, pas encore forcément installé. Voir BundleRegistryListing pour
 * le format complet du fichier.
 *
 * `versions` accepte deux formes selon le schema_version du registry source
 * (voir BundleRegistryListing) :
 * - schema 1 (historique) : une liste plate, sans notion de canal — offerte
 *   à l'identique sur les trois canaux (aucune information pour distinguer).
 * - schema 2 : un objet {release, beta, alpha}, chaque clé une liste triée
 *   la plus récente en premier, calculée par le registry lui-même (voir
 *   scripts/sync-versions.js du dépôt KintaiBundle) à partir du
 *   target_commitish/prerelease des releases GitHub de chaque bundle — même
 *   sémantique de canal que AppSettingsService::updateChannel()/
 *   GithubUpdateService côté auto-update du Core.
 */
final readonly class BundleRegistryEntry
{
    private const CHANNELS = ['release', 'beta', 'alpha'];

    /**
     * @param string[] $versions Compat schema 1 : liste plate, la plus récente en premier (= versionsByChannel['release'] pour un schema 2).
     * @param array{release: string[], beta: string[], alpha: string[]} $versionsByChannel
     * @param array<string, string> $commits Version -> sha (40 hex minuscules) du commit vers lequel pointe son tag,
     *        épinglé par le registry (voir scripts/sync-versions.js du dépôt KintaiBundle). Vide pour un registry qui
     *        ne l'épingle pas : l'installation se fait alors sans ce contrôle.
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $repositoryUrl,
        public array $versions,
        public array $versionsByChannel,
        public array $commits = [],
    ) {
    }

    /** Commit épinglé pour cette version, ou null si le registry n'en épingle pas. */
    public function commitFor(string $version): ?string
    {
        return $this->commits[$version] ?? null;
    }

    /** Versions disponibles pour le canal donné (release/beta/alpha), la plus récente en premier. */
    public function versionsForChannel(string $channel): array
    {
        return $this->versionsByChannel[$channel] ?? $this->versions;
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }
        if (!isset($data['slug'], $data['repository_url']) || !is_string($data['slug']) || !is_string($data['repository_url'])) {
            return null;
        }
        if ($data['slug'] === '' || $data['repository_url'] === '') {
            return null;
        }

        [$versions, $versionsByChannel] = self::normalizeVersions($data['versions'] ?? []);

        return new self(
            slug: $data['slug'],
            name: is_string($data['name'] ?? null) ? $data['name'] : $data['slug'],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            repositoryUrl: $data['repository_url'],
            versions: $versions,
            versionsByChannel: $versionsByChannel,
            commits: self::normalizeCommits($data['commits'] ?? []),
        );
    }

    /**
     * Ne garde que les couples valides (version X.Y.Z -> sha de 40 hex minuscules) : une valeur mal formée
     * est ignorée plutôt que de risquer de comparer l'archive à autre chose qu'un vrai commit.
     *
     * @return array<string, string>
     */
    private static function normalizeCommits(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $commits = [];
        foreach ($raw as $version => $sha) {
            if (is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1
                && is_string($sha) && preg_match('/^[0-9a-f]{40}$/', $sha) === 1) {
                $commits[$version] = $sha;
            }
        }
        return $commits;
    }

    /**
     * @return array{0: string[], 1: array{release: string[], beta: string[], alpha: string[]}}
     */
    private static function normalizeVersions(mixed $raw): array
    {
        if (is_array($raw) && array_is_list($raw)) {
            $flat = array_values(array_map('strval', $raw));
            return [$flat, ['release' => $flat, 'beta' => $flat, 'alpha' => $flat]];
        }

        if (is_array($raw)) {
            $byChannel = [];
            foreach (self::CHANNELS as $channel) {
                $list = $raw[$channel] ?? [];
                $byChannel[$channel] = is_array($list) ? array_values(array_map('strval', $list)) : [];
            }
            return [$byChannel['release'], $byChannel];
        }

        return [[], ['release' => [], 'beta' => [], 'alpha' => []]];
    }
}
