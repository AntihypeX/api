<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

api_require_method('POST');

$payload = api_input();

$login = trim((string) ($payload['login'] ?? $payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$password2 = (string) ($payload['password2'] ?? $payload['repPassword'] ?? '');

api_register($login, $password, $password2);
