<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

api_require_method('POST');

$payload = api_input();
$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($username === '' || $password === '') {
    api_response([
        'message' => 'Укажите имя пользователя и пароль.',
    ], 422);
}

$user = api_find_user_for_login($username);

if (!$user) {
    api_response([
        'message' => 'Пользователь не найден.',
    ], 404);
}

if (!password_verify($password, (string) $user['password'])) {
    api_response([
        'message' => 'Неверный пароль.',
    ], 401);
}

api_login_user($user);

api_response([
    'message' => 'Авторизация успешна.',
    'user' => api_current_user(),
]);
