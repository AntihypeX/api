<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');

$user = getAuthenticatedUser();

if ($user === null) {
    sendJson([
        'message' => 'Пользователь не авторизован.',
    ], 401);
}

sendJson([
    'user' => $user,
]);
