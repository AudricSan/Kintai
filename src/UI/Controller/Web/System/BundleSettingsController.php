<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

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

    /**
     * Bundles connus, avec leurs clés de traduction (libellé + description).
     * Tenu à jour manuellement au fil des extractions de fonctionnalités du Core vers un bundle.
     */
    private const KNOWN_BUNDLES = [
        'daily-report' => ['label' => 'bundle_daily_report', 'desc' => 'bundle_daily_report_desc'],
        'messaging'    => ['label' => 'bundle_messaging',     'desc' => 'bundle_messaging_desc'],
        'store-photos' => ['label' => 'bundle_store_photos',  'desc' => 'bundle_store_photos_desc'],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AppSettingsRepositoryInterface $appSettings,
        private readonly FeatureManager $features,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /admin/bundles */
    public function show(Request $request): Response
    {
        $this->requireOwner($request);

        $bundles = [];
        foreach (self::KNOWN_BUNDLES as $key => $meta) {
            $bundles[] = [
                'key'     => $key,
                'label'   => __($meta['label']),
                'desc'    => __($meta['desc']),
                'enabled' => $this->features->isEnabled($key),
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

        $oldEnabled = array_keys(array_filter(
            array_combine(array_keys(self::KNOWN_BUNDLES), array_map(
                fn(string $key) => $this->features->isEnabled($key),
                array_keys(self::KNOWN_BUNDLES),
            )),
        ));

        $enabled = [];
        foreach (array_keys(self::KNOWN_BUNDLES) as $key) {
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

    private function requireOwner(Request $request): void
    {
        $user = $request->getAttribute('auth_user');
        if (empty($user['is_admin'])) {
            header('Location: ' . $this->base() . '/');
            exit;
        }
    }
}
