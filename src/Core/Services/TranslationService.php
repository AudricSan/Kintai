<?php

declare(strict_types=1);

namespace kintai\Core\Services;

final class TranslationService
{
    private string $locale = 'en';
    private array $translations = [];
    private string $langPath;

    public function __construct(string $langPath)
    {
        $this->langPath = rtrim($langPath, DIRECTORY_SEPARATOR);
    }

    public function setLocale(string $locale): void
    {
        if ($this->locale === $locale && !empty($this->translations)) {
            return;
        }

        $this->locale = $locale;
        $file = $this->langPath . DIRECTORY_SEPARATOR . $locale . '.php';

        if (file_exists($file)) {
            $this->translations = include $file;
        } else {
            // Fallback to English if file doesn't exist
            $fallbackFile = $this->langPath . DIRECTORY_SEPARATOR . 'en.php';
            if (file_exists($fallbackFile)) {
                $this->translations = include $fallbackFile;
            }
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function translate(string $key, array $replace = []): string
    {
        $translation = $this->translations[$key] ?? $key;

        foreach ($replace as $search => $value) {
            $translation = str_replace(':' . $search, (string) $value, $translation);
        }

        return $translation;
    }
}
