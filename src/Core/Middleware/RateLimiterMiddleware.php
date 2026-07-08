<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Exceptions\HttpException;
use kintai\Core\Request;
use kintai\Core\Response;

final class RateLimiterMiddleware implements MiddlewareInterface
{
    private const STORAGE_DIR = '/storage/logs/rate-limits';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $key = $request->method() . ':' . $request->uri();
        $storage = dirname(__DIR__, 3) . self::STORAGE_DIR;

        if (!is_dir($storage)) {
            @mkdir($storage, 0775, true);
        }

        $file = $storage . '/' . md5($ip . '_' . $key) . '.lock';

        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $decoded = json_decode($data, true);
                $attempts = array_filter(is_array($decoded) ? $decoded : [], fn(int $t) => $t > $now - self::WINDOW);
            }
        }

        if (count($attempts) >= self::MAX_ATTEMPTS) {
            throw new HttpException(429, 'Trop de tentatives. Réessayez dans quelques minutes.');
        }

        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);

        return $next($request);
    }
}