<?php

function loadEnv(string $path): array
{
    $data = [];

    if (!file_exists($path)) {
        die('.env не найден');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        $data[$key] = $value;
    }

    return $data;
}

function getConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = loadEnv(__DIR__ . '/.env');
    }

    return $config;
}

function dbConnect(): mysqli
{
    $config = getConfig();

    $host = $config['DB_HOST'] ?? '127.0.0.1';
    $port = (int) ($config['DB_PORT'] ?? 3306);
    $name = $config['DB_NAME'] ?? '';
    $user = $config['DB_USER'] ?? '';
    $pass = $config['DB_PASS'] ?? '';

    $link = mysqli_connect($host, $user, $pass, $name, $port);

    if (!$link) {
        die('Ошибка подключения к БД: ' . mysqli_connect_error());
    }

    mysqli_set_charset($link, 'utf8mb4');

    return $link;
}

