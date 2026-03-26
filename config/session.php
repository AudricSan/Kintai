<?php

declare(strict_types=1);

return [
    'name' => 'kintai_session',
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'secure' => false, // Set true in production with HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
];
