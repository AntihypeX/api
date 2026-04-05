<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

api_require_method('GET');

$path = trim((string) ($_GET['path'] ?? '/'));

if ($path === '') {
    $path = '/';
}

if ($path !== '/') {
    api_response([
        'message' => 'Доступ разрешен только для пути /.',
    ], 403);
}

$user = api_current_user();

if ($user === null) {
    api_response([
        'message' => 'Пользователь не авторизован.',
    ], 401);
}

api_response([
    'user' => $user,
]);
