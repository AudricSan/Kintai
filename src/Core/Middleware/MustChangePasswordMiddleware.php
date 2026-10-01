<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Impose le changement du mot de passe par défaut « 0000 » (ou d'un mot de passe trop court) : tant que
 * l'utilisateur connecté le garde, il ne peut atteindre que son profil, l'enregistrement du nouveau mot de
 * passe et la déconnexion. Sans ça, quiconque connaît un code employé et un code magasin se connectait
 * avec « 0000 » tant que l'employé n'avait pas pensé à le changer.
 *
 * L'API (/api/*) et les endpoints cron restent hors du périmètre : ils s'authentifient par jeton, sans
 * session, et n'ont pas d'écran où changer un mot de passe.
 */
final class MustChangePasswordMiddleware implements MiddlewareInterface
{
    /** Préfixes libres : ressources statiques, API à jeton, cron, photo de profil affichée sur /profile. */
    private const BYPASS_PREFIXES = ['/api/', '/cron/', '/assets/', '/bundle-assets/', '/avatar/'];

    /** Chemins exacts libres, toutes méthodes confondues. */
    private const BYPASS_PATHS = ['/login', '/logout', '/profile', '/profile/password', '/sw.js', '/manifest.json'];

    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->uri();

        if (in_array($uri, self::BYPASS_PATHS, true)) {
            return $next($request);
        }
        foreach (self::BYPASS_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return $next($request);
            }
        }

        try {
            /** @var AuthService $auth */
            $auth = $this->container->make(AuthService::class);
            $mustChange = $auth->mustChangePassword();
        } catch (\Throwable) {
            // Base indisponible ou pas encore migrée : ne jamais bloquer le site pour ça.
            return $next($request);
        }

        if (!$mustChange) {
            return $next($request);
        }

        return Response::redirect(base_url() . '/profile?tab=info');
    }
}
