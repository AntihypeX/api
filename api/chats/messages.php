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

$chatId = (int) ($_GET['chatId'] ?? 0);

if ($chatId <= 0) {
    api_response([
        'message' => 'Укажите chatId.',
    ], 400);
}

$link = dbConnect();
$userId = (int) $user['id'];

$checkStmt = mysqli_prepare(
    $link,
    'SELECT id FROM chats WHERE id = ? AND user_id = ? LIMIT 1'
);

if ($checkStmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос проверки чата.',
    ], 500);
}

mysqli_stmt_bind_param($checkStmt, 'ii', $chatId, $userId);

if (!mysqli_stmt_execute($checkStmt)) {
    mysqli_stmt_close($checkStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось проверить чат.',
    ], 500);
}

$checkResult = mysqli_stmt_get_result($checkStmt);

if ($checkResult === false || mysqli_num_rows($checkResult) === 0) {
    if ($checkResult !== false) {
        mysqli_free_result($checkResult);
    }
    mysqli_stmt_close($checkStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Чат не найден.',
    ], 404);
}

mysqli_free_result($checkResult);
mysqli_stmt_close($checkStmt);

$stmt = mysqli_prepare(
    $link,
    'SELECT id, user_id, text, date FROM messages WHERE chat_id = ? ORDER BY id ASC'
);

if ($stmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос сообщений.',
    ], 500);
}

mysqli_stmt_bind_param($stmt, 'i', $chatId);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось получить сообщения.',
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

$messages = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rawText = (string) ($row['text'] ?? '');
    $decoded = json_decode($rawText, true);
    $role = '';
    $content = '';

    if (is_array($decoded)) {
        $role = (string) ($decoded['role'] ?? '');
        $content = (string) ($decoded['content'] ?? '');
    }

    if ($role === '' || $content === '') {
        $role = ((int) ($row['user_id'] ?? 0)) === 0 ? 'assistant' : 'user';
        $content = $rawText;
    }

    $messages[] = [
        'id' => (int) ($row['id'] ?? 0),
        'role' => $role,
        'content' => $content,
        'date' => (string) ($row['date'] ?? ''),
    ];
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);
mysqli_close($link);

api_response([
    'messages' => $messages,
]);
