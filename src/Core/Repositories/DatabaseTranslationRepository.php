<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Translation;

final class DatabaseTranslationRepository implements TranslationRepositoryInterface
{
    public function findByLocale(string $locale): array
    {
        return Translation::where('language_code', $locale)
            ->pluck('value', 'key')
            ->toArray();
    }

    public function findValue(string $locale, string $key): ?string
    {
        $t = Translation::where('language_code', $locale)->where('key', $key)->first();
        return $t ? $t->value : null;
    }

    public function findAllKeys(): array
    {
        return Translation::query()->distinct()->pluck('key')->toArray();
    }

    public function save(string $locale, string $key, string $value): void
    {
        Translation::updateOrCreate(
            ['language_code' => $locale, 'key' => $key],
            ['value' => $value]
        );
    }

    public function delete(string $locale, string $key): int
    {
        return Translation::where('language_code', $locale)->where('key', $key)->delete();
    }

    public function deleteKey(string $key): int
    {
        return Translation::where('key', $key)->delete();
    }

    public function countByLocale(string $locale): int
    {
        return Translation::where('language_code', $locale)->count();
    }
}
