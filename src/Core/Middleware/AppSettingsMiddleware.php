<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\PublicUrlResolver;
use kintai\Core\Services\UpdateService;
use kintai\UI\ViewRenderer;

final class AppSettingsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = $this->container->make(AppSettingsService::class);
            $view     = $this->container->make(ViewRenderer::class);

            $view->share('app_subtitle',     $settings->subtitle());
            $view->share('app_login_notice', $settings->loginNotice());
            $view->share('app_theme_color_style', $settings->themeColorStyle());
            $view->share('app_support_email', $settings->supportEmail());
            $view->share('app_maintenance_mode_enabled', $settings->maintenanceModeEnabled());
            $view->share('app_version', $this->container->make(UpdateService::class)->getCurrentVersion());
            // Sans URL publique, les e-mails de réinitialisation du mot de passe ne partent pas : l'Owner en est averti.
            $view->share('app_public_url_missing', $this->container->make(PublicUrlResolver::class)->resolve() === null);
        } catch (\Throwable) {
            // Table absente (avant migration) ou DB non disponible — on ignore.
        }

        return $next($request);
    }
}
