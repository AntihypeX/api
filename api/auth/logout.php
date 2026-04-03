<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

api_require_method('POST');

api_logout_user();

api_response([
    'message' => 'Вы вышли из аккаунта.',
]);
