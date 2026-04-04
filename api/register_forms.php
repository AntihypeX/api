<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

api_require_method('POST');

$currentUser = api_current_user();

if ($currentUser === null || !isset($currentUser['id'])) {
    api_response([
        'message' => 'Нужна авторизация.',
    ], 401);
}

$data = api_input();
$message = trim((string) ($data['message'] ?? ''));

if ($message === '') {
    api_response([
        'message' => 'Заполните message.',
    ], 400);
}

$link = dbConnect();
$userId = (int) $currentUser['id'];
$username = (string) $currentUser['username'];

$reviewStmt = mysqli_prepare(
    $link,
    'INSERT INTO reviews (id_user, message) VALUES (?, ?)'
);

if ($reviewStmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос на сохранение сообщения.',
    ], 500);
}

mysqli_stmt_bind_param($reviewStmt, 'is', $userId, $message);

if (!mysqli_stmt_execute($reviewStmt)) {
    mysqli_stmt_close($reviewStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось сохранить сообщение в базе данных.',
    ], 500);
}

$reviewId = mysqli_insert_id($link);

mysqli_stmt_close($reviewStmt);
mysqli_close($link);

api_response([
    'message' => 'Сообщение успешно сохранено.',
    'review' => [
        'id' => $reviewId,
        'id_user' => $userId,
        'username' => $username,
        'message' => $message,
    ],
], 201);
