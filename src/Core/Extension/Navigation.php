<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\NavItem;
use kintai\Core\BundleManager;
use kintai\Core\Container;

/**
 * Navigation de la barre du haut et des préférences de navigation, construite à partir de données :
 * - les entrées du Core (planning, personnel, rapports du Core, système) ;
 * - les entrées déclarées par les bundles actifs (point d'extension Bundle::navigation(), contrat 1.1) ;
 * - en repli, les entrées historiques d'un bundle actif qui ne déclare encore rien (bundles d'avant le contrat
 *   1.1). Ce repli disparaîtra quand tous les bundles déclareront leur navigation (étape 5 du plan du contrat).
 *
 * Une même clé sert à la barre du haut et aux préférences (« masquer cette entrée ») pour tous les rôles.
 */
final class Navigation
{
    /** Entrées que l'utilisateur ne peut pas masquer. */
    public const NOT_HIDEABLE = ['settings'];

    /**
     * Anciennes clés de préférences employé (onglet « navigation » du profil) : elles n'ont jamais correspondu aux
     * clés lues par la barre du haut, donc les masquer n'avait aucun effet. Remplacées par les clés uniques.
     */
    public const LEGACY_EMPLOYEE_KEYS = ['my_planning' => 'shifts', 'timeclock' => 'timeclocks', 'my_timeoff' => 'timeoff'];

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
     * Toutes les entrées : Core, bundles déclarants, repli des bundles actifs non déclarants ; triées par section
     * puis par ordre. Une clé déjà présente n'est pas reprise.
     *
     * @return list<NavItem>
     */
    public function items(): array
    {
        $items = self::coreItems();
        $declared = $this->registry?->navigation() ?? [];
        $declaring = $this->registry?->bundlesDeclaringNavigation() ?? [];

        foreach (self::legacyBundleItems() as $slug => $legacy) {
            if ($this->bundles?->isActive($slug) && !in_array($slug, $declaring, true)) {
                array_push($items, ...$legacy);
            }
        }
        array_push($items, ...$declared);

        $unique = [];
        foreach ($items as $item) {
            $unique[$item->key] ??= $item;
        }
        $items = array_values($unique);
        usort($items, static fn(NavItem $a, NavItem $b): int =>
            [array_search($a->section, NavItem::SECTIONS, true), $a->order, $a->key]
            <=> [array_search($b->section, NavItem::SECTIONS, true), $b->order, $b->key]);

        return $items;
    }

    /**
     * Entrées visibles pour la requête courante, par section, prêtes à afficher.
     *
     * @param array{
     *     isManager: bool, path: string, routeVisible: \Closure, feat: \Closure, hidden: list<string>,
     *     routeUrl: \Closure, translate: \Closure, view: array<string, mixed>
     * } $ctx
     * @return array<string, list<array{key: string, icon: string, links: list<array{href: string, label: string, active: bool}>, badge: int}>>
     */
    public static function visible(array $items, array $ctx): array
    {
        // Variables fournies aux fonctions de liens (contrat : route_visible, route_url, path, isOwner, BASE_URL, et celles de la vue).
        $view = ['route_visible' => $ctx['routeVisible'], 'route_url' => $ctx['routeUrl'], 'path' => $ctx['path']] + $ctx['view'];

        $sections = [];
        foreach ($items as $item) {
            if (in_array($item->key, $ctx['hidden'], true) && !in_array($item->key, self::NOT_HIDEABLE, true)) {
                continue;
            }
            if ($item->storeFeature !== null && !($ctx['feat'])($item->storeFeature)) {
                continue;
            }
            $route = $item->routeFor($ctx['isManager']);
            if ($route === null) {
                continue;
            }

            try {
                $links = $item->linksFor($view, $ctx['isManager']);
                if ($links === null) {
                    if (!($ctx['routeVisible'])($route)) {
                        continue;
                    }
                    $links = [[
                        'href'   => ($ctx['routeUrl'])($route),
                        'label'  => ($ctx['translate'])($item->labelFor($ctx['isManager'])),
                        'active' => $item->isActive($ctx['path']),
                    ]];
                }
            } catch (\Throwable) {
                // Route d'un bundle introuvable, fonction de liens défaillante : l'entrée disparaît, la page reste.
                continue;
            }
            if ($links === []) {
                continue;
            }

            $sections[$item->section][] = [
                'key'   => $item->key,
                'icon'  => $item->icon,
                'links' => $links,
                'badge' => $item->badge !== null ? (int) ($view[$item->badge] ?? 0) : 0,
            ];
        }
        return $sections;
    }

    /**
     * Clés masquables par section, pour la page de préférences du rôle donné.
     *
     * @param \Closure(string): bool      $routeVisible
     * @param \Closure(string): bool      $feat
     * @param array<string, mixed>        $view  Variables de la page, pour les entrées à liens multiples
     * @return array<string, list<string>>
     */
    public function hideableKeys(bool $isManager, \Closure $routeVisible, \Closure $feat, array $view = []): array
    {
        $view = ['route_visible' => $routeVisible, 'route_url' => static fn(string $r): string => route_url($r), 'path' => ''] + $view;
        $sections = [];
        foreach ($this->items() as $item) {
            $route = $item->routeFor($isManager);
            // Les liens directs (section MAIN) et les entrées non masquables ne figurent pas dans les préférences.
            if ($route === null || $item->section === NavItem::MAIN || in_array($item->key, self::NOT_HIDEABLE, true)) {
                continue;
            }
            if ($item->storeFeature !== null && !$feat($item->storeFeature)) {
                continue;
            }
            if ($item->links === null && !$routeVisible($route)) {
                continue;
            }
            if ($item->links !== null) {
                try {
                    if ($item->linksFor($view, $isManager) === []) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
            $sections[$item->section][] = $item->key;
        }
        return $sections;
    }

    /**
     * Libellé (clé de traduction) d'une entrée pour la page de préférences.
     */
    public function labelOf(string $key, bool $isManager): string
    {
        foreach ($this->items() as $item) {
            if ($item->key === $key) {
                return $item->labelFor($isManager);
            }
        }
        return $key;
    }

    /** @param list<string> $hidden @return list<string> */
    public static function migrateLegacyKeys(array $hidden): array
    {
        return array_values(array_unique(array_map(
            static fn(string $k): string => self::LEGACY_EMPLOYEE_KEYS[$k] ?? $k,
            array_filter($hidden, 'is_string'),
        )));
    }

    /** @return list<NavItem> */
    public static function coreItems(): array
    {
        $settingsPaths = ['/admin/owner-settings', '/admin/backup', '/admin/update', '/admin/languages', '/admin/bundles', '/admin/license'];

        return [
            new NavItem('shifts', NavItem::PLANNING, 'shifts', 'admin.shifts.timeline', 'employee.shifts.day',
                icon: 'calendar', employeeLabel: 'my_planning', activePaths: ['/admin/shifts', '/employee/shifts'],
                storeFeature: 'shifts', order: 10),
            new NavItem('calendar', NavItem::PLANNING, 'calendar', 'admin.shifts.calendar', 'admin.shifts.calendar',
                icon: 'calendar', activePaths: ['/admin/shifts/calendar'], order: 20),
            new NavItem('shift_types', NavItem::PLANNING, 'shift_types', 'admin.shift_types', 'admin.shift_types',
                icon: 'tag', activePaths: ['/admin/shift-types'], order: 30),
            new NavItem('users', NavItem::HR, 'staff', 'admin.users', 'admin.users',
                icon: 'users', activePaths: ['/admin/users'], order: 10),
            new NavItem('stores', NavItem::HR, 'stores', 'admin.stores', 'admin.stores',
                icon: 'store', activePaths: ['/admin/stores'], order: 20),
            // Lien direct vers le rapport du magasin quand l'utilisateur n'en gère qu'un, sinon vers la liste.
            new NavItem('employee_report', NavItem::REPORTS, 'employee_report', 'admin.stores.employee_report', 'admin.stores.employee_report',
                icon: 'chart', order: 20,
                links: static function (array $view, bool $isManager): array {
                    if (!($view['route_visible'])('admin.stores.employee_report')) {
                        return [];
                    }
                    $managed = $view['managed_store_ids'] ?? null;
                    $base = (string) ($view['BASE_URL'] ?? '');
                    $href = is_array($managed) && count($managed) === 1
                        ? $base . '/admin/stores/' . store_segment((int) $managed[0]) . '/employee-report'
                        : $base . '/admin/stores';
                    return [['href' => $href, 'label' => __('employee_report'), 'active' => str_contains((string) ($view['path'] ?? ''), '/employee-report')]];
                }),
            new NavItem('audit_log', NavItem::SYSTEM, 'activity_log', 'admin.activity', 'admin.activity',
                icon: 'history', activePaths: ['/admin/activity'], order: 10),
            // Réglages de l'instance : Owner uniquement (OwnerOnlyMiddleware, pas une permission délégable).
            new NavItem('settings', NavItem::SYSTEM, 'settings', 'admin.owner_settings', 'admin.owner_settings',
                icon: 'gear', activePaths: $settingsPaths, order: 20,
                links: static function (array $view, bool $isManager) use ($settingsPaths): array {
                    if (empty($view['isOwner'])) {
                        return [];
                    }
                    $path = (string) ($view['path'] ?? '');
                    $active = array_filter($settingsPaths, static fn(string $p): bool => str_starts_with($path, $p)) !== [];
                    return [['href' => ($view['route_url'])('admin.owner_settings'), 'label' => __('settings'), 'active' => $active]];
                }),
        ];
    }

    /**
     * Entrées historiques des bundles officiels, utilisées seulement pour un bundle actif qui ne déclare pas encore sa
     * navigation (versions d'avant le contrat 1.1).
     *
     * @return array<string, list<NavItem>>
     */
    public static function legacyBundleItems(): array
    {
        return [
            'team-directory' => [
                new NavItem('team_directory', NavItem::MAIN, 'team_directory', 'team.index', 'team.index',
                    icon: 'users', activePaths: ['/team'], order: 10),
            ],
            'timeclock' => [
                new NavItem('timeclocks', NavItem::PLANNING, 'timeclocks', 'admin.timeclocks', 'employee.timeclock',
                    icon: 'clock', employeeLabel: 'timeclock', activePaths: ['/admin/timeclocks', '/employee/timeclock'],
                    storeFeature: 'timeclock', order: 40, mobile: true),
            ],
            'timeoff' => [
                new NavItem('timeoff', NavItem::REQUESTS, 'timeoff', 'admin.timeoff', 'employee.timeoff',
                    icon: 'leaf', employeeLabel: 'my_timeoff', activePaths: ['/admin/timeoff', '/employee/timeoff'],
                    storeFeature: 'timeoff', order: 10, mobile: true),
            ],
            'shift-swap' => [
                new NavItem('swaps', NavItem::REQUESTS, 'swaps', 'admin.swap_requests', 'employee.swaps',
                    icon: 'arrows', activePaths: ['/admin/swap-requests', '/employee/swaps'],
                    storeFeature: 'swaps', order: 20, mobile: true),
            ],
            'shift-claim' => [
                new NavItem('open_shifts', NavItem::REQUESTS, 'open_shifts', 'admin.open_shifts', 'employee.open_shifts',
                    icon: 'plus', activePaths: ['/admin/open-shifts', '/employee/open-shifts'],
                    storeFeature: 'open_shifts', order: 30, mobile: true),
            ],
            'messaging' => [
                new NavItem('messages', NavItem::REQUESTS, 'messages', 'admin.messages', 'employee.messages',
                    icon: 'message', activePaths: ['/admin/messages', '/employee/messages'],
                    storeFeature: 'messages', badge: 'unread_messages_count', order: 40, mobile: true),
            ],
            'hiring-report' => [
                new NavItem('hiring_report', NavItem::REPORTS, 'hiring_reports', 'admin.reports.hiring', 'admin.reports.hiring',
                    icon: 'person', activePaths: ['*/reports/hiring'], order: 10),
            ],
            'daily-report' => [
                // Avec la permission globale : un lien vers tous les rapports ; sinon, un lien par magasin où
                // l'utilisateur rédige les rapports ($daily_report_staff_stores).
                new NavItem('daily_reports', NavItem::REPORTS, 'daily_reports', 'admin.daily_reports.all', 'admin.daily_reports.all',
                    icon: 'chart', storeFeature: 'daily_reports', order: 30, mobile: true,
                    links: static function (array $view, bool $isManager): array {
                        $path = (string) ($view['path'] ?? '');
                        if (($view['route_visible'])('admin.daily_reports.all')) {
                            return [['href' => ($view['route_url'])('admin.daily_reports.all'), 'label' => __('daily_reports'), 'active' => str_contains($path, '/daily-reports')]];
                        }
                        $stores = (array) ($view['daily_report_staff_stores'] ?? []);
                        $links = [];
                        foreach ($stores as $store) {
                            $id = (int) ($store['id'] ?? 0);
                            $links[] = [
                                'href'   => ($view['route_url'])('admin.stores') . '/' . $id . '/daily-reports',
                                'label'  => __('daily_reports') . (count($stores) > 1 ? ' — ' . (string) ($store['name'] ?? '') : ''),
                                'active' => str_contains($path, '/stores/' . $id . '/daily-reports'),
                            ];
                        }
                        return $links;
                    }),
            ],
            'resignation-report' => [
                new NavItem('resignation_report', NavItem::REPORTS, 'resignation_report', 'admin.reports.resignation', 'admin.reports.resignation',
                    icon: 'exit', activePaths: ['*/reports/resignation'], order: 40),
            ],
            'salary-report' => [
                new NavItem('salary_report', NavItem::REPORTS, 'salary_report', 'admin.reports.salary', 'admin.reports.salary',
                    icon: 'money', activePaths: ['*/reports/salary'], order: 50),
            ],
            'store-photos' => [
                new NavItem('photos', NavItem::REPORTS, 'photos', 'admin.photos.index', 'admin.photos.index',
                    icon: 'camera', activePaths: ['/admin/photos'], storeFeature: 'photos', order: 60),
            ],
        ];
    }
}
