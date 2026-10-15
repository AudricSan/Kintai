<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\LicenseServiceProvider;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;

/**
 * Assistant de configuration qui suit l'installateur (public/install.php) : étape « Licence » (facultative ; sans
 * licence, plan gratuit à 4 bundles), puis étape « Bundles » (choix limité par la licence, téléchargés depuis le
 * catalogue sur le canal du Core et activés). Tant qu'il n'est pas terminé ou explicitement passé, l'Owner voit un
 * bandeau qui y ramène.
 *
 * L'état vit dans app_settings : une instance installée avant l'assistant n'a pas la clé et n'est pas concernée.
 */
final class SetupWizard
{
    public const PENDING_KEY = 'setup_wizard_pending';
    public const STEP_KEY = 'setup_wizard_step';
    public const STEP_LICENSE = 'license';
    public const STEP_BUNDLES = 'bundles';

    /**
     * @param \Closure $catalog   fn(string $channel): list<array{slug: string, name: string, description: string,
     *                            version: string, repository_url: string, registry_url: string}> — bundles proposés
     * @param \Closure $installer fn(array $entry): ?string — installe un bundle du catalogue ; null si réussi, sinon
     *                            le motif de l'échec
     */
    public function __construct(
        private readonly AppSettingsRepositoryInterface $settings,
        private readonly \Closure $catalog,
        private readonly \Closure $installer,
        private readonly BundleActivationService $activation,
        private readonly PlanLimitService $planLimits,
    ) {}

    /**
     * Appelé par l'installateur sur une instance neuve : aucun bundle actif, assistant à l'étape « Licence », bundles
     * suivis sur le même canal que le Core.
     */
    public static function start(AppSettingsRepositoryInterface $settings, string $coreChannel): void
    {
        $settings->setMany([
            LicenseServiceProvider::SETTINGS_KEY => '[]',
            'bundle_update_channel'              => $coreChannel,
            self::PENDING_KEY                    => '1',
            self::STEP_KEY                       => self::STEP_LICENSE,
        ]);
    }

    public function isPending(): bool
    {
        return $this->settings->get(self::PENDING_KEY) === '1';
    }

    public function step(): string
    {
        return $this->settings->get(self::STEP_KEY) === self::STEP_BUNDLES ? self::STEP_BUNDLES : self::STEP_LICENSE;
    }

    /** Étape « Licence » terminée (licence activée ou passée). */
    public function licenseDone(): void
    {
        $this->settings->set(self::STEP_KEY, self::STEP_BUNDLES);
    }

    /** Assistant terminé (ou passé) : plus de bandeau. */
    public function finish(): void
    {
        $this->settings->setMany([self::PENDING_KEY => '0', self::STEP_KEY => self::STEP_BUNDLES]);
    }

    /** Nombre de bundles que la licence permet d'activer, ou null si illimité. */
    public function maxBundles(): ?int
    {
        return $this->planLimits->maxActiveBundles();
    }

    /** @return list<array{slug: string, name: string, description: string, version: string, repository_url: string, registry_url: string}> */
    public function availableBundles(string $channel): array
    {
        return ($this->catalog)($channel);
    }

    /**
     * Installe et active les bundles choisis. Un choix hors catalogue ou au-delà du quota est refusé en entier ;
     * un échec d'installation n'empêche pas les autres (il est rapporté).
     *
     * @param list<string> $slugs
     * @return array{error: ?string, installed: list<string>, failed: array<string, string>, inactive: list<string>}
     */
    public function install(array $slugs, string $channel): array
    {
        $result = ['error' => null, 'installed' => [], 'failed' => [], 'inactive' => []];
        $bySlug = [];
        foreach ($this->availableBundles($channel) as $entry) {
            $bySlug[$entry['slug']] = $entry;
        }
        $slugs = array_values(array_unique($slugs));
        if (array_diff($slugs, array_keys($bySlug)) !== []) {
            $result['error'] = 'setup_bundles_unknown';
            return $result;
        }
        $max = $this->maxBundles();
        if ($max !== null && count($slugs) > $max) {
            $result['error'] = 'setup_bundles_too_many';
            return $result;
        }

        foreach ($slugs as $slug) {
            try {
                $failure = ($this->installer)($bySlug[$slug]);
            } catch (\Throwable $e) {
                $failure = $e->getMessage();
            }
            if ($failure !== null) {
                $result['failed'][$slug] = $failure;
                continue;
            }
            $result['installed'][] = $slug;
            if ($this->activation->activateIfRoom($slug) === BundleActivationService::QUOTA_FULL) {
                $result['inactive'][] = $slug;
            }
        }
        return $result;
    }
}
