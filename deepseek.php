<?php

require_once __DIR__ . '/connect.php';
function deepseekConfig(): array
{
    $config = getConfig();

    return [
        'api_key' => $config['DEEPSEEK_API_KEY'] ?? '',
        'base_url' => $config['DEEPSEEK_BASE_URL'] ?? 'https://api.deepseek.com',
    ];
}
?>