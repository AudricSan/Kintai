<?php

declare(strict_types=1);

return [
    'remember_me' => [
        'cookie_name' => 'kintai_remember',
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
    ],
    'password' => [
        'algo' => PASSWORD_BCRYPT,
        'cost' => 12,
    ],
];
