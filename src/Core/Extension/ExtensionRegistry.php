<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\ProfileSection;
use kintai\Contract\Extension\StoreFeature;
use kintai\Contract\Provider\AbsenceProvider;
use kintai\Contract\Provider\AttendanceProvider;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Contract\Provider\SalesProvider;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Services\Log;

/**
 * Collecte les contributions des bundles actifs aux points d'extension du Core (contrat 1.1, étape 2 du plan).
 *
 * Chaque bundle est interrogé isolément : une exception, ou une valeur qui n'est pas du type attendu, retire sa
 * contribution et l'inscrit au journal, sans casser la page (même principe que WidgetGuard pour les widgets).
 * Les résultats sont mis en cache pour la durée de la requête.
 */
final class ExtensionRegistry
{
    /** @var list<NavItem>|null */
    private ?array $navigation = null;

    /** @var array<string, list<string>>|null */
    private ?array $permissions = null;

    /** @var list<StoreFeature>|null */
    private ?array $storeFeatures = null;

    /** @var array<string, list<DashboardWidget>>|null */
    private ?array $dashboardWidgets = null;

    /** @var array<string, array<string, \Closure>>|null */
    private ?array $viewVariables = null;

    /** @var array<string, list<Action>>|null */
    private ?array $actions = null;

    /** @var array<string, string>|null */
    private ?array $notificationTypes = null;

    /** @var array<string, list<class-string>>|null */
    private ?array $cronJobs = null;

    /** @var array<string, list<PendingRequestsProvider>>|null */
    private ?array $pendingRequestsProviders = null;

    /** @var array<string, list<AbsenceProvider>>|null */
    private ?array $absenceProviders = null;

    /** @var array<string, list<AttendanceProvider>>|null */
    private ?array $attendanceProviders = null;

    /** @var array<string, list<ProfileSection>>|null */
    private ?array $profileSections = null;

    /** @var array<string, array<class-string, callable>>|null */
    private ?array $eventListeners = null;

    /** @var array<string, list<SalesProvider>>|null */
    private ?array $salesProviders = null;

    /** @var list<string>|null */
    private ?array $permissionDeclarers = null;

    public function __construct(private readonly BundleManager $bundles) {}

    /**
     * Entrées de navigation des bundles actifs, triées par section puis par ordre. Une clé déjà fournie par un
     * autre bundle est ignorée (la première gagne).
     *
     * @return list<NavItem>
     */
    public function navigation(): array
    {
        if ($this->navigation !== null) {
            return $this->navigation;
        }

        $items = [];
        $seen = [];
        foreach ($this->collect('navigation', NavItem::class) as $item) {
            if (isset($seen[$item->key])) {
                Log::warning('bundle_extension_duplicate_key', ['point' => 'navigation', 'key' => $item->key]);
                continue;
            }
            $seen[$item->key] = true;
            $items[] = $item;
        }

        usort($items, static fn(NavItem $a, NavItem $b): int =>
            [array_search($a->section, NavItem::SECTIONS, true), $a->order, $a->key]
            <=> [array_search($b->section, NavItem::SECTIONS, true), $b->order, $b->key]);

        return $this->navigation = $items;
    }

    /**
     * Slugs des bundles actifs qui déclarent au moins une entrée de navigation : pour eux, le Core n'affiche plus ses
     * entrées de repli (bundles d'avant le contrat 1.1, qui ne déclarent rien).
     *
     * @return list<string>
     */
    public function bundlesDeclaringNavigation(): array
    {
        $slugs = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            if ($this->contributionsOf($bundle, 'navigation', NavItem::class) !== []) {
                $slugs[] = $bundle->getName();
            }
        }
        return $slugs;
    }

    /**
     * Catégories de permissions déclarées par les bundles actifs (catégorie => actions). Une catégorie ou une action
     * mal formée, ou une catégorie déjà déclarée (par le Core ou un autre bundle), est ignorée et journalisée.
     *
     * @param list<string> $reserved Catégories du Core, jamais redéfinissables
     * @return array<string, list<string>>
     */
    public function permissions(array $reserved = []): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $categories = [];
        $declarers = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            try {
                $declared = $bundle->permissions();
            } catch (\Throwable $e) {
                Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => 'permissions', 'error' => $e->getMessage()]);
                continue;
            }
            if (!is_array($declared) || $declared === []) {
                continue;
            }
            $declarers[] = $bundle->getName();
            foreach ($declared as $category => $actions) {
                $valid = is_string($category) && preg_match('/^[a-z][a-z0-9_]{1,40}$/', $category) === 1 && is_array($actions)
                    && $actions !== [] && array_filter($actions, static fn($a): bool => !is_string($a) || preg_match('/^[a-z][a-z0-9_]{1,40}$/', $a) !== 1) === [];
                if (!$valid || in_array($category, $reserved, true) || isset($categories[$category])) {
                    Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => 'permissions', 'category' => is_string($category) ? $category : null]);
                    continue;
                }
                $categories[$category] = array_values(array_unique($actions));
            }
        }

        $this->permissionDeclarers = $declarers;
        return $this->permissions = $categories;
    }

    /** Slugs des bundles actifs qui déclarent leurs permissions (leurs catégories historiques ne s'appliquent plus). */
    public function bundlesDeclaringPermissions(): array
    {
        $this->permissions();
        return $this->permissionDeclarers ?? [];
    }

    /**
     * Fonctionnalités par magasin déclarées par les bundles actifs. Une clé déjà fournie par un autre bundle est
     * ignorée (la première gagne).
     *
     * @return list<StoreFeature>
     */
    public function storeFeatures(): array
    {
        if ($this->storeFeatures !== null) {
            return $this->storeFeatures;
        }

        $features = [];
        foreach ($this->collect('storeFeatures', StoreFeature::class) as $feature) {
            if (isset($features[$feature->key])) {
                Log::warning('bundle_extension_duplicate_key', ['point' => 'storeFeatures', 'key' => $feature->key]);
                continue;
            }
            $features[$feature->key] = $feature;
        }
        return $this->storeFeatures = array_values($features);
    }

    /** Slugs des bundles actifs qui déclarent leurs fonctionnalités par magasin (leur repli historique ne s'applique plus). */
    public function bundlesDeclaringStoreFeatures(): array
    {
        $slugs = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            if ($this->contributionsOf($bundle, 'storeFeatures', StoreFeature::class) !== []) {
                $slugs[] = $bundle->getName();
            }
        }
        return $slugs;
    }

    /**
     * Widgets de tableau de bord déclarés par les bundles actifs, par slug de bundle (les doublons de clé sont
     * départagés par kintai\Core\Extension\DashboardWidgets, qui connaît les widgets du Core).
     *
     * @return array<string, list<DashboardWidget>>
     */
    public function dashboardWidgetsByBundle(): array
    {
        if ($this->dashboardWidgets !== null) {
            return $this->dashboardWidgets;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $widgets = $this->contributionsOf($bundle, 'dashboardWidgets', DashboardWidget::class);
            if ($widgets !== []) {
                $bySlug[$bundle->getName()] = $widgets;
            }
        }
        return $this->dashboardWidgets = $bySlug;
    }

    /** Slugs des bundles actifs qui déclarent des widgets (leurs widgets historiques ne sont plus rendus par le Core). */
    public function bundlesDeclaringDashboardWidgets(): array
    {
        return array_keys($this->dashboardWidgetsByBundle());
    }

    /**
     * Variables de vue déclarées par les bundles actifs, par slug de bundle : nom => fonction. Un nom mal formé ou
     * une valeur qui n'est pas une fonction est ignoré et journalisé.
     *
     * @return array<string, array<string, \Closure>>
     */
    public function viewVariablesByBundle(): array
    {
        if ($this->viewVariables !== null) {
            return $this->viewVariables;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            try {
                $declared = $bundle->viewVariables();
            } catch (\Throwable $e) {
                Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => 'viewVariables', 'error' => $e->getMessage()]);
                continue;
            }
            $valid = [];
            foreach (is_array($declared) ? $declared : [] as $name => $resolve) {
                if (is_string($name) && preg_match('/^[a-z][a-z0-9_]{1,60}$/', $name) === 1 && $resolve instanceof \Closure) {
                    $valid[$name] = $resolve;
                } else {
                    Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => 'viewVariables']);
                }
            }
            if ($valid !== []) {
                $bySlug[$bundle->getName()] = $valid;
            }
        }
        return $this->viewVariables = $bySlug;
    }

    /**
     * Liens d'action déclarés par les bundles actifs, par slug de bundle (les doublons de clé dans un emplacement sont
     * départagés par kintai\Core\Extension\Actions).
     *
     * @return array<string, list<Action>>
     */
    public function actionsByBundle(): array
    {
        if ($this->actions !== null) {
            return $this->actions;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $actions = $this->contributionsOf($bundle, 'actions', Action::class);
            if ($actions !== []) {
                $bySlug[$bundle->getName()] = $actions;
            }
        }
        return $this->actions = $bySlug;
    }

    /**
     * Types de notification déclarés par les bundles actifs : type => icône. Un type mal formé, une icône qui n'est pas
     * une chaîne courte ou un type déjà déclaré est ignoré et journalisé.
     *
     * @return array<string, string>
     */
    public function notificationTypes(): array
    {
        if ($this->notificationTypes !== null) {
            return $this->notificationTypes;
        }

        $types = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            try {
                $declared = $bundle->notificationTypes();
            } catch (\Throwable $e) {
                Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => 'notificationTypes', 'error' => $e->getMessage()]);
                continue;
            }
            foreach (is_array($declared) ? $declared : [] as $type => $icon) {
                if (!is_string($type) || preg_match('/^[a-z][a-z0-9_]{1,60}$/', $type) !== 1 || !is_string($icon)
                    || mb_strlen($icon) > 8 || isset($types[$type])) {
                    Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => 'notificationTypes']);
                    continue;
                }
                $types[$type] = $icon;
            }
        }
        return $this->notificationTypes = $types;
    }

    /**
     * Classes de tâches planifiées déclarées par les bundles actifs, par slug de bundle. Une valeur qui n'est pas le
     * nom d'une classe existante implémentant l'interface des tâches est ignorée et journalisée.
     *
     * @return array<string, list<class-string>>
     */
    public function cronJobsByBundle(): array
    {
        if ($this->cronJobs !== null) {
            return $this->cronJobs;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            try {
                $declared = $bundle->cronJobs();
            } catch (\Throwable $e) {
                Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => 'cronJobs', 'error' => $e->getMessage()]);
                continue;
            }
            $valid = [];
            foreach (is_array($declared) ? $declared : [] as $class) {
                if (is_string($class) && class_exists($class) && is_subclass_of($class, \kintai\Core\Cron\CronJobInterface::class)) {
                    $valid[] = $class;
                } else {
                    Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => 'cronJobs']);
                }
            }
            if ($valid !== []) {
                $bySlug[$bundle->getName()] = $valid;
            }
        }
        return $this->cronJobs = $bySlug;
    }

    /**
     * Fournisseurs de demandes en attente déclarés par les bundles actifs, par slug de bundle.
     *
     * @return array<string, list<PendingRequestsProvider>>
     */
    public function pendingRequestsProvidersByBundle(): array
    {
        if ($this->pendingRequestsProviders !== null) {
            return $this->pendingRequestsProviders;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $providers = $this->contributionsOf($bundle, 'pendingRequestsProviders', PendingRequestsProvider::class);
            if ($providers !== []) {
                $bySlug[$bundle->getName()] = $providers;
            }
        }
        return $this->pendingRequestsProviders = $bySlug;
    }

    /**
     * Fournisseurs d'absences déclarés par les bundles actifs, par slug de bundle.
     *
     * @return array<string, list<AbsenceProvider>>
     */
    public function absenceProvidersByBundle(): array
    {
        if ($this->absenceProviders !== null) {
            return $this->absenceProviders;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $providers = $this->contributionsOf($bundle, 'absenceProviders', AbsenceProvider::class);
            if ($providers !== []) {
                $bySlug[$bundle->getName()] = $providers;
            }
        }
        return $this->absenceProviders = $bySlug;
    }

    /**
     * Fournisseurs de pointages déclarés par les bundles actifs, par slug de bundle.
     *
     * @return array<string, list<AttendanceProvider>>
     */
    public function attendanceProvidersByBundle(): array
    {
        if ($this->attendanceProviders !== null) {
            return $this->attendanceProviders;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $providers = $this->contributionsOf($bundle, 'attendanceProviders', AttendanceProvider::class);
            if ($providers !== []) {
                $bySlug[$bundle->getName()] = $providers;
            }
        }
        return $this->attendanceProviders = $bySlug;
    }

    /**
     * Sections du profil déclarées par les bundles actifs, par slug de bundle (les doublons de clé sont départagés par
     * kintai\Core\Extension\ProfileSections).
     *
     * @return array<string, list<ProfileSection>>
     */
    public function profileSectionsByBundle(): array
    {
        if ($this->profileSections !== null) {
            return $this->profileSections;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $sections = $this->contributionsOf($bundle, 'profileSections', ProfileSection::class);
            if ($sections !== []) {
                $bySlug[$bundle->getName()] = $sections;
            }
        }
        return $this->profileSections = $bySlug;
    }

    /**
     * Auditeurs d'événements déclarés par les bundles actifs, par slug de bundle puis par classe d'événement. Seuls
     * les événements du contrat (kintai\Contract\Event\…) sont acceptés.
     *
     * @return array<string, array<class-string, callable>>
     */
    public function eventListenersByBundle(): array
    {
        if ($this->eventListeners !== null) {
            return $this->eventListeners;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            try {
                $declared = $bundle->eventListeners();
            } catch (\Throwable $e) {
                Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => 'eventListeners', 'error' => $e->getMessage()]);
                continue;
            }
            $valid = [];
            foreach (is_array($declared) ? $declared : [] as $event => $listener) {
                if (is_string($event) && str_starts_with($event, 'kintai\\Contract\\Event\\') && class_exists($event) && is_callable($listener)) {
                    $valid[$event] = $listener;
                } else {
                    Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => 'eventListeners']);
                }
            }
            if ($valid !== []) {
                $bySlug[$bundle->getName()] = $valid;
            }
        }
        return $this->eventListeners = $bySlug;
    }

    /**
     * Fournisseurs de ventes journalières déclarés par les bundles actifs, par slug de bundle.
     *
     * @return array<string, list<SalesProvider>>
     */
    public function salesProvidersByBundle(): array
    {
        if ($this->salesProviders !== null) {
            return $this->salesProviders;
        }

        $bySlug = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            $providers = $this->contributionsOf($bundle, 'salesProviders', SalesProvider::class);
            if ($providers !== []) {
                $bySlug[$bundle->getName()] = $providers;
            }
        }
        return $this->salesProviders = $bySlug;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return list<T>
     */
    private function collect(string $point, string $type): array
    {
        $all = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            array_push($all, ...$this->contributionsOf($bundle, $point, $type));
        }
        return $all;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return list<T>
     */
    private function contributionsOf(Bundle $bundle, string $point, string $type): array
    {
        try {
            $values = $bundle->{$point}();
        } catch (\Throwable $e) {
            Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => $point, 'error' => $e->getMessage()]);
            return [];
        }
        if (!is_array($values)) {
            Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => $point]);
            return [];
        }

        $valid = [];
        foreach ($values as $value) {
            if ($value instanceof $type) {
                $valid[] = $value;
            } else {
                Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => $point]);
            }
        }
        return $valid;
    }
}
