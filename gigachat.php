<?php
declare(strict_types=1);

require_once __DIR__ . '/api/common.php';
require_once __DIR__ . '/gigachat_client.php';

api_require_method('POST');

$payload = api_input();
$message = trim((string) ($payload['message'] ?? ''));
$systemPrompt = trim((string) ($payload['systemPrompt'] ?? ''));
$model = trim((string) ($payload['model'] ?? ''));

if ($message === '') {
    api_response([
        'message' => 'Введите сообщение для ИИ.',
    ], 422);
}

try {
    $result = gigachatAsk($message, $systemPrompt, $model);

    api_response([
        'message' => 'Ответ от GigaChat получен.',
        'reply' => $result['reply'],
        'model' => $result['model'],
        'usage' => $result['usage'],
        'provider' => 'gigachat',
    ]);
} catch (Throwable $exception) {
    api_response([
        'message' => $exception->getMessage(),
        'error_type' => $exception::class,
    ], 500);
}
