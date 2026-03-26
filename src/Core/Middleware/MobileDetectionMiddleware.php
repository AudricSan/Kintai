<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

/**
 * Détecte si l'utilisateur est sur mobile (User-Agent) ou force la vue via session.
 * Partage $is_mobile avec toutes les vues et substitue layout.app → layout.mobile si mobile.
 */
final class MobileDetectionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ViewRenderer $view) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Priorité : session forcée > détection UA
        $forced = $_SESSION['device_view'] ?? null;

        if ($forced === 'mobile') {
            $isMobile = true;
        } elseif ($forced === 'desktop') {
            $isMobile = false;
        } else {
            $isMobile = $this->detectMobile($request);
        }

        $this->view->share('is_mobile', $isMobile);

        if ($isMobile) {
            $this->view->setLayoutOverride('layout.app', 'layout.mobile');
        }

        return $next($request);
    }

    private function detectMobile(Request $request): bool
    {
        $ua = $request->header('User-Agent') ?? '';
        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|webOS/i',
            $ua
        );
    }
}
