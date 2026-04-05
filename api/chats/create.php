<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

api_require_method('POST');

$user = api_current_user();

if ($user === null || !isset($user['id'])) {
    api_response([
        'message' => 'Нужна авторизация.',
    ], 401);
}

$payload = api_input();
$number = (int) ($payload['number'] ?? 0);

$link = dbConnect();
$userId = (int) $user['id'];

$nextNumber = $number;

if ($nextNumber <= 0) {
    $numStmt = mysqli_prepare(
        $link,
        'SELECT COALESCE(MAX(number), 0) + 1 AS next_number FROM chats WHERE user_id = ?'
    );

    if ($numStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос нумерации чата.',
        ], 500);
    }

    mysqli_stmt_bind_param($numStmt, 'i', $userId);

    if (!mysqli_stmt_execute($numStmt)) {
        mysqli_stmt_close($numStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось получить номер чата.',
        ], 500);
    }

    $numResult = mysqli_stmt_get_result($numStmt);
    $row = $numResult ? mysqli_fetch_assoc($numResult) : null;
    $nextNumber = (int) ($row['next_number'] ?? 1);

    if ($numResult) {
        mysqli_free_result($numResult);
    }
    mysqli_stmt_close($numStmt);
}

$stmt = mysqli_prepare(
    $link,
    'INSERT INTO chats (id, user_id, number, date) VALUES (NULL, ?, ?, NOW())'
);

if ($stmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос создания чата.',
    ], 500);
}

mysqli_stmt_bind_param($stmt, 'ii', $userId, $nextNumber);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось создать чат.',
    ], 500);
}

$chatId = mysqli_insert_id($link);
mysqli_stmt_close($stmt);
mysqli_close($link);

api_response([
    'chat' => [
        'id' => $chatId,
        'number' => $nextNumber,
    ],
], 201);
