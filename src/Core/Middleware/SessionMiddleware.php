<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Request;
use kintai\Core\Response;

final class SessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            $configFile = dirname(__DIR__, 3) . '/config/session.php';
            $config = file_exists($configFile) ? require $configFile : [];

            $secure = (bool) ($config['secure'] ?? false);
            if ($request->isSecure()) {
                $secure = true;
            }

            session_set_cookie_params([
                'lifetime' => $config['lifetime'] ?? 7200,
                'path'     => $config['path'] ?? '/',
                'secure'   => $secure,
                'httponly' => $config['httponly'] ?? true,
                'samesite' => $config['samesite'] ?? 'Lax',
            ]);

            session_name($config['name'] ?? 'kintai_session');
            session_start();
        }
        return $next($request);
    }
}
