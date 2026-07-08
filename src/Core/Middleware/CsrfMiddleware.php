<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Exceptions\HttpException;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Génère et valide le token CSRF pour toutes les requêtes mutantes hors /api/.
 * Le token est stocké en session et transmis via le champ caché `_token`
 * ou l'en-tête `X-CSRF-Token` pour les requêtes AJAX.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_token';

    public function handle(Request $request, Closure $next): Response
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        $realMethod = strtoupper($request->server('REQUEST_METHOD', 'GET'));
        $isApi      = str_starts_with($request->uri(), '/api/');

        if (!$isApi && in_array($realMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->post(self::FIELD_NAME)
                  ?? $request->header('X-CSRF-Token');

            if (!hash_equals($_SESSION[self::SESSION_KEY], (string) $token)) {
                // Renvoyer vers une page d'erreur complète au lieu de lancer une exception
                http_response_code(419);
                echo "<!doctype html><html lang='fr'><head><meta charset='utf-8'><title>Erreur 419 — Action expirée</title><style>body{font-family:Arial,Helvetica,sans-serif;margin:2rem;color:#222}header{border-bottom:1px solid #eee;margin-bottom:1rem}h1{color:#b00}</style></head><body><header><h1>Erreur 419 — Action expirée</h1></header><p>La session a expiré ou le jeton CSRF est invalide. Veuillez recharger la page et réessayer.</p><p><a href='/'>Retour à l'accueil</a></p></body></html>";
                exit;
            }
        }

        return $next($request);
    }
}
