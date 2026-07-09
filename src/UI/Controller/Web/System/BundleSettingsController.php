<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\BundleDiscoveryService;
use kintai\Core\FeatureManager;
use kintai\Core\LicenseServiceProvider;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

final class BundleSettingsController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AppSettingsRepositoryInterface $appSettings,
        private readonly FeatureManager $features,
        private readonly AuditLogger $auditLogger,
        private readonly BundleDiscoveryService $discovery,
    ) {}

    /** GET /admin/bundles */
    public function show(Request $request): Response
    {
        $this->requireOwner($request);

        $official = $this->officialBundleSlugs();
        $bundles = [];
        foreach ($this->discovery->discover() as $key => $meta) {
            $bundles[] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'desc'     => $meta['description'],
                'enabled'  => $this->features->isEnabled($key),
                'official' => in_array($key, $official, true),
            ];
        }

        return Response::html($this->view->render('system.bundles', [
            'title'   => __('bundle_settings'),
            'bundles' => $bundles,
            'success' => isset($_GET['success']),
        ], 'layout.app'));
    }

    /** POST /admin/bundles */
    public function save(Request $request): Response
    {
        $this->requireOwner($request);

        $discoveredSlugs = array_keys($this->discovery->discover());

        $oldEnabled = array_values(array_filter(
            $discoveredSlugs,
            fn(string $key) => $this->features->isEnabled($key),
        ));

        $enabled = [];
        foreach ($discoveredSlugs as $key) {
            if ($request->post('bundle_' . $key)) {
                $enabled[] = $key;
            }
        }

        $this->appSettings->set(
            LicenseServiceProvider::SETTINGS_KEY,
            json_encode($enabled, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $this->auditLogger->logUpdate($request, 'bundle_settings.updated', 'system', null, [
            'enabled_bundles' => $oldEnabled,
        ], [
            'enabled_bundles' => $enabled,
        ], []);

        return Response::redirect($this->base() . '/admin/bundles?success=1');
    }

    private function officialBundleSlugs(): array
    {
        $path = BASE_PATH . '/config/official-bundles.php';
        return file_exists($path) ? (require $path) : [];
    }

    private function requireOwner(Request $request): void
    {
        $user = $request->getAttribute('auth_user');
        if (empty($user['is_admin'])) {
            header('Location: ' . $this->base() . '/');
            exit;
        }
    }
}
