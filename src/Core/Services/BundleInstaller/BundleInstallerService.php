<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleInstaller;

use kintai\Core\BundleContract\BundleManifest;
use kintai\Core\InstalledBundleManifestStore;
use kintai\Core\Repositories\InstalledBundleRepositoryInterface;
use kintai\Core\Services\HttpFetcher;
use kintai\Core\Services\Log;
use kintai\Core\Services\UpdateService;

/**
 * Télécharge, vérifie et installe un bundle depuis une release GitHub taguée
 * (jamais de `git clone`/`pull` — hébergements mutualisés sans binaire git
 * garanti, voir docs/architecture.md). Frère de GithubUpdateService : même
 * conventions (tag `v{version}`, zipball d'une release, vérification "un
 * seul dossier racine" après extraction), mêmes points d'injection par
 * closure pour les tests, mais installe un bundle tiers dans
 * storage/bundles/ au lieu de mettre à jour l'application elle-même.
 */
final class BundleInstallerService
{
    private const DOWNLOAD_TIMEOUT_SECONDS = 60;

    private ?string $lastError = null;

    /**
     * @param \Closure|null $releaseFetcher fn(string $repositoryUrl, string $version): ?string — l'URL du zipball de la release taguée v{version}, ou null en cas d'échec (surchargeable pour les tests)
     * @param \Closure|null $zipDownloader  fn(string $url, string $destination): bool — surchargeable pour les tests
     */
    public function __construct(
        private readonly UpdateService $updateService,
        private readonly InstalledBundleRepositoryInterface $installedBundles,
        private readonly InstalledBundleManifestStore $manifestStore = new InstalledBundleManifestStore(),
        private readonly ?string $bundlesDir = null,
        private readonly ?\Closure $releaseFetcher = null,
        private readonly ?\Closure $zipDownloader = null,
        private readonly HttpFetcher $http = new HttpFetcher(),
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function install(string $slug, string $repositoryUrl, string $version, ?string $sourceRegistryUrl = null): BundleInstallResult
    {
        return $this->run($slug, $repositoryUrl, $version, $sourceRegistryUrl, dryRun: false);
    }

    public function dryRun(string $slug, string $repositoryUrl, string $version): BundleInstallResult
    {
        return $this->run($slug, $repositoryUrl, $version, null, dryRun: true);
    }

    /**
     * Repointe la version active vers une version déjà présente sur disque
     * (pas de retéléchargement) — utile si une mise à jour casse quelque chose.
     */
    public function rollback(string $slug, string $version): bool
    {
        $bundleDir = $this->bundlesDir() . "/{$slug}/{$version}";
        if (!is_dir($bundleDir)) {
            $this->lastError = "La version {$version} de {$slug} n'est pas présente sur ce serveur.";
            return false;
        }

        $existing = $this->installedBundles->find($slug);
        $this->manifestStore->setActiveVersion($slug, $version);
        $this->installedBundles->upsert($slug, $version, $existing['source_registry_url'] ?? null);

        return true;
    }

    private function run(string $slug, string $repositoryUrl, string $version, ?string $sourceRegistryUrl, bool $dryRun): BundleInstallResult
    {
        $this->lastError = null;

        $stagingContainer = $this->bundlesDir() . "/{$slug}/.staging/{$version}";
        $zipPath = $stagingContainer . '.zip';

        $downloadUrl = $this->resolveDownloadUrl($repositoryUrl, $version);
        if ($downloadUrl === null) {
            return BundleInstallResult::failure($this->lastError ?? "Impossible de résoudre la release {$version} pour {$repositoryUrl}.");
        }

        if (!$this->download($downloadUrl, $zipPath)) {
            return BundleInstallResult::failure($this->lastError ?? "Échec du téléchargement de l'archive.");
        }

        $verified = $this->verifyAndExtract($zipPath, $slug, $version, $stagingContainer);
        @unlink($zipPath);

        if ($verified === null) {
            $this->removeDirIfExists($stagingContainer);
            return BundleInstallResult::failure($this->lastError ?? 'Vérification du bundle échouée.');
        }

        [$manifest, $extractedRoot] = $verified;

        if ($dryRun) {
            $this->removeDirIfExists($stagingContainer);
            return BundleInstallResult::dryRunOk($manifest);
        }

        $this->activate($slug, $version, $extractedRoot, $sourceRegistryUrl);
        $this->removeDirIfExists($stagingContainer);

        return BundleInstallResult::installed($manifest);
    }

    private function resolveDownloadUrl(string $repositoryUrl, string $version): ?string
    {
        if ($this->releaseFetcher !== null) {
            return ($this->releaseFetcher)($repositoryUrl, $version);
        }

        if (!preg_match('#^https://github\.com/([^/]+)/([^/]+?)/?$#', rtrim($repositoryUrl), $m)) {
            $this->lastError = "URL de dépôt non reconnue : {$repositoryUrl} (seuls les dépôts GitHub sont supportés pour l'instant).";
            return null;
        }

        $apiUrl = "https://api.github.com/repos/{$m[1]}/{$m[2]}/releases/tags/v{$version}";
        $body = $this->http->get($apiUrl, [
            'User-Agent: Kintai-BundleInstaller/1.0',
            'Accept: application/vnd.github+json',
        ], self::DOWNLOAD_TIMEOUT_SECONDS);

        if ($body === null) {
            $this->lastError = "La release v{$version} de {$repositoryUrl} est introuvable ou inaccessible.";
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['zipball_url']) || !is_string($data['zipball_url'])) {
            $this->lastError = "Réponse GitHub inattendue pour la release v{$version}.";
            return null;
        }

        return $data['zipball_url'];
    }

    private function download(string $url, string $destination): bool
    {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($this->zipDownloader !== null) {
            return ($this->zipDownloader)($url, $destination);
        }

        return $this->http->downloadToFile($url, $destination, ['User-Agent: Kintai-BundleInstaller/1.0'], self::DOWNLOAD_TIMEOUT_SECONDS);
    }

    /**
     * @return array{0: BundleManifest, 1: string}|null [manifeste, chemin du dossier extrait]
     */
    private function verifyAndExtract(string $zipPath, string $expectedSlug, string $expectedVersion, string $stagingContainer): ?array
    {
        $extractedRoot = $this->extractZip($zipPath, $stagingContainer . '/extract');
        if ($extractedRoot === null) {
            $this->lastError = "L'archive ne contient pas exactement un dossier racine.";
            return null;
        }

        $manifestPath = $extractedRoot . '/bundle.json';
        if (!is_file($manifestPath)) {
            $this->lastError = 'bundle.json introuvable dans l\'archive.';
            return null;
        }

        $data = json_decode((string) file_get_contents($manifestPath), true);
        $manifest = BundleManifest::fromArray($data);
        if ($manifest === null) {
            $this->lastError = 'bundle.json invalide ou incomplet.';
            return null;
        }

        if ($manifest->slug !== $expectedSlug) {
            $this->lastError = "Le slug du manifeste ({$manifest->slug}) ne correspond pas au bundle attendu ({$expectedSlug}).";
            return null;
        }

        $coreVersion = $this->updateService->getCurrentVersion();
        if (!$manifest->isCompatibleWithCore($coreVersion)) {
            $this->lastError = "Ce bundle nécessite Kintai {$manifest->kintaiCoreMin} à {$manifest->kintaiCoreMax} (version installée : {$coreVersion}).";
            return null;
        }

        $entryFile = $manifest->classFilePath($extractedRoot, $manifest->entryClass);
        if ($entryFile === null || !is_file($entryFile)) {
            $this->lastError = "Classe {$manifest->entryClass} introuvable dans l'archive.";
            return null;
        }

        return [$manifest, $extractedRoot];
    }

    private function activate(string $slug, string $version, string $extractedRoot, ?string $sourceRegistryUrl): void
    {
        $finalDir = $this->bundlesDir() . "/{$slug}/{$version}";
        $this->removeDirIfExists($finalDir);

        $parent = dirname($finalDir);
        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }

        rename($extractedRoot, $finalDir);

        $this->manifestStore->setActiveVersion($slug, $version);
        $this->installedBundles->upsert($slug, $version, $sourceRegistryUrl);

        Log::info('bundle_installed', ['slug' => $slug, 'version' => $version]);
    }

    private function extractZip(string $zipPath, string $extractDir): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if ($dirs === false || count($dirs) !== 1) {
            return null;
        }

        return $dirs[0];
    }

    private function removeDirIfExists(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    private function bundlesDir(): string
    {
        return $this->bundlesDir ?? storage_path('bundles');
    }
}
