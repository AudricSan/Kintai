<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Database\MigrationRunner;

/**
 * Met à jour l'application depuis une Release GitHub taguée : télécharge
 * l'archive de la release, synchronise les fichiers (en supprimant ceux
 * qui ont disparu depuis la dernière mise à jour appliquée par ce
 * mécanisme), lance les migrations en attente et tente `composer install`
 * si composer.lock a changé.
 */
final class GithubUpdateService
{
    /** Préfixes jamais écrasés ni supprimés, relatifs à BASE_PATH (style unix). */
    private const EXCLUDED_PREFIXES = [
        'storage/',
        'vendor/',
        '.git/',
        'config/database.local.php',
        '.env',
    ];

    private string $basePath;
    private string $manifestFile;
    private string $updatesDir;
    private string $repo;
    private string $token;

    /**
     * @param \Closure|null $releaseFetcher fn(string $repo, string $token): ?array — surchargeable pour les tests
     * @param \Closure|null $zipDownloader  fn(string $url, string $dest, string $token): bool — surchargeable pour les tests
     */
    public function __construct(
        private readonly UpdateService $updateService,
        private readonly BackupService $backup,
        private readonly MigrationRunner $migrator,
        ?string $basePath = null,
        private readonly ?\Closure $releaseFetcher = null,
        private readonly ?\Closure $zipDownloader = null,
    ) {
        $this->basePath = $basePath ?? BASE_PATH;
        $this->manifestFile = storage_path('app/update-files-manifest.json');
        $this->updatesDir = storage_path('app/updates');
        $this->repo = env('GITHUB_UPDATE_REPO', 'AudricSan/Kintai');
        $this->token = env('GITHUB_UPDATE_TOKEN', '');
    }

    /**
     * Interroge la dernière Release GitHub taguée. Retourne null si le repo
     * n'est pas configuré, si la requête échoue, ou si aucune release
     * n'existe encore (404 sur /releases/latest).
     */
    public function checkLatestRelease(): ?array
    {
        if ($this->repo === '') {
            return null;
        }

        $release = $this->fetchLatestReleaseData();
        if ($release === null || !isset($release['tag_name'], $release['zipball_url'])) {
            return null;
        }

        $current = $this->updateService->getCurrentVersion();
        $latest = ltrim((string) $release['tag_name'], 'v');

        return [
            'current_version' => $current,
            'latest_version'  => $latest,
            'has_update'      => version_compare($latest, $current, '>'),
            'release_notes'   => $release['body'] ?? '',
            'release_url'     => $release['html_url'] ?? null,
            'published_at'    => $release['published_at'] ?? null,
            'zipball_url'     => $release['zipball_url'],
            'tag_name'        => $release['tag_name'],
        ];
    }

    /**
     * Applique la dernière mise à jour disponible : backup de sécurité,
     * téléchargement + synchronisation des fichiers, migrations, bump de
     * version, puis tentative composer en tout dernier (best-effort).
     *
     * @param (callable(int, string): void)|null $onProgress reçoit (pourcentage 0-100, libellé de l'étape en cours)
     */
    public function applyUpdate(?callable $onProgress = null): array
    {
        $progress = $onProgress ?? function (int $percent, string $label): void {};
        $startedAt = microtime(true);

        $progress(0, __('backup_update_step_check'));
        $release = $this->checkLatestRelease();
        if ($release === null || !$release['has_update']) {
            return ['ok' => false, 'error' => 'no_update_available'];
        }

        $tag = $release['tag_name'];
        $safeTag = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $tag);
        $extractDir = null;
        $downloadPath = null;

        try {
            $progress(10, __('backup_update_step_backup'));
            $this->createSafetyBackups($tag);

            $progress(25, __('backup_update_step_download'));
            $downloadPath = $this->updatesDir . '/download_' . $safeTag . '.zip';
            if (!$this->downloadZip($release['zipball_url'], $downloadPath)) {
                return ['ok' => false, 'error' => 'download_failed'];
            }

            $progress(45, __('backup_update_step_extract'));
            $extractDir = $this->updatesDir . '/extract_' . $safeTag;
            $sourceRoot = $this->extractZip($downloadPath, $extractDir);
            if ($sourceRoot === null) {
                return ['ok' => false, 'error' => 'extract_failed'];
            }

            $lockBefore = $this->composerLockHash();
            $sync = $this->syncFiles($sourceRoot, function (int $done, int $total) use ($progress): void {
                $pct = $total > 0 ? (int) round(55 + 20 * ($done / $total)) : 55;
                $progress($pct, __('backup_update_step_sync', ['done' => $done, 'total' => $total]));
            });
            $lockChanged = $lockBefore !== $this->composerLockHash();

            $progress(80, __('backup_update_step_migrate'));
            $migrationsApplied = $this->migrator->run();

            $composerStatus = 'skipped';
            $composerOutput = null;
            if ($lockChanged) {
                $progress(90, __('backup_update_step_composer'));
                $composer = $this->maybeRunComposer();
                $composerStatus = $composer['ran'] ? 'ok' : 'warning';
                $composerOutput = $composer['output'];
            }

            $this->updateService->recordUpdateDuration((int) round(microtime(true) - $startedAt));

            $progress(100, __('backup_update_step_done'));

            return [
                'ok'                 => true,
                'version'            => $release['latest_version'],
                'files_copied'       => $sync['copied'],
                'files_deleted'      => $sync['deleted'],
                'migrations_applied' => $migrationsApplied,
                'composer'           => $composerStatus,
                'composer_output'    => $composerOutput,
            ];
        } finally {
            if ($downloadPath !== null && file_exists($downloadPath)) {
                unlink($downloadPath);
            }
            if ($extractDir !== null && is_dir($extractDir)) {
                $this->removeDir($extractDir);
            }
        }
    }

    private function createSafetyBackups(string $tag): void
    {
        $label = 'avant-maj-' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $tag);
        $this->backup->create('[auto] Avant mise à jour vers ' . $tag);
        $this->backup->createCodeSnapshot($label, $this->basePath);
    }

    /**
     * Copie chaque fichier de $sourceRoot vers $this->basePath, puis supprime
     * les fichiers qui étaient gérés par la mise à jour précédente (présents
     * dans l'ancien manifeste) mais absents de la nouvelle release.
     *
     * @param (callable(int, int): void)|null $onFileProgress reçoit (fichiers traités, total)
     * @return array{copied:int, deleted:int}
     */
    private function syncFiles(string $sourceRoot, ?callable $onFileProgress = null): array
    {
        $newManifest = [];
        $copied = 0;

        $total = iterator_count(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        ));

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $relative = str_replace('\\', '/', $iterator->getSubPathname());
            $newManifest[] = $relative;

            $target = $this->basePath . '/' . $relative;
            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            copy($item->getRealPath(), $target);
            $copied++;

            if ($onFileProgress !== null) {
                $onFileProgress($copied, $total);
            }
        }

        $oldManifest = $this->readManifest();
        $deleted = 0;
        foreach (array_diff($oldManifest, $newManifest) as $relative) {
            if ($this->isExcluded($relative)) {
                continue;
            }
            $path = $this->basePath . '/' . $relative;
            if (is_file($path)) {
                unlink($path);
                $this->pruneEmptyParents(dirname($path));
                $deleted++;
            }
        }

        $this->writeManifest($newManifest);

        return ['copied' => $copied, 'deleted' => $deleted];
    }

    private function isExcluded(string $relative): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function pruneEmptyParents(string $dir): void
    {
        $root = rtrim($this->basePath, '/');
        while (str_starts_with($dir, $root . '/') && $dir !== $root) {
            $entries = @scandir($dir);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) > 0) {
                break;
            }
            rmdir($dir);
            $dir = dirname($dir);
        }
    }

    private function readManifest(): array
    {
        if (!file_exists($this->manifestFile)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->manifestFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeManifest(array $manifest): void
    {
        $dir = dirname($this->manifestFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->manifestFile, json_encode(array_values($manifest), JSON_PRETTY_PRINT));
    }

    private function composerLockHash(): ?string
    {
        $path = $this->basePath . '/composer.lock';
        return file_exists($path) ? md5_file($path) : null;
    }

    /**
     * Tentative best-effort de `composer install`. Appelée en toute fin de
     * processus : un éventuel plantage du sous-processus (voir bug AH02965 —
     * proc_open instable sous Apache/Windows) ne doit jamais remettre en
     * cause les étapes déjà commises (fichiers, migrations, version).
     *
     * @return array{ran:bool, output:?string}
     */
    private function maybeRunComposer(): array
    {
        try {
            if (!function_exists('shell_exec')) {
                return ['ran' => false, 'output' => null];
            }
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('shell_exec', $disabled, true)) {
                return ['ran' => false, 'output' => null];
            }

            $cmd = 'cd ' . escapeshellarg($this->basePath)
                . ' && composer install --no-dev --optimize-autoloader --no-interaction 2>&1';
            $output = shell_exec($cmd);

            return ['ran' => $output !== null, 'output' => $output];
        } catch (\Throwable $e) {
            return ['ran' => false, 'output' => $e->getMessage()];
        }
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

    private function removeDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    private function fetchLatestReleaseData(): ?array
    {
        if ($this->releaseFetcher !== null) {
            return ($this->releaseFetcher)($this->repo, $this->token);
        }

        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $headers = [
            'User-Agent: Kintai-UpdateCheck/1.0',
            'Accept: application/vnd.github+json',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $headers),
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    private function downloadZip(string $url, string $destination): bool
    {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($this->zipDownloader !== null) {
            return ($this->zipDownloader)($url, $destination, $this->token);
        }

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: Kintai-UpdateCheck/1.0\r\n" . ($this->token !== '' ? "Authorization: Bearer {$this->token}\r\n" : ''),
                'timeout'         => 60,
                'ignore_errors'   => true,
                'follow_location' => 1,
            ],
        ]);

        $in = @fopen($url, 'rb', false, $context);
        if ($in === false) {
            return false;
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            fclose($in);
            return false;
        }

        $ok = stream_copy_to_stream($in, $out) !== false;
        fclose($in);
        fclose($out);

        return $ok;
    }
}
