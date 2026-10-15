<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\SetupWizard;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

/**
 * Assistant de configuration (/admin/setup), ouvert par l'installateur : Licence, puis Bundles, puis Terminer.
 * Réservé à l'Owner (OwnerOnlyMiddleware, voir config/routes.php).
 */
final class SetupWizardController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly SetupWizard $wizard,
        private readonly LicenseClientService $license,
        private readonly AppSettingsService $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /admin/setup — étape en cours ; tableau de bord si l'assistant est terminé. */
    public function show(Request $request): Response
    {
        if (!$this->wizard->isPending()) {
            return Response::redirect($this->base() . '/');
        }

        $data = ['title' => __('setup_title'), 'step' => $this->wizard->step(), 'error' => $request->query('error')];
        if ($data['step'] === SetupWizard::STEP_LICENSE) {
            $data['licenseConfigured'] = $this->license->isConfigured();
            $data['licenseError'] = $this->license->state()['error'] ?? null;
        } else {
            $data['bundles'] = $this->wizard->availableBundles($this->settings->bundleUpdateChannel());
            $data['maxBundles'] = $this->wizard->maxBundles();
        }

        return Response::html($this->view->render('system.setup', $data, 'layout.app'));
    }

    /** POST /admin/setup/license — active la licence saisie, ou passe l'étape (plan gratuit). */
    public function license(Request $request): Response
    {
        if ($request->post('skip', '') === '1') {
            $this->wizard->licenseDone();
            return Response::redirect($this->base() . '/admin/setup');
        }

        $key = trim((string) $request->post('license_key', ''));
        if ($key === '') {
            return Response::redirect($this->base() . '/admin/setup?error=missing_license_key');
        }
        $result = $this->license->activate($key);
        $this->auditLogger->log($request, 'license.activated', 'system', null, ['valid' => (bool) ($result['valid'] ?? false), 'from' => 'setup']);
        if (!($result['valid'] ?? false)) {
            return Response::redirect($this->base() . '/admin/setup?error=license_invalid');
        }

        $this->wizard->licenseDone();
        return Response::redirect($this->base() . '/admin/setup');
    }

    /**
     * POST /admin/setup/bundles — installe et active les bundles choisis, puis affiche le bilan (installés, inactifs
     * faute de place, échecs à refaire depuis le catalogue). Avec « skip », termine sans rien installer.
     */
    public function bundles(Request $request): Response
    {
        if ($request->post('skip', '') === '1') {
            $this->wizard->finish();
            return Response::redirect($this->base() . '/');
        }

        $slugs = array_values(array_filter(array_map('strval', (array) $request->post('bundles', [])), static fn(string $s): bool => $s !== ''));
        set_time_limit(0);
        $result = $this->wizard->install($slugs, $this->settings->bundleUpdateChannel());
        if ($result['error'] !== null) {
            return Response::redirect($this->base() . '/admin/setup?error=' . $result['error']);
        }
        $this->auditLogger->log($request, 'setup.bundles_installed', 'system', null, [
            'installed' => $result['installed'],
            'failed'    => array_keys($result['failed']),
        ]);
        $this->wizard->finish();

        return Response::html($this->view->render('system.setup', [
            'title'  => __('setup_title'),
            'step'   => 'done',
            'result' => $result,
        ], 'layout.app'));
    }
}
