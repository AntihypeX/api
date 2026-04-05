<?php
declare(strict_types=1);

require_once __DIR__ . '/connect.php';

function gigachatAsk(string $message, string $systemPrompt = '', string $model = ''): array
{
    return gigachatAskWithMessages([
        [
            'role' => 'user',
            'content' => $message,
        ],
    ], $systemPrompt, $model);
}

function gigachatAskWithMessages(array $messages, string $systemPrompt = '', string $model = ''): array
{
    $config = gigachatConfig();

    $accessToken = gigachatGetAccessToken($config);
    $model = $model !== '' ? $model : $config['model'];
    $systemPrompt = gigachatBuildSystemPrompt($systemPrompt);

    $payloadMessages = [];

    if ($systemPrompt !== '') {
        $payloadMessages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];
    }

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = (string) ($message['role'] ?? '');
        $content = (string) ($message['content'] ?? '');

        if ($role === '' || $content === '') {
            continue;
        }

        $payloadMessages[] = [
            'role' => $role,
            'content' => $content,
        ];
    }

    $response = gigachatHttpRequest(
        $config['base_url'] . '/chat/completions',
        [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        [
            'model' => $model,
            'messages' => $payloadMessages,
            'stream' => false,
        ],
        $config
    );

    $reply = trim((string) ($response['data']['choices'][0]['message']['content'] ?? ''));

    return [
        'reply' => $reply,
        'model' => (string) ($response['data']['model'] ?? $model),
        'usage' => is_array($response['data']['usage'] ?? null) ? $response['data']['usage'] : null,
    ];
}

function gigachatBuildSystemPrompt(string $systemPrompt = ''): string
{
    $templatePath = __DIR__ . '/gigachat_prompt.txt';
    $template = '';

    if (is_file($templatePath)) {
        $template = trim((string) file_get_contents($templatePath));
    }

    $systemPrompt = trim($systemPrompt);

    if ($template === '') {
        return $systemPrompt;
    }

    if ($systemPrompt === '') {
        return $template;
    }

    return $template . "\n\nДополнительная инструкция:\n" . $systemPrompt;
}

function gigachatGetAccessToken(array $config): string
{
    $response = gigachatHttpRequest(
        $config['auth_url'],
        [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'RqUID: ' . gigachatUuidV4(),
            'Authorization: ' . gigachatBasicAuth($config['auth_key']),
        ],
        'scope=' . rawurlencode($config['scope']),
        $config
    );

    $accessToken = trim((string) ($response['data']['access_token'] ?? ''));

    return $accessToken;
}

function gigachatHttpRequest(string $url, array $headers, array|string $body, array $config): array
{
    $ch = curl_init($url);

    $payload = is_array($body)
        ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $body;

    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60,
    ];

    if ($config['verify_ssl'] === false) {
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = 0;
    } elseif ($config['ca_bundle'] !== '') {
        $options[CURLOPT_CAINFO] = $config['ca_bundle'];
    }

    curl_setopt_array($ch, $options);

    $responseBody = curl_exec($ch);

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($responseBody, true);

    return [
        'status' => $status,
        'data' => $data,
    ];
}

function gigachatConfig(): array
{
    $config = getConfig();

    return [
        'auth_key' => trim((string) ($config['GIGACHAT_AUTH_KEY'] ?? '')),
        'auth_url' => rtrim((string) ($config['GIGACHAT_AUTH_URL'] ?? 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth'), '/'),
        'base_url' => rtrim((string) ($config['GIGACHAT_BASE_URL'] ?? 'https://gigachat.devices.sberbank.ru/api/v1'), '/'),
        'scope' => trim((string) ($config['GIGACHAT_SCOPE'] ?? 'GIGACHAT_API_PERS')),
        'model' => trim((string) ($config['GIGACHAT_MODEL'] ?? 'GigaChat-2')),
        'verify_ssl' => !in_array(strtolower(trim((string) ($config['GIGACHAT_VERIFY_SSL'] ?? 'true'))), ['0', 'false', 'off', 'no'], true),
        'ca_bundle' => trim((string) ($config['GIGACHAT_CA_BUNDLE'] ?? '')),
    ];
}

function gigachatBasicAuth(string $authKey): string
{
    $authKey = trim($authKey);

    return stripos($authKey, 'Basic ') === 0 ? $authKey : 'Basic ' . $authKey;
}

function gigachatUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
