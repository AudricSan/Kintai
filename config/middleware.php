<?php

declare(strict_types=1);

return [
    // Applied to every request
    'global' => [
        \kintai\Core\Middleware\SessionMiddleware::class,
        \kintai\Core\Middleware\I18nMiddleware::class,
        \kintai\Core\Middleware\MobileDetectionMiddleware::class,
    ],

    // Named middleware for route groups
    'named' => [
        'auth'  => \kintai\Core\Middleware\AuthMiddleware::class,
        'admin' => \kintai\Core\Middleware\AdminMiddleware::class,
        'json'  => \kintai\Core\Middleware\JsonResponseMiddleware::class,
    ],
];
