<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;

final class PwaController
{
    public function manifest(Request $request): Response
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

        $data = [
            'name'             => 'Kintai',
            'short_name'       => 'Kintai',
            'description'      => 'Gestion de plannings et pointages',
            'start_url'        => $base . '/',
            'scope'            => $base . '/',
            'display'          => 'standalone',
            'background_color' => '#f5f5f5',
            'theme_color'      => '#1a5c8c',
            'icons'            => [
                [
                    'src'     => $base . '/assets/img/kintai-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => $base . '/assets/img/kintai-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => $base . '/assets/img/kintai-512-maskable.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return Response::json($data)->withHeader('Content-Type', 'application/manifest+json');
    }
}
