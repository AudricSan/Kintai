<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Request;
use kintai\Core\Response;

final class TestApiController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'status' => 'ok',
            'app' => 'kintai',
            'version' => '1.0.0',
            'timestamp' => date('c'),
        ]);
    }
}
