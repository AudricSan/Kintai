<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\DashboardWidget;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Services\WidgetGuard;
use kintai\UI\ViewRenderer;

/**
 * Widgets des tableaux de bord (gérant et employé), construits à partir de données :
 * - ceux du Core, rendus par la vue du tableau de bord ;
 * - ceux déclarés par les bundles actifs (point d'extension Bundle::dashboardWidgets(), contrat 1.4), rendus ici à
 *   partir de la vue du bundle et affichés après ceux du Core.
 *
 * Les widgets historiques que le Core affiche pour un bundle (demandes de congé, échanges, pointages) restent
 * affichés tant que ce bundle ne déclare aucun widget ; dès qu'il en déclare, seuls les siens comptent. Un bundle peut
 * reprendre la clé de son widget historique pour conserver les préférences des utilisateurs.
 */
final class DashboardWidgets
{
    /** Widgets historiques du Core qui appartiennent à un bundle : public => clé => slug du bundle. */
    public const LEGACY_BUNDLE_WIDGETS = [
        DashboardWidget::MANAGER => [
            'pending_timeoff'  => 'timeoff',
            'timeclocks_today' => 'timeclock',
        ],
        DashboardWidget::EMPLOYEE => [
            'timeclock'       => 'timeclock',
            'pending_timeoff' => 'timeoff',
        ],
    ];

    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?BundleManager $bundles = null,
    ) {}

    /** Instance de la requête courante (registre des extensions et bundles actifs), ou Core seul si indisponible. */
    public static function current(): self
    {
        try {
            $container = Container::getInstance();
            return new self(
                $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null,
                $container->has(BundleManager::class) ? $container->make(BundleManager::class) : null,
            );
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Widgets déclarés par les bundles actifs pour ce public, triés par ordre. Une clé déjà prise (widget du Core qui
     * n'est pas l'historique du bundle déclarant, ou widget d'un autre bundle) est ignorée.
     *
     * @param list<string> $coreKeys Widgets du Core pour ce public
     * @return list<DashboardWidget>
     */
    public function declared(string $audience, array $coreKeys): array
    {
        $legacy = self::LEGACY_BUNDLE_WIDGETS[$audience] ?? [];
        $widgets = [];
        foreach ($this->registry?->dashboardWidgetsByBundle() ?? [] as $slug => $declared) {
            foreach ($declared as $widget) {
                if ($widget->audience !== $audience || isset($widgets[$widget->key])) {
                    continue;
                }
                if (in_array($widget->key, $coreKeys, true) && ($legacy[$widget->key] ?? null) !== $slug) {
                    continue;
                }
                $widgets[$widget->key] = $widget;
            }
        }
        $widgets = array_values($widgets);
        usort($widgets, static fn(DashboardWidget $a, DashboardWidget $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);
        return $widgets;
    }

    /**
     * Clés de tous les widgets proposés pour ce public : ceux du Core (sans les historiques des bundles déclarants),
     * puis ceux déclarés par les bundles.
     *
     * @param list<string> $coreKeys
     * @return list<string>
     */
    public function keys(string $audience, array $coreKeys): array
    {
        $replaced = $this->replacedLegacyKeys($audience);
        $keys = array_values(array_filter($coreKeys, static fn(string $k): bool => !in_array($k, $replaced, true)));
        foreach ($this->declared($audience, $coreKeys) as $widget) {
            if (!in_array($widget->key, $keys, true)) {
                $keys[] = $widget->key;
            }
        }
        return $keys;
    }

    /**
     * Widgets historiques du Core que le Core ne rend plus lui-même, parce que leur bundle déclare ses widgets.
     *
     * @return list<string>
     */
    public function replacedLegacyKeys(string $audience): array
    {
        $declaring = $this->registry?->bundlesDeclaringDashboardWidgets() ?? [];
        return array_keys(array_filter(
            self::LEGACY_BUNDLE_WIDGETS[$audience] ?? [],
            static fn(string $slug): bool => in_array($slug, $declaring, true)
        ));
    }

    /**
     * Rend les widgets de bundles activés et visibles. Chaque widget est isolé : une exception dans ses données ou sa
     * vue le retire (journalisé), les autres s'affichent.
     *
     * @param list<string>             $coreKeys
     * @param list<string>             $enabledKeys Widgets activés par l'utilisateur
     * @param array<string, mixed>     $context     Voir DashboardWidget::$data
     * @param callable(string): bool   $can         Permission accordée ?
     * @param callable(?string): bool  $feat        Fonctionnalité par magasin active ?
     * @return list<array{key: string, html: string}>
     */
    public function render(string $audience, array $coreKeys, array $enabledKeys, array $context, ViewRenderer $view, callable $can, callable $feat): array
    {
        $rendered = [];
        foreach ($this->declared($audience, $coreKeys) as $widget) {
            if (!in_array($widget->key, $enabledKeys, true) || !$this->isVisible($widget, $can, $feat)) {
                continue;
            }
            $html = WidgetGuard::run($widget->key, static function () use ($widget, $context, $view): string {
                $data = $widget->data !== null ? ($widget->data)($context) : [];
                if (!is_array($data)) {
                    throw new \UnexpectedValueException('les données du widget doivent être un tableau');
                }
                $level = ob_get_level();
                try {
                    return $view->renderPartial($widget->view, $data + ['widget_key' => $widget->key]);
                } finally {
                    // Une vue qui lève une exception laisse son tampon ouvert : on le referme sans l'afficher.
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                }
            }, '');
            if ($html !== '') {
                $rendered[] = ['key' => $widget->key, 'html' => $html];
            }
        }
        return $rendered;
    }

    /**
     * Libellés (traduits), permissions et fonctionnalités des widgets de bundles, pour le panneau « Personnaliser ».
     *
     * @param list<string> $coreKeys
     * @return array{labels: array<string, string>, permissions: array<string, string>, features: array<string, string>}
     */
    public function meta(string $audience, array $coreKeys): array
    {
        $meta = ['labels' => [], 'permissions' => [], 'features' => []];
        foreach ($this->declared($audience, $coreKeys) as $widget) {
            $meta['labels'][$widget->key] = __($widget->label);
            if ($widget->permission !== null) {
                $meta['permissions'][$widget->key] = $widget->permission;
            }
            if ($widget->storeFeature !== null) {
                $meta['features'][$widget->key] = $widget->storeFeature;
            }
        }
        return $meta;
    }

    /**
     * Préférences à enregistrer : les widgets cochés parmi ceux proposés, plus ceux déjà enregistrés mais pas proposés
     * en ce moment (bundle désactivé), pour qu'ils reviennent à sa réactivation.
     *
     * @param list<string>      $offered
     * @param list<string>      $checked
     * @param list<string>|null $previous
     * @return list<string>
     */
    public static function mergePreferences(array $offered, array $checked, ?array $previous): array
    {
        $keep = array_filter((array) $previous, static fn($k): bool => is_string($k) && !in_array($k, $offered, true)
            && preg_match('/^[a-z][a-z0-9_]{1,40}$/', $k) === 1);
        return array_values(array_unique(array_merge(array_values(array_intersect($offered, $checked)), array_values($keep))));
    }

    /** @param callable(string): bool $can @param callable(?string): bool $feat */
    private function isVisible(DashboardWidget $widget, callable $can, callable $feat): bool
    {
        return ($widget->permission === null || $can($widget->permission))
            && ($widget->storeFeature === null || $feat($widget->storeFeature));
    }
}
