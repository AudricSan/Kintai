<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\AppSettingsRepositoryInterface;

final class AppSettingsService
{
    private array $cache = [];

    public function __construct(private readonly AppSettingsRepositoryInterface $repo)
    {
        $this->cache = $repo->all();
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->cache[$key] ?? $default;
    }

    public function setMany(array $settings): void
    {
        $this->repo->setMany($settings);
        foreach ($settings as $k => $v) {
            $this->cache[(string) $k] = (string) $v;
        }
    }

    // ── Accesseurs typés ──────────────────────────────────────────────────────

    /** Nom de l'entreprise affiché sous "Kintai" dans la sidebar et la page de connexion. */
    public function subtitle(): string
    {
        return $this->get('app_subtitle');
    }

    /** Message affiché sur la page de connexion (maintenance, annonces, etc.). */
    public function loginNotice(): string
    {
        return $this->get('app_login_notice');
    }

    /** E-mail de support affiché aux utilisateurs (pages d'erreur, footer). */
    public function supportEmail(): string
    {
        return $this->get('app_support_email');
    }

    // ── Backup ─────────────────────────────────────────────────────────────────

    /** Nombre maximal de sauvegardes à conserver (0 = illimité). */
    public function backupMaxKeep(): int
    {
        return max(0, (int) $this->get('backup_max_keep', '0'));
    }

    /** Sauvegarde automatique activée (cron). */
    public function backupAutoEnabled(): bool
    {
        return $this->get('backup_auto_enabled', '1') === '1';
    }
}
