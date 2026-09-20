<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\BundleDiscoveryService;
use kintai\Core\FeatureManager;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\PlanLimitService;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

final class LicenseController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly LicenseClientService $license,
        private readonly AuditLogger $auditLogger,
        private readonly PlanLimitService $planLimits,
        private readonly BundleDiscoveryService $discovery,
        private readonly FeatureManager $features,
    ) {}

    /** GET /admin/license */
    public function show(Request $request): Response
    {
        $this->license->refreshIfStale();

        $activeBundleCount = count(array_filter(
            array_keys($this->discovery->discover()),
            fn(string $slug) => $this->features->isEnabled($slug),
        ));

        return Response::html($this->view->render('system.license', [
            'title'            => __('license'),
            'configured'       => $this->license->isConfigured(),
            'licenseKey'       => $this->license->licenseKey(),
            'instanceId'       => $this->license->instanceId(),
            'state'            => $this->license->state(),
            'isPaidActive'     => $this->license->isPaidPlanActive(),
            'maxStores'        => $this->planLimits->maxStores(),
            'currentStores'    => $this->planLimits->currentStoreCount(),
            'maxEmployees'     => $this->planLimits->maxEmployees(),
            'currentEmployees' => $this->planLimits->currentEmployeeCount(),
            'maxBundles'       => $this->planLimits->maxActiveBundles(),
            'currentBundles'   => $activeBundleCount,
        ], 'layout.app'));
    }

    /** POST /admin/license/activate */
    public function activate(Request $request): Response
    {
        $licenseKey = trim($request->post('license_key', ''));
        if ($licenseKey === '') {
            return Response::redirect($this->base() . '/admin/license?error=missing_license_key');
        }

        $result = $this->license->activate($licenseKey);

        $this->auditLogger->log($request, 'license.activated', 'system', null, [
            'valid' => (bool) ($result['valid'] ?? false),
        ]);

        if (!($result['valid'] ?? false)) {
            return Response::redirect($this->base() . '/admin/license?error=' . urlencode((string) ($result['error'] ?? 'unknown')));
        }

        return Response::redirect($this->base() . '/admin/license?success=activated');
    }

    /** POST /admin/license/refresh */
    public function refresh(Request $request): Response
    {
        $result = $this->license->refresh();

        $this->auditLogger->log($request, 'license.refreshed', 'system', null, [
            'valid' => (bool) ($result['valid'] ?? false),
        ]);

        if ($result === null) {
            return Response::redirect($this->base() . '/admin/license?error=no_license');
        }
        if (!($result['valid'] ?? false)) {
            return Response::redirect($this->base() . '/admin/license?error=' . urlencode((string) ($result['error'] ?? 'unknown')));
        }

        return Response::redirect($this->base() . '/admin/license?success=refreshed');
    }

    /** POST /admin/license/deactivate */
    public function deactivate(Request $request): Response
    {
        $this->license->deactivate();

        $this->auditLogger->log($request, 'license.deactivated', 'system', null, []);

        return Response::redirect($this->base() . '/admin/license?success=deactivated');
    }
}
