<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;

final class PwaController
{
    public function manifest(Request $request): Response
    {
        $baseUrl = rtrim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

        $data = [
            'name'             => 'Kintai',
            'short_name'       => 'Kintai',
            'description'      => 'Gestion de plannings et pointages',
            'start_url'        => $base . '/',
            'display'          => 'standalone',
            'background_color' => '#f5f5f5',
            'theme_color'      => '#1a5c8c',
            'icons'            => [
                [
                    'src'   => $base . '/assets/img/kintai-192.svg',
                    'sizes' => '192x192',
                    'type'  => 'image/svg+xml',
                ],
            ],
        ];

        return new Response(json_encode($data, JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
