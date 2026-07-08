<?php

declare(strict_types=1);

return [
    // Applied to every request
    'global' => [
        \kintai\Core\Middleware\SessionMiddleware::class,
        \kintai\Core\Middleware\SecurityHeadersMiddleware::class,
        \kintai\Core\Middleware\CsrfMiddleware::class,
        \kintai\Core\Middleware\I18nMiddleware::class,
        \kintai\Core\Middleware\AppSettingsMiddleware::class,
        \kintai\Core\Middleware\DailyReportNavMiddleware::class,
        \kintai\Core\Middleware\MessageCountMiddleware::class,
        \kintai\Core\Middleware\NotificationMiddleware::class,
    ],

    // Named middleware for route groups
    'named' => [
        'auth'  => \kintai\Core\Middleware\AuthMiddleware::class,
        'admin' => \kintai\Core\Middleware\AdminMiddleware::class,
        'json'  => \kintai\Core\Middleware\JsonResponseMiddleware::class,
        'rate-limit' => \kintai\Core\Middleware\RateLimiterMiddleware::class,
    ],
];
