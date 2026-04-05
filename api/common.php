<?php
declare(strict_types=1);

require_once __DIR__ . '/../connect.php';

api_start();

function api_start(): void
{
    api_set_cors();
    api_configure_session_cookie();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    session_start();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function api_set_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

function api_configure_session_cookie(): void
{
    $secure = api_is_localhost() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
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

function api_is_localhost(): bool
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower(trim(explode(':', $host)[0]));

    return $host === 'localhost' || str_ends_with($host, '.localhost');
}

function api_require_method(string $method): void
{
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($requestMethod !== strtoupper($method)) {
        api_response([
            'message' => 'Метод запроса не поддерживается.',
        ], 405);
    }
}

function api_input(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        api_response([
            'message' => 'Некорректный JSON.',
        ], 400);
    }

    return $data;
}

function api_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_current_user(): ?array
{
    if (!isset($_SESSION['username'], $_SESSION['login_time'])) {
        return null;
    }

    return [
        'id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => (string) $_SESSION['username'],
        'login_time' => date('d.m.Y H:i:s', (int) $_SESSION['login_time']),
    ];
}

function api_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['login_time'] = time();
}

function api_logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function api_get_accounts_columns(mysqli $link): array
{
    $result = mysqli_query($link, 'SHOW COLUMNS FROM accounts');

    if ($result === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось получить структуру таблицы accounts.',
        ], 500);
    }

    $columns = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $field = (string) ($row['Field'] ?? '');

        if ($field !== '') {
            $columns[$field] = $row;
        }
    }

    mysqli_free_result($result);

    return $columns;
}

function api_bind_params(mysqli_stmt $stmt, string $types, array $values): void
{
    $references = [];

    foreach ($values as $index => $value) {
        $references[$index] = &$values[$index];
    }

    array_unshift($references, $types);

    if (!call_user_func_array([$stmt, 'bind_param'], $references)) {
        mysqli_stmt_close($stmt);
        api_response([
            'message' => 'Не удалось привязать параметры запроса.',
        ], 500);
    }
}

function api_fetch_single_row(mysqli_stmt $stmt, mysqli $link): ?array
{
    $result = mysqli_stmt_get_result($stmt);

    if ($result === false) {
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось получить результат запроса к базе данных.',
        ], 500);
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return $row === null ? null : $row;
}

function api_placeholder_email(string $username): string
{
    $localPart = strtolower($username);
    $localPart = preg_replace('/[^a-z0-9._-]+/i', '-', $localPart) ?? '';
    $localPart = trim($localPart, '.-');

    if ($localPart === '') {
        $localPart = 'user';
    }

    return $localPart . '+' . substr(sha1($username), 0, 8) . '@placeholder.local';
}

function api_find_user_by_username(string $username): ?array
{
    $link = dbConnect();
    $stmt = mysqli_prepare($link, 'SELECT id, name FROM users WHERE name = ? LIMIT 1');

    if ($stmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос к базе данных.',
        ], 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $username);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось выполнить запрос к базе данных.',
        ], 500);
    }

    $row = api_fetch_single_row($stmt, $link);

    mysqli_stmt_close($stmt);
    mysqli_close($link);

    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'username' => (string) ($row['name'] ?? ''),
    ];
}

function api_find_user_for_login(string $login): ?array
{
    $link = dbConnect();
    $stmt = mysqli_prepare($link, 'SELECT id, name, password FROM users WHERE name = ? LIMIT 1');

    if ($stmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос к базе данных.',
        ], 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $login);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось выполнить запрос к базе данных.',
        ], 500);
    }

    $row = api_fetch_single_row($stmt, $link);

    mysqli_stmt_close($stmt);
    mysqli_close($link);

    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'username' => (string) ($row['name'] ?? ''),
        'password' => (string) ($row['password'] ?? ''),
    ];
}

function api_register(string $username, string $password, string $repPassword): void
{
    $link = dbConnect();
    $username = trim($username);
    $password = (string) $password;
    $repPassword = (string) $repPassword;

    if ($username === '' || $password === '' || $repPassword === '') {
        mysqli_close($link);
        api_response([
            'message' => 'Заполните логин и оба поля пароля.',
        ], 400);
    }

    if ($password !== $repPassword) {
        mysqli_close($link);
        api_response([
            'message' => 'Пароли должны совпадать.',
        ], 400);
    }

    $checkStmt = mysqli_prepare(
        $link,
        'SELECT id FROM users WHERE name = ? LIMIT 1'
    );

    if ($checkStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос проверки пользователя.',
        ], 500);
    }

    mysqli_stmt_bind_param($checkStmt, 's', $username);

    if (!mysqli_stmt_execute($checkStmt)) {
        mysqli_stmt_close($checkStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось выполнить запрос проверки пользователя.',
        ], 500);
    }

    $existingUser = api_fetch_single_row($checkStmt, $link);
    mysqli_stmt_close($checkStmt);

    if ($existingUser !== null) {
        mysqli_close($link);
        api_response([
            'message' => 'Пользователь с таким логином уже существует.',
        ], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = mysqli_prepare($link, 'INSERT INTO users (id, name, password) VALUES (NULL, ?, ?)');

    if ($insertStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос на создание пользователя.',
        ], 500);
    }

    mysqli_stmt_bind_param($insertStmt, 'ss', $username, $passwordHash);

    if (!mysqli_stmt_execute($insertStmt)) {
        mysqli_stmt_close($insertStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось создать пользователя.',
        ], 500);
    }

    $newUserId = mysqli_insert_id($link);

    mysqli_stmt_close($insertStmt);

    api_login_user([
        'id' => $newUserId,
        'username' => $username,
    ]);

    mysqli_close($link);

    api_response([
        'message' => 'Регистрация успешна.',
        'user' => [
            'id' => $newUserId,
            'username' => $username,
        ],
    ], 201);
}
