<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

final class OwnerSettingsController
{
    use HasBaseUrl;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AppSettingsService $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /admin/owner-settings */
    public function show(Request $request): Response
    {
        $this->requireOwner($request);

        return Response::html($this->view->render('system.owner-settings', [
            'title'    => __('owner_settings'),
            'settings' => [
                'app_subtitle'      => $this->settings->subtitle(),
                'app_login_notice'  => $this->settings->loginNotice(),
                'app_support_email' => $this->settings->supportEmail(),
                'backup_max_keep'       => $this->settings->backupMaxKeep(),
                'backup_auto_enabled'   => $this->settings->backupAutoEnabled() ? '1' : '0',
                'maintenance_mode_enabled' => $this->settings->maintenanceModeEnabled() ? '1' : '0',
                'maintenance_message'      => $this->settings->maintenanceMessage(),
            ],
            'success' => isset($_GET['success']),
        ], 'layout.app'));
    }

    /** POST /admin/owner-settings */
    public function save(Request $request): Response
    {
        $this->requireOwner($request);

        $subtitle     = substr(trim((string) $request->post('app_subtitle', '')), 0, 100);
        $loginNotice  = substr(trim((string) $request->post('app_login_notice', '')), 0, 300);
        $supportEmail = trim((string) $request->post('app_support_email', ''));

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $supportEmail = '';
        }

        $backupMaxKeep     = max(0, min(365, (int) $request->post('backup_max_keep', '0')));
        $backupAutoEnabled = $request->post('backup_auto_enabled', '0') === '1' ? '1' : '0';

        $maintenanceModeEnabled = $request->post('maintenance_mode_enabled', '0') === '1' ? '1' : '0';
        $maintenanceMessage     = substr(trim((string) $request->post('maintenance_message', '')), 0, 500);

        $oldData = [
            'app_subtitle'      => $this->settings->subtitle(),
            'app_login_notice'  => $this->settings->loginNotice(),
            'app_support_email' => $this->settings->supportEmail(),
            'backup_max_keep'       => $this->settings->backupMaxKeep(),
            'backup_auto_enabled'   => $this->settings->backupAutoEnabled() ? '1' : '0',
            'maintenance_mode_enabled' => $this->settings->maintenanceModeEnabled() ? '1' : '0',
            'maintenance_message'      => $this->settings->maintenanceMessage(),
        ];

        $this->settings->setMany([
            'app_subtitle'      => $subtitle,
            'app_login_notice'  => $loginNotice,
            'app_support_email' => $supportEmail,
            'backup_max_keep'       => (string) $backupMaxKeep,
            'backup_auto_enabled'   => $backupAutoEnabled,
            'maintenance_mode_enabled' => $maintenanceModeEnabled,
            'maintenance_message'      => $maintenanceMessage,
        ]);

        $this->auditLogger->logUpdate($request, 'owner_settings.updated', 'system', null, $oldData, [
            'app_subtitle'      => $subtitle,
            'app_login_notice'  => $loginNotice,
            'app_support_email' => $supportEmail,
            'backup_max_keep'       => (string) $backupMaxKeep,
            'backup_auto_enabled'   => $backupAutoEnabled,
            'maintenance_mode_enabled' => $maintenanceModeEnabled,
            'maintenance_message'      => $maintenanceMessage,
        ], []);

        return Response::redirect($this->base() . '/admin/owner-settings?success=1');
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
