<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\ThemeColorPalette;
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
        $themeColors = [];
        $themeColorsDark = [];
        foreach (ThemeColorPalette::DEFAULTS as $group => $default) {
            $themeColors[$group] = $this->settings->themeColor($group);
            // Pré-remplit le sélecteur "variante sombre" (affiché en mode manuel) avec la
            // teinte auto-calculée tant qu'aucune n'a été réglée à la main : si l'Owner
            // bascule en manuel et enregistre sans y toucher, la couleur claire brute ne se
            // retrouve pas enregistrée telle quelle comme variante sombre (illisible sur
            // fond sombre pour la plupart des couleurs).
            $storedDark = $this->settings->themeColorDark($group);
            $themeColorsDark[$group] = $storedDark !== '' ? $storedDark : ThemeColorPalette::autoDarkBase($themeColors[$group]);
        }

        return Response::html($this->view->render('system.owner-settings', [
            'title'    => __('owner_settings'),
            'settings' => [
                'app_subtitle'      => $this->settings->subtitle(),
                'app_login_notice'  => $this->settings->loginNotice(),
                'app_support_email' => $this->settings->supportEmail(),
                'maintenance_mode_enabled' => $this->settings->maintenanceModeEnabled() ? '1' : '0',
                'maintenance_message'      => $this->settings->maintenanceMessage(),
            ],
            'theme_colors'      => $themeColors,
            'theme_colors_dark' => $themeColorsDark,
            'theme_dark_mode'   => $this->settings->themeDarkMode(),
            'success' => isset($_GET['success']),
        ], 'layout.app'));
    }

    /** POST /admin/owner-settings */
    public function save(Request $request): Response
    {
        $subtitle     = substr(trim((string) $request->post('app_subtitle', '')), 0, 100);
        $loginNotice  = substr(trim((string) $request->post('app_login_notice', '')), 0, 300);
        $supportEmail = trim((string) $request->post('app_support_email', ''));

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $supportEmail = '';
        }

        $darkMode = $request->post('app_theme_dark_mode', '') === 'manual' ? 'manual' : 'auto';

        // Couleurs personnalisables du thème (voir ThemeColorPalette) : une valeur claire
        // invalide retombe sur la valeur actuellement enregistrée (pas sur le défaut Foxy,
        // pour ne pas silencieusement réinitialiser un autre réglage en cas de champ vide).
        // La couleur sombre manuelle, elle, retombe sur '' (= mode auto pour ce groupe) si
        // invalide : elle n'a de sens que lorsque $darkMode === 'manual'.
        $oldThemeData = [];
        $newThemeData = [];
        foreach (ThemeColorPalette::DEFAULTS as $group => $default) {
            $oldThemeData["app_{$group}_color"] = $this->settings->themeColor($group);
            $oldThemeData["app_{$group}_color_dark"] = $this->settings->themeColorDark($group);

            $light = strtolower(trim((string) $request->post("app_{$group}_color", '')));
            $newThemeData["app_{$group}_color"] = ThemeColorPalette::isValidHex($light)
                ? $light
                : $oldThemeData["app_{$group}_color"];

            $dark = strtolower(trim((string) $request->post("app_{$group}_color_dark", '')));
            $newThemeData["app_{$group}_color_dark"] = ThemeColorPalette::isValidHex($dark) ? $dark : '';
        }
        $oldThemeData['app_theme_dark_mode'] = $this->settings->themeDarkMode();
        $newThemeData['app_theme_dark_mode'] = $darkMode;

        $maintenanceModeEnabled = $request->post('maintenance_mode_enabled', '0') === '1' ? '1' : '0';
        $maintenanceMessage     = substr(trim((string) $request->post('maintenance_message', '')), 0, 500);

        $oldData = array_merge([
            'app_subtitle'      => $this->settings->subtitle(),
            'app_login_notice'  => $this->settings->loginNotice(),
            'app_support_email' => $this->settings->supportEmail(),
            'maintenance_mode_enabled' => $this->settings->maintenanceModeEnabled() ? '1' : '0',
            'maintenance_message'      => $this->settings->maintenanceMessage(),
        ], $oldThemeData);

        $newData = array_merge([
            'app_subtitle'      => $subtitle,
            'app_login_notice'  => $loginNotice,
            'app_support_email' => $supportEmail,
            'maintenance_mode_enabled' => $maintenanceModeEnabled,
            'maintenance_message'      => $maintenanceMessage,
        ], $newThemeData);

        $this->settings->setMany($newData);

        $this->auditLogger->logUpdate($request, 'owner_settings.updated', 'system', null, $oldData, $newData, []);

        return Response::redirect($this->base() . '/admin/owner-settings?success=1');
    }
}
