<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
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
        } catch (\Throwable) {
            // Table absente (avant migration) ou DB non disponible — on ignore.
        }

        return $next($request);
    }
}
