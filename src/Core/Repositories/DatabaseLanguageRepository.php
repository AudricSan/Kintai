<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Language;
use kintai\Domain\Eloquent\Translation;

final class DatabaseLanguageRepository implements LanguageRepositoryInterface
{
    public function findAll(): array
    {
        return Language::orderBy('sort_order')->orderBy('name')->get()->toArray();
    }

    public function findAllActive(): array
    {
        return Language::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function findByCode(string $code): ?array
    {
        $lang = Language::find($code);
        return $lang ? $lang->toArray() : null;
    }

    public function findDefault(): ?array
    {
        $lang = Language::where('is_default', true)->first();
        return $lang ? $lang->toArray() : null;
    }

    public function save(array $data): array
    {
        $code = $data['code'] ?? '';
        $lang = Language::find($code);
        if ($lang) {
            $lang->fill($data);
            $lang->save();
        } else {
            $lang = Language::create($data);
        }
        return $lang->toArray();
    }

    public function setDefault(string $code): bool
    {
        if (!Language::find($code)) {
            return false;
        }
        Language::query()->update(['is_default' => false]);
        Language::where('code', $code)->update(['is_default' => true]);
        return true;
    }

    public function toggleActive(string $code, bool $active): bool
    {
        $lang = Language::find($code);
        if (!$lang) {
            return false;
        }
        $lang->is_active = $active;
        $lang->save();
        return true;
    }

    public function delete(string $code): int
    {
        $lang = Language::find($code);
        if (!$lang) {
            return 0;
        }
        // Suppression explicite : le driver SQLite de ce projet n'active pas
        // PRAGMA foreign_keys, donc onDelete('cascade') n'est pas appliqué en pratique.
        Translation::where('language_code', $code)->delete();
        return $lang->delete() ? 1 : 0;
    }
}
