<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Marks the request as expecting JSON. Controllers can check this,
 * and the error handler will return JSON for uncaught exceptions.
 */
final class JsonResponseMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('wantsJson', true);
        return $next($request);
    }
}
