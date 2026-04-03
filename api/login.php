<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../connect.php';

requireMethod('POST');

$payload = readJsonInput();
$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($username === '' || $password === '') {
    sendJson([
        'message' => 'Укажите имя пользователя и пароль.',
    ], 422);
}

$link = dbConnect();
$stmt = mysqli_prepare($link, 'SELECT id, username FROM accounts WHERE username = ? LIMIT 1');

if ($stmt === false) {
    mysqli_close($link);
    sendJson([
        'message' => 'Не удалось подготовить запрос к базе данных.',
    ], 500);
}

mysqli_stmt_bind_param($stmt, 's', $username);
$executed = mysqli_stmt_execute($stmt);

if ($executed === false) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    sendJson([
        'message' => 'Не удалось выполнить запрос к базе данных.',
    ], 500);
}

$user = null;
mysqli_stmt_bind_result($stmt, $userId, $dbUsername);

if (mysqli_stmt_fetch($stmt)) {
    $user = [
        'id' => $userId,
        'username' => $dbUsername,
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($link);

if (!$user) {
    sendJson([
        'message' => 'Пользователь не найден.',
    ], 404);
}

if ($password !== '123') {
    sendJson([
        'message' => 'Неверный пароль.',
    ], 401);
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['login_time'] = time();

sendJson([
    'message' => 'Авторизация успешна.',
    'user' => getAuthenticatedUser(),
]);
