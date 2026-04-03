<?php
declare(strict_types=1);

setCorsHeaders();
configureSessionCookie();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

function setCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
}

function configureSessionCookie(): void
{
    $secure = shouldUseSecureCookie();
    $sameSite = $secure ? 'None' : 'Lax';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
}

function shouldUseSecureCookie(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower(trim(explode(':', $host)[0]));

    return $host === 'localhost' || str_ends_with($host, '.localhost');
}

function requireMethod(string $method): void
{
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($requestMethod !== strtoupper($method)) {
        sendJson([
            'message' => 'Метод запроса не поддерживается.',
        ], 405);
    }
}

function readJsonInput(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        sendJson([
            'message' => 'Некорректный JSON в теле запроса.',
        ], 400);
    }

    return $data;
}

function getAuthenticatedUser(): ?array
{
    if (!isset($_SESSION['username'], $_SESSION['login_time'])) {
        return null;
    }

    return [
        'id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => (string) $_SESSION['username'],
        'login_time' => date('d.m.Y H:i:s', (int) $_SESSION['login_time']),
        'login_timestamp' => (int) $_SESSION['login_time'],
    ];
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
