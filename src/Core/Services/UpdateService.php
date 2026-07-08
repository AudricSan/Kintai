<?php

declare(strict_types=1);

namespace kintai\Core\Services;

final class UpdateService
{
    private string $versionFile;
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->versionFile = storage_path('app/version.json');
        $this->basePath = $basePath ?? BASE_PATH;
    }

    /**
     * La version installée est celle déclarée dans config/app.php (bumpée à
     * chaque release, et synchronisée par GithubUpdateService::syncFiles()
     * lors d'une mise à jour) — pas de fichier JSON supplémentaire à tenir à jour.
     */
    public function getCurrentVersion(): string
    {
        $configFile = $this->basePath . '/config/app.php';
        if (!file_exists($configFile)) {
            return '0.0.0';
        }
        $config = require $configFile;
        return $config['version'] ?? '0.0.0';
    }

    public function getInstalledAt(): ?string
    {
        return $this->readVersion()['installed_at'] ?? null;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->readVersion()['updated_at'] ?? null;
    }

    public function getLastUpdateDuration(): ?int
    {
        $seconds = $this->readVersion()['duration_seconds'] ?? null;
        return $seconds === null ? null : (int) $seconds;
    }

    public function recordUpdateDuration(int $seconds): void
    {
        $data = $this->readVersion();
        $data['duration_seconds'] = $seconds;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->writeVersion($data);
    }

    public function hasPendingMigrations(): bool
    {
        $migrated = $this->getExecutedMigrations();
        $all = $this->getAvailableMigrations();
        return array_diff($all, $migrated) !== [];
    }

    public function getPendingMigrations(): array
    {
        $migrated = $this->getExecutedMigrations();
        $all = $this->getAvailableMigrations();
        return array_values(array_diff($all, $migrated));
    }

    private function readVersion(): array
    {
        if (!file_exists($this->versionFile)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->versionFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeVersion(array $data): void
    {
        $dir = dirname($this->versionFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->versionFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function getExecutedMigrations(): array
    {
        try {
            return \Illuminate\Database\Capsule\Manager::table('migrations')
                ->pluck('migration')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getAvailableMigrations(): array
    {
        $path = BASE_PATH . '/database/migrations/php';
        if (!is_dir($path)) {
            return [];
        }
        $files = glob($path . '/*.php');
        if ($files === false) {
            return [];
        }
        sort($files);
        return array_map(function (string $f): string {
            return basename($f, '.php');
        }, $files);
    }
}
