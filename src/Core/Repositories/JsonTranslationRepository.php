<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

/**
 * Fusionne les traductions du Core (lang/*.json) avec celles de chaque bundle
 * détecté sur le disque (src/Bundles/<Name>/lang/*.json). Un bundle ne définit
 * que ses propres clés ; toute clé absente de son fichier retombe naturellement
 * sur celle du Core (elles cohabitent dans le même espace de noms plat, comme
 * avant l'introduction des bundles). Un bundle peut aussi surcharger une clé
 * Core en la redéfinissant chez lui.
 */
final class JsonTranslationRepository implements TranslationRepositoryInterface
{
    private string $langPath;
    private ?string $bundlesDir;

    /** @var array<string, array<string,string>> Cache des fichiers déjà lus (chemin => données). */
    private array $fileCache = [];

    public function __construct(string $langPath, ?string $bundlesDir = null)
    {
        $this->langPath = rtrim($langPath, '/\\');
        $this->bundlesDir = $bundlesDir !== null ? rtrim($bundlesDir, '/\\') : null;
    }

    public function findByLocale(string $locale): array
    {
        return $this->load($locale);
    }

    public function findValue(string $locale, string $key): ?string
    {
        return $this->load($locale)[$key] ?? null;
    }

    public function findAllKeys(): array
    {
        $keys = [];
        foreach ($this->localeCodes() as $locale) {
            foreach ($this->layerFiles($locale) as $file) {
                $keys = array_merge($keys, array_keys($this->readJson($file)));
            }
        }
        return array_values(array_unique($keys));
    }

    public function save(string $locale, string $key, string $value): void
    {
        $file = $this->fileOwning($locale, $key) ?? $this->filePath($locale);
        $data = $this->readJson($file);
        $data[$key] = $value;
        $this->write($file, $data);
    }

    public function delete(string $locale, string $key): int
    {
        $file = $this->fileOwning($locale, $key);
        if ($file === null) {
            return 0;
        }
        $data = $this->readJson($file);
        unset($data[$key]);
        $this->write($file, $data);
        return 1;
    }

    public function countByLocale(string $locale): int
    {
        return count($this->load($locale));
    }

    /** @return string[] Codes de langue connus (fr, en, ja...), dérivés des fichiers lang/*.json du Core. */
    private function localeCodes(): array
    {
        return array_map(
            fn(string $f) => basename($f, '.json'),
            $this->localeFiles(),
        );
    }

    /** @return string[] */
    private function localeFiles(): array
    {
        $files = glob($this->langPath . '/*.json') ?: [];
        return array_values(array_filter($files, fn($f) => basename($f) !== 'languages.json'));
    }

    /**
     * Fichiers à fusionner pour une locale donnée, dans l'ordre de priorité
     * croissante : celui du Core d'abord (base), puis celui de chaque bundle
     * trouvé sur le disque (dernier lu = valeur retenue en cas de collision).
     *
     * @return string[]
     */
    private function layerFiles(string $locale): array
    {
        $files = [$this->filePath($locale)];
        if ($this->bundlesDir !== null) {
            $bundleFiles = glob($this->bundlesDir . '/*/lang/' . $locale . '.json') ?: [];
            sort($bundleFiles);
            $files = array_merge($files, $bundleFiles);
        }
        return $files;
    }

    private function filePath(string $locale): string
    {
        return $this->langPath . '/' . $locale . '.json';
    }

    private function load(string $locale): array
    {
        $merged = [];
        foreach ($this->layerFiles($locale) as $file) {
            $merged = array_merge($merged, $this->readJson($file));
        }
        return $merged;
    }

    /** Fichier possédant déjà $key pour $locale (priorité aux bundles), ou null si la clé n'existe nulle part. */
    private function fileOwning(string $locale, string $key): ?string
    {
        $owner = null;
        foreach ($this->layerFiles($locale) as $file) {
            if (array_key_exists($key, $this->readJson($file))) {
                $owner = $file;
            }
        }
        return $owner;
    }

    private function readJson(string $file): array
    {
        if (array_key_exists($file, $this->fileCache)) {
            return $this->fileCache[$file];
        }
        if (!file_exists($file)) {
            return $this->fileCache[$file] = [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return $this->fileCache[$file] = (is_array($data) ? $data : []);
    }

    private function write(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        ksort($data);
        file_put_contents(
            $file,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
        $this->fileCache[$file] = $data;
    }
}
