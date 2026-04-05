<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

// Temporary debug output for API errors.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

api_require_method('POST');

$payload = api_input();

$login = trim((string) ($payload['login'] ?? $payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$password2 = (string) ($payload['password2'] ?? $payload['repPassword'] ?? '');

api_register($login, $password, $password2);
