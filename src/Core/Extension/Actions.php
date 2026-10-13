<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\Action;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Services\Log;

/**
 * Liens d'action des bundles dans les emplacements des pages du Core, construits à partir de données :
 * - ceux déclarés par les bundles actifs (point d'extension Bundle::actions(), contrat 1.6) ;
 * - en repli, ceux que le Core affichait jusqu'ici pour un bundle actif qui ne déclare encore rien (salary-report,
 *   resignation-report, shift-swap). Ce repli disparaîtra à l'étape 5 du plan du contrat.
 */
final class Actions
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?BundleManager $bundles = null,
    ) {}

    /** Instance de la requête courante (registre des extensions et bundles actifs), ou aucune action si indisponible. */
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
     * Actions historiques du Core, par slug de bundle.
     *
     * @return array<string, list<Action>>
     */
    public static function legacyBundleActions(): array
    {
        $base = static fn(): string => base_url();
        $salary = static fn(array $c): string => $base() . '/admin/stores/' . (int) $c['store_id'] . '/reports/salary/create'
            . (isset($c['user_id']) ? '?user_id=' . (int) $c['user_id'] : '');
        $resign = static fn(array $c): string => $base() . '/admin/stores/' . (int) $c['store_id'] . '/reports/resignation/create?user_id=' . (int) $c['user_id'];

        return [
            'salary-report' => [
                new Action('salary_report', Action::EMPLOYEE_ROW, 'salary_report', $salary, '💰', permission: 'payroll.generate', order: 10),
                new Action('salary_report', Action::EMPLOYEE_STORE, 'salary_report', $salary, permission: 'payroll.generate', order: 20),
                new Action('salary_report', Action::STORE_REPORT, 'salary_report', static fn(array $c): string => $salary(['store_id' => $c['store_id']]), '💰', Action::PRIMARY, order: 10),
                new Action('salary_report', Action::STORE_REPORT_ROW, 'salary_report', $salary, '💰', order: 10),
            ],
            'resignation-report' => [
                new Action('resign', Action::EMPLOYEE_ROW, 'resign', $resign, '✕', Action::DANGER, 'resignation_reports.create', order: 20),
                new Action('resign', Action::EMPLOYEE_STORE, 'resign', $resign, '', Action::DANGER, 'resignation_reports.create', order: 10),
            ],
            'shift-swap' => [
                new Action('request_swap', Action::SHIFTS_EMPLOYEE, 'request_swap', static fn(): string => route_url('employee.swaps.create'), '⇄', Action::PRIMARY, order: 10),
            ],
        ];
    }

    /**
     * Actions de l'emplacement : bundles actifs déclarants, repli des bundles actifs non déclarants ; triées par ordre.
     * Une clé déjà présente dans l'emplacement n'est pas reprise.
     *
     * @return list<Action>
     */
    public function inSlot(string $slot): array
    {
        $declaredBySlug = $this->registry?->actionsByBundle() ?? [];

        $all = [];
        foreach (self::legacyBundleActions() as $slug => $legacy) {
            if ($this->bundles?->isActive($slug) && !isset($declaredBySlug[$slug])) {
                array_push($all, ...$legacy);
            }
        }
        foreach ($declaredBySlug as $declared) {
            array_push($all, ...$declared);
        }

        $actions = [];
        foreach ($all as $action) {
            if ($action->slot === $slot) {
                $actions[$action->key] ??= $action;
            }
        }
        $actions = array_values($actions);
        usort($actions, static fn(Action $a, Action $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);
        return $actions;
    }

    /**
     * Liens prêts à afficher pour cet emplacement et ce contexte. Une action dont l'adresse lève une exception est
     * retirée (journalisée).
     *
     * @param array<string, mixed>     $context
     * @param callable(string): bool   $can
     * @param callable(?string): bool  $feat
     * @return list<array{key: string, href: string, label: string, icon: string, style: string}>
     */
    public function links(string $slot, array $context, callable $can, ?callable $feat = null): array
    {
        $links = [];
        foreach ($this->inSlot($slot) as $action) {
            if (($action->permission !== null && !$can($action->permission))
                || ($action->storeFeature !== null && $feat !== null && !$feat($action->storeFeature))) {
                continue;
            }
            try {
                $href = ($action->url)($context);
            } catch (\Throwable $e) {
                try {
                    Log::warning('bundle_extension_failed', ['point' => 'actions', 'key' => $action->key, 'error' => $e->getMessage()]);
                } catch (\Throwable) {
                }
                continue;
            }
            if (!is_string($href) || $href === '') {
                continue;
            }
            $links[] = ['key' => $action->key, 'href' => $href, 'label' => __($action->label), 'icon' => $action->icon, 'style' => $action->style];
        }
        return $links;
    }
}
