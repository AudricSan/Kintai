<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

final class JsonTranslationRepository implements TranslationRepositoryInterface
{
    private string $langPath;

    public function __construct(string $langPath)
    {
        $this->langPath = rtrim($langPath, '/\\');
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
        foreach ($this->localeFiles() as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $keys = array_merge($keys, array_keys($data));
            }
        }
        return array_values(array_unique($keys));
    }

    public function save(string $locale, string $key, string $value): void
    {
        $data = $this->load($locale);
        $data[$key] = $value;
        $this->write($locale, $data);
    }

    public function delete(string $locale, string $key): int
    {
        $data = $this->load($locale);
        if (!array_key_exists($key, $data)) {
            return 0;
        }
        unset($data[$key]);
        $this->write($locale, $data);
        return 1;
    }

    public function countByLocale(string $locale): int
    {
        return count($this->load($locale));
    }

    /** @return string[] */
    private function localeFiles(): array
    {
        $files = glob($this->langPath . '/*.json') ?: [];
        return array_values(array_filter($files, fn($f) => basename($f) !== 'languages.json'));
    }

    private function filePath(string $locale): string
    {
        return $this->langPath . '/' . $locale . '.json';
    }

    private function load(string $locale): array
    {
        $file = $this->filePath($locale);
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function write(string $locale, array $data): void
    {
        ksort($data);
        file_put_contents(
            $this->filePath($locale),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
