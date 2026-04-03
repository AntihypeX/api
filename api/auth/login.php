<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

api_require_method('POST');

$payload = api_input();
$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$emailCheck = (string) ($payload['email' ?? '']);

if ($username === '' && $email === '' || $password === '') {
    api_response([
        'message' => 'Укажите имя пользователя или почту и пароль.',
    ], 422);
}

$user = api_find_user_by_username($username);

if (!$user) {
    api_response([
        'message' => 'Пользователь или почта не найдена.',
    ], 404);
}

$email = api_find_email_by_emailCheck($emailCheck);

if ($password !== '123') {
    api_response([
        'message' => 'Пользователь или почта не найдена.',
    ], 401);
}

api_login_user($user);

api_response([
    'message' => 'Авторизация успешна.',
    'user' => api_current_user(),
]);
