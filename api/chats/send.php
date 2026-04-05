<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../../gigachat_client.php';

api_require_method('POST');

$user = api_current_user();

if ($user === null || !isset($user['id'])) {
    api_response([
        'message' => 'Нужна авторизация.',
    ], 401);
}

$payload = api_input();
$chatId = (int) ($payload['chatId'] ?? 0);
$message = trim((string) ($payload['message'] ?? ''));
$number = (int) ($payload['number'] ?? 0);

if ($message === '') {
    api_response([
        'message' => 'Введите сообщение.',
    ], 400);
}

$link = dbConnect();
$userId = (int) $user['id'];

if ($chatId <= 0) {
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

    $createStmt = mysqli_prepare(
        $link,
        'INSERT INTO chats (id, user_id, number, date) VALUES (NULL, ?, ?, NOW())'
    );

    if ($createStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос создания чата.',
        ], 500);
    }

    mysqli_stmt_bind_param($createStmt, 'ii', $userId, $nextNumber);

    if (!mysqli_stmt_execute($createStmt)) {
        mysqli_stmt_close($createStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось создать чат.',
        ], 500);
    }

    $chatId = (int) mysqli_insert_id($link);
    mysqli_stmt_close($createStmt);
} else {
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
}

$insertUserStmt = mysqli_prepare(
    $link,
    'INSERT INTO messages (id, user_id, chat_id, date, text) VALUES (NULL, ?, ?, NOW(), ?)'
);

if ($insertUserStmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос сохранения сообщения.',
    ], 500);
}

$userPayload = json_encode(
    [
        'role' => 'user',
        'content' => $message,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$userPayload = $userPayload === false ? $message : $userPayload;

mysqli_stmt_bind_param($insertUserStmt, 'iis', $userId, $chatId, $userPayload);

if (!mysqli_stmt_execute($insertUserStmt)) {
    mysqli_stmt_close($insertUserStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось сохранить сообщение пользователя.',
    ], 500);
}

mysqli_stmt_close($insertUserStmt);

$historyStmt = mysqli_prepare(
    $link,
    'SELECT user_id, text FROM messages WHERE chat_id = ? ORDER BY id DESC LIMIT 20'
);

if ($historyStmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос истории.',
    ], 500);
}

mysqli_stmt_bind_param($historyStmt, 'i', $chatId);

if (!mysqli_stmt_execute($historyStmt)) {
    mysqli_stmt_close($historyStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось получить историю чата.',
    ], 500);
}

$historyResult = mysqli_stmt_get_result($historyStmt);

if ($historyResult === false) {
    mysqli_stmt_close($historyStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось получить результат истории.',
    ], 500);
}

$history = [];

while ($row = mysqli_fetch_assoc($historyResult)) {
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

    $history[] = [
        'role' => $role,
        'content' => $content,
    ];
}

mysqli_free_result($historyResult);
mysqli_stmt_close($historyStmt);

$history = array_reverse($history);

try {
    $result = gigachatAskWithMessages($history);
} catch (Throwable $exception) {
    mysqli_close($link);
    api_response([
        'message' => $exception->getMessage(),
        'error_type' => $exception::class,
    ], 500);
}

$reply = trim((string) ($result['reply'] ?? ''));

$insertAiStmt = mysqli_prepare(
    $link,
    'INSERT INTO messages (id, user_id, chat_id, date, text) VALUES (NULL, ?, ?, NOW(), ?)'
);

if ($insertAiStmt === false) {
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось подготовить запрос сохранения ответа.',
    ], 500);
}

$assistantPayload = json_encode(
    [
        'role' => 'assistant',
        'content' => $reply,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$assistantPayload = $assistantPayload === false ? $reply : $assistantPayload;

$assistantUserId = 0;
mysqli_stmt_bind_param($insertAiStmt, 'iis', $assistantUserId, $chatId, $assistantPayload);

if (!mysqli_stmt_execute($insertAiStmt)) {
    mysqli_stmt_close($insertAiStmt);
    mysqli_close($link);
    api_response([
        'message' => 'Не удалось сохранить ответ ИИ.',
    ], 500);
}

mysqli_stmt_close($insertAiStmt);
mysqli_close($link);

api_response([
    'chat_id' => $chatId,
    'reply' => $reply,
    'model' => $result['model'] ?? null,
    'usage' => $result['usage'] ?? null,
]);
