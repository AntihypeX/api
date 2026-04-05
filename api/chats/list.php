<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

api_require_method('GET');

$user = api_current_user();

if ($user === null || !isset($user['id'])) {
    api_response([
        'message' => 'Нужна авторизация.',
    ], 401);
}

$link = dbConnect();
$userId = (int) $user['id'];

$stmt = mysqli_prepare(
    $link,
    'SELECT id, number, date FROM chats WHERE user_id = ? ORDER BY id DESC'
);

if ($stmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос списка чатов.',
    ], 500);
}

mysqli_stmt_bind_param($stmt, 'i', $userId);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось получить список чатов.',
    ], 500);
}

$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось получить результат запроса.',
    ], 500);
}

$chats = [];

while ($row = mysqli_fetch_assoc($result)) {
    $chats[] = [
        'id' => (int) ($row['id'] ?? 0),
        'number' => (int) ($row['number'] ?? 0),
        'date' => (string) ($row['date'] ?? ''),
    ];
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);
mysqli_close($link);

api_response([
    'chats' => $chats,
]);
