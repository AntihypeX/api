<?php
declare(strict_types=1);

require_once __DIR__ . '../common.php';

api_require_method('POST');

$payload = api_input();

$username = trim((string) ($payload['username'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$birthdate = trim((string) ($payload['birthdate'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$repPassword = (string) ($payload['repPassword'] ?? '');

api_register($username, $email, $birthdate, $password, $repPassword);

?>