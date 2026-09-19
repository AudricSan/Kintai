<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\BundleDiscoveryService;
use kintai\Core\LicenseServiceProvider;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\BundleRegistryRepositoryInterface;
use kintai\Core\Repositories\InstalledBundleRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\BundleInstaller\BundleInstallerService;
use kintai\Core\Services\BundleRegistry\BundleCatalogService;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

/**
 * Gère les registries de bundles ajoutés par l'Owner (façon dépôts d'add-ons
 * Home Assistant), le catalogue agrégé des bundles disponibles et leur
 * installation/mise à jour effective via BundleInstallerService. Distinct de
 * BundleSettingsController, qui active/désactive un bundle déjà présent sur
 * le disque.
 */
final class BundleMarketController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly BundleRegistryRepositoryInterface $registries,
        private readonly BundleCatalogService $catalog,
        private readonly InstalledBundleRepositoryInterface $installedBundles,
        private readonly BundleInstallerService $installer,
        private readonly AuditLogger $auditLogger,
        private readonly AppSettingsRepositoryInterface $appSettings,
        private readonly BundleDiscoveryService $discovery,
    ) {}

    /** GET /admin/bundles/registries */
    public function index(Request $request): Response
    {
        return Response::html($this->view->render('system.bundle-registries', [
            'title'      => __('bundle_registries'),
            'registries' => $this->registries->all(),
            'error'      => $request->query('error'),
            'success'    => $request->query('success'),
        ], 'layout.app'));
    }

    /** POST /admin/bundles/registries */
    public function store(Request $request): Response
    {
        $name = trim((string) $request->post('name', ''));
        $url = trim((string) $request->post('url', ''));

        if ($name === '' || mb_strlen($name) > 150 || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=invalid');
        }

        if ($this->registries->existsByUrl($url)) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=duplicate');
        }

        $created = $this->registries->create($name, $url);

        $this->auditLogger->log($request, 'bundle_registry.created', 'bundle_registry', $created['id'], [
            'name' => $name,
            'url'  => $url,
        ]);

        return Response::redirect($this->base() . '/admin/bundles/registries?success=created');
    }

    /** POST /admin/bundles/registries/{id}/delete */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $registry = $this->registries->find($id);
        if (!$registry) {
            return Response::redirect($this->base() . '/admin/bundles/registries');
        }
        if ($registry['is_official']) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=delete_official_forbidden');
        }

        $this->registries->delete($id);

        $this->auditLogger->log($request, 'bundle_registry.deleted', 'bundle_registry', $id, [
            'name' => $registry['name'],
            'url'  => $registry['url'],
        ]);

        return Response::redirect($this->base() . '/admin/bundles/registries?success=deleted');
    }

    /** GET /admin/bundles/market — catalogue agrégé de tous les registries actifs. */
    public function market(Request $request): Response
    {
        $installed = [];
        foreach ($this->installedBundles->all() as $row) {
            $installed[$row['slug']] = $row['active_version'];
        }

        $entries = [];
        $seenSlugs = [];
        foreach ($this->catalog->listAvailableBundles() as $entry) {
            $latestVersion = $entry->bundle->versions[0] ?? null;
            $installedVersion = $installed[$entry->bundle->slug] ?? null;
            $seenSlugs[$entry->bundle->slug] = true;

            $entries[] = [
                'slug'             => $entry->bundle->slug,
                'name'             => $entry->bundle->name,
                'description'      => $entry->bundle->description,
                'repository_url'   => $entry->bundle->repositoryUrl,
                'versions'         => $entry->bundle->versions,
                'latest_version'   => $latestVersion,
                'registry_name'    => $entry->registryName,
                'registry_url'     => $entry->registryUrl,
                'official'         => $this->catalog->isOfficial($entry->bundle->slug),
                'installed_version' => $installedVersion,
                'update_available' => $installedVersion !== null && $latestVersion !== null
                    && version_compare($latestVersion, $installedVersion, '>'),
                'orphaned'         => false,
            ];
        }

        // Bundle installé dont le registry d'origine ne le liste plus (retiré
        // ou bundle délisté) : gardé visible pour ne jamais bloquer sa
        // désinstallation depuis cet écran.
        $discovered = $this->discovery->discover();
        foreach ($installed as $slug => $activeVersion) {
            if (isset($seenSlugs[$slug])) {
                continue;
            }

            $entries[] = [
                'slug'              => $slug,
                'name'              => $discovered[$slug]['label'] ?? $slug,
                'description'       => $discovered[$slug]['description'] ?? '',
                'repository_url'    => null,
                'versions'          => [],
                'latest_version'    => null,
                'registry_name'     => null,
                'registry_url'      => null,
                'official'          => $this->catalog->isOfficial($slug),
                'installed_version' => $activeVersion,
                'update_available'  => false,
                'orphaned'          => true,
            ];
        }

        return Response::html($this->view->render('system.bundle-market', [
            'title'   => __('bundle_market'),
            'entries' => $entries,
            'error'   => $request->query('error'),
            'success' => $request->query('success'),
            'uninstalled' => $request->query('uninstalled'),
        ], 'layout.app'));
    }

    /** POST /admin/bundles/market/uninstall — retire un bundle installé (fichiers + base), désactivé au passage. */
    public function uninstall(Request $request): Response
    {
        $slug = trim((string) $request->post('slug', ''));
        if ($slug === '') {
            return Response::redirect($this->base() . '/admin/bundles/market?error=invalid');
        }

        if (!$this->installer->uninstall($slug)) {
            return Response::redirect($this->base() . '/admin/bundles/market?error=' . urlencode((string) $this->installer->getLastError()));
        }

        $this->disableBundle($slug);

        $this->auditLogger->log($request, 'bundle.uninstalled', 'bundle', null, [
            'slug' => $slug,
        ]);

        return Response::redirect($this->base() . '/admin/bundles/market?uninstalled=' . urlencode($slug));
    }

    /**
     * Retire un bundle désinstallé de la liste `enabled_bundles` en base pour
     * éviter une entrée fantôme — sans effet fonctionnel (BundleServiceProvider
     * n'enregistre que ce que BundleDiscoveryService trouve encore), mais
     * évite de laisser un slug obsolète traîner dans le réglage Owner.
     */
    private function disableBundle(string $slug): void
    {
        $stored = $this->appSettings->get(LicenseServiceProvider::SETTINGS_KEY);
        if ($stored === null) {
            return;
        }

        $enabled = json_decode($stored, true);
        if (!is_array($enabled) || !in_array($slug, $enabled, true)) {
            return;
        }

        $this->appSettings->set(
            LicenseServiceProvider::SETTINGS_KEY,
            json_encode(array_values(array_diff($enabled, [$slug])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    /** POST /admin/bundles/market/dry-run — vérification de compatibilité sans installation, réponse JSON. */
    public function dryRun(Request $request): Response
    {
        $slug = trim((string) $request->post('slug', ''));
        $repositoryUrl = trim((string) $request->post('repository_url', ''));
        $version = trim((string) $request->post('version', ''));

        if ($slug === '' || $repositoryUrl === '' || $version === '') {
            return Response::json(['ok' => false, 'error' => __('bundle_market_invalid_request')], 400);
        }

        $result = $this->installer->dryRun($slug, $repositoryUrl, $version);

        return Response::json([
            'ok'    => $result->success,
            'error' => $result->error,
        ], $result->success ? 200 : 422);
    }

    /** POST /admin/bundles/market/install — installation/mise à jour classique (rechargement de page). */
    public function install(Request $request): Response
    {
        $input = $this->readInstallInput($request);
        if ($input === null) {
            return Response::redirect($this->base() . '/admin/bundles/market?error=invalid');
        }
        [$slug, $repositoryUrl, $version, $registryUrl] = $input;

        $result = $this->installer->install($slug, $repositoryUrl, $version, $registryUrl);

        if (!$result->success) {
            return Response::redirect($this->base() . '/admin/bundles/market?error=' . urlencode((string) $result->error));
        }

        $this->auditLogger->log($request, 'bundle.installed', 'bundle', null, [
            'slug'    => $slug,
            'version' => $version,
        ]);

        return Response::redirect($this->base() . '/admin/bundles/market?success=' . urlencode($slug));
    }

    /**
     * POST /admin/bundles/market/install/stream — même installation, en streamant
     * la progression (SSE). Même contrat que BackupController::updateStream() :
     * ne retourne jamais réellement, termine par exit(0).
     */
    public function installStream(Request $request): Response
    {
        $input = $this->readInstallInput($request);

        session_write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        set_time_limit(0);

        $emit = function (string $event, array $data): void {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data) . "\n\n";
            flush();
        };

        if ($input === null) {
            $emit('error', ['message' => __('bundle_market_invalid_request')]);
            exit(0);
        }
        [$slug, $repositoryUrl, $version, $registryUrl] = $input;

        $result = $this->installer->install($slug, $repositoryUrl, $version, $registryUrl, function (int $percent, string $label) use ($emit): void {
            $emit('progress', ['percent' => $percent, 'label' => $label]);
        });

        if (!$result->success) {
            $emit('error', ['message' => (string) $result->error]);
            exit(0);
        }

        $this->auditLogger->log($request, 'bundle.installed', 'bundle', null, [
            'slug'    => $slug,
            'version' => $version,
        ]);

        $emit('done', ['slug' => $slug, 'version' => $version]);
        exit(0);
    }

    /**
     * Lit et valide les champs communs à install()/installStream(), y compris
     * le garde-fou "bundle tiers" : une installation/mise à jour d'un slug non
     * répertorié dans config/official-bundles.php exige explicitement
     * confirm_third_party=1, vérifié ici côté serveur (jamais uniquement côté JS).
     *
     * @return array{0: string, 1: string, 2: string, 3: ?string}|null [slug, repository_url, version, registry_url]
     */
    private function readInstallInput(Request $request): ?array
    {
        $slug = trim((string) $request->post('slug', ''));
        $repositoryUrl = trim((string) $request->post('repository_url', ''));
        $version = trim((string) $request->post('version', ''));
        $registryUrl = trim((string) $request->post('registry_url', ''));
        $confirmThirdParty = $request->post('confirm_third_party') === '1';

        if ($slug === '' || $repositoryUrl === '' || $version === '') {
            return null;
        }

        if (!$this->catalog->isOfficial($slug) && !$confirmThirdParty) {
            return null;
        }

        return [$slug, $repositoryUrl, $version, $registryUrl !== '' ? $registryUrl : null];
    }
}
