<?php

declare(strict_types=1);

/**
 * Database configuration for Eloquent ORM.
 *
 * Switch the active driver by changing 'driver' to one of: 'sqlite', 'mysql'.
 *
 * This file contains the application defaults.
 * The installer generates config/database.local.php with actual credentials,
 * which is merged here and must NOT be committed to version control.
 */

$config = [

    'driver' => 'sqlite',

    'connections' => [

        'sqlite' => [
            'path' => storage_path('app/database.sqlite'),
        ],

        'mysql' => [
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => (int) env('DB_PORT', 3306),
            'database'  => env('DB_DATABASE', 'kintai'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => env('DB_PREFIX', '')
        ],

    ],

];

return $config;