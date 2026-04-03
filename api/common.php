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

function api_login_email(array $email): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $email['id'];
    $_SESSION['emailCheck'] = (string) $email['EmailCheck'];
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

function api_find_user_by_username(string $username): ?array
{
    $link = dbConnect();
    $stmt = mysqli_prepare($link, 'SELECT id, username, email FROM accounts WHERE username = ? LIMIT 1');

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

    $user = null;
    mysqli_stmt_bind_result($stmt, $userId, $dbUsername);

    if (mysqli_stmt_fetch($stmt)) {
        $user = [
            'id' => $userId,
            'username' => $dbUsername,
        ];
    }

    mysqli_stmt_close($stmt);
    mysqli_close($link);

    return $user;
};

function api_find_email_by_emailCheck(string $emailCheck): ?array
{
    $link = dbConnect();
    $stmt = mysqli_prepare($link, 'SELECT id, username, email FROM accounts WHERE email = ? LIMIT 1');

    if ($stmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос к базе данных.',
        ], 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $emailCheck);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось выполнить запрос к базе данных.',
        ], 500);
    }

    $email = null;
    mysqli_stmt_bind_result($stmt, $userId, $dbEmail);

    if (mysqli_stmt_fetch($stmt)) {
        $user = [
            'id' => $userId,
            'emailCheck' => $dbEmail,
        ];
    }

    mysqli_stmt_close($stmt);
    mysqli_close($link);

    return $email;
}

function api_calculate_age(string $birthdate): ?int
{
    $birthdate = trim($birthdate);

    $date = DateTime::createFromFormat('d.m.Y', $birthdate);
    $errors = DateTime::getLastErrors();

    if (
        $date === false ||
        $errors['warning_count'] > 0 ||
        $errors['error_count'] > 0
    ) {
        return null;
    }

    $today = new DateTime('today');
    $age = $today->diff($date)->y;

    if ($age < 0 || $age > 120) {
        return null;
    }

    return $age;
}

function api_register($username, $email, $age, $password, $repPassword): void
{
    api_require_method('POST');

    $link = dbConnect();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $repPassword = (string) ($_POST['repPassword'] ?? '');

    if ($username === '' || $email === '' || $birthdate === '' || $password === '') {
        mysqli_close($link);
        api_response([
            'message' => 'Заполните все обязательные поля.',
        ], 400);
    }

    if ($password !== $repPassword) {
        mysqli_close($link);
        api_response([
            'message' => 'Пароли должны быть одинаковыми',
        ], 400);
    }

    $age = api_calculate_age($birthdate);

    if ($age === null) {
        mysqli_close($link);
        api_response([
            'message' => 'Некорректная дата рождения. Используйте формат dd.mm.yyyy.',
        ], 400);
    }

    $checkStmt = mysqli_prepare(
        $link,
        'SELECT id, username, email FROM accounts WHERE email = ? OR username = ? LIMIT 1'
    );

    if ($checkStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос проверки пользователя.',
        ], 500);
    }

    mysqli_stmt_bind_param($checkStmt, 'ss', $email, $username);

    if (!mysqli_stmt_execute($checkStmt)) {
        mysqli_stmt_close($checkStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось выполнить запрос проверки пользователя.',
        ], 500);
    }

    $checkResult = mysqli_stmt_get_result($checkStmt);

    if ($checkResult === false) {
        mysqli_stmt_close($checkStmt);
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось получить результат проверки.',
        ], 500);
    }

    if (mysqli_num_rows($checkResult) > 0) {
        $existingUser = mysqli_fetch_assoc($checkResult);

        mysqli_free_result($checkResult);
        mysqli_stmt_close($checkStmt);
        mysqli_close($link);

        $message = 'Пользователь уже существует.';

        if (($existingUser['email'] ?? '') === $email) {
            $message = 'Пользователь с таким email уже существует.';
        } elseif (($existingUser['username'] ?? '') === $username) {
            $message = 'Пользователь с таким username уже существует.';
        }

        api_response([
            'message' => $message,
        ], 409);
    }

    mysqli_free_result($checkResult);
    mysqli_stmt_close($checkStmt);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = mysqli_prepare(
        $link,
        'INSERT INTO accounts (username, email, age, password) VALUES (?, ?, ?, ?)'
    );

    if ($insertStmt === false) {
        mysqli_close($link);
        api_response([
            'message' => 'Не удалось подготовить запрос на создание пользователя.',
        ], 500);
    }

    mysqli_stmt_bind_param($insertStmt, 'ssis', $username, $email, $age, $passwordHash);

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
        'redirect' => '/welcome.php',
        'user' => [
            'id' => $newUserId,
            'username' => $username,
            'email' => $email,
            'age' => $age,
        ],
    ], 201);
}