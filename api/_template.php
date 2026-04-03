<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

// Замени метод на GET / POST / PUT и т.д.
api_require_method('GET');

// Если нужен body у POST-запроса:
// $data = api_input();

// Если нужна проверка авторизации:
// $user = api_current_user();
// if ($user === null) {
//     api_response(['message' => 'Нужна авторизация.'], 401);
// }

api_response([
    'message' => 'Это шаблон нового эндпоинта.',
    'example' => true,
]);
