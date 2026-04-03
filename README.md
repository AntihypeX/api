# BackEnd API README

## Что это

Это простой PHP backend для работы с отдельным frontend через API.

Сейчас backend умеет:

- авторизовать пользователя через `POST /api/login.php`
- проверять текущую сессию через `GET /api/me.php`
- выполнять выход через `POST /api/logout.php`

Frontend и backend считаются разными серверами, поэтому запросы идут через `fetch()` и CORS.

## Текущая структура

```text
BackEnd/
|-- .env
|-- connect.php
|-- auth.php
|-- me.php
|-- logout.php
|-- README.md
`-- api/
    |-- common.php
    |-- login.php
    |-- me.php
    |-- logout.php
    `-- _template.php
```

### Назначение файлов

- `.env` - настройки подключения к базе данных
- `connect.php` - функция подключения к MySQL
- `api/common.php` - общий шаблон API: CORS, session, JSON, helper-функции
- `api/login.php` - логин
- `api/me.php` - проверка текущего пользователя
- `api/logout.php` - выход
- `api/_template.php` - шаблон нового endpoint
- `auth.php`, `me.php`, `logout.php` - обертки для совместимости, которые просто подключают API-файлы

## Как запустить

### 1. Настроить домены

В твоем текущем frontend в файле `FrontEnd/api.js` указан адрес:

```js
const API_BASE_URL = 'http://api.localhost/api';
```

Значит backend должен открываться по домену `http://api.localhost`.

Рекомендуемая схема:

- frontend: `http://front.localhost`
- backend API: `http://api.localhost`

Если у тебя другой домен, поменяй `API_BASE_URL` во frontend.

### 2. Настроить `.env`

Файл `.env` в папке `BackEnd` должен содержать настройки базы:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=hakaton
DB_USER=root
DB_PASS=
```

Если логин, пароль или имя БД другие, измени значения.

### 3. Создать таблицу пользователей

Сейчас логин ищет пользователя в таблице `accounts` по полю `username`.

Минимальный SQL:

```sql
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL
);
```

Добавить тестового пользователя можно так:

```sql
INSERT INTO accounts (username, password)
VALUES ('ivan', NULL);
```

Важно:

- сейчас backend проверяет не поле `password` в базе
- сейчас backend пускает пользователя, если он найден в таблице и введен пароль `123`

То есть логика сейчас такая:

1. Пользователь вводит `username` и `password`
2. `login.php` ищет `username` в таблице `accounts`
3. Если пользователь найден, backend проверяет пароль
4. Если пароль равен `123`, создается сессия

Это сделано как временный упрощенный шаблон для разработки.

## Как работает текущая API

### POST `/api/login.php`

Тело запроса:

```json
{
  "username": "ivan",
  "password": "123"
}
```

Успешный ответ:

```json
{
  "message": "Авторизация успешна.",
  "user": {
    "id": 1,
    "username": "ivan",
    "login_time": "03.04.2026 15:10:00"
  }
}
```

Ошибки:

- `422` - если не передан логин или пароль
- `404` - если пользователь не найден
- `401` - если пароль неверный

### GET `/api/me.php`

Проверяет, есть ли активная сессия.

Успешный ответ:

```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "login_time": "03.04.2026 15:10:00"
  }
}
```

Ошибка:

- `401` - если пользователь не авторизован

### POST `/api/logout.php`

Удаляет сессию пользователя.

Ответ:

```json
{
  "message": "Вы вышли из аккаунта."
}
```

## Как настроить frontend под эту API

Frontend должен работать не через обычные HTML-формы с `action`, а через JavaScript и `fetch()`.

Текущий шаблон frontend уже рассчитан именно на такой способ работы.

### Базовая идея

Frontend:

- показывает форму входа
- отправляет запросы на backend через `fetch`
- получает JSON-ответы
- сам решает, когда делать редирект, что показывать и что скрывать

Backend:

- принимает запросы
- возвращает JSON
- хранит авторизацию в PHP session

### Что должно быть на frontend

Минимально нужны:

- страница входа, например `index.html`
- страница профиля, например `profile.html`
- файл `api.js` для всех запросов к backend

### Как подключить frontend к backend

В файле `FrontEnd/api.js` должен быть указан адрес backend API:

```js
const API_BASE_URL = 'http://api.localhost/api';
```

Если backend будет работать по другому домену, здесь нужно поменять только эту строку.

Примеры:

- `http://api.localhost/api`
- `http://localhost/hakaton/BackEnd/api`
- `http://my-api.test/api`

### Важное правило

Frontend нужно открывать через HTTP-адрес, а не двойным кликом по HTML-файлу.

Правильно:

- `http://front.localhost/index.html`

Неправильно:

- `file:///C:/.../index.html`

Если открыть frontend через `file://`, cookie и CORS будут работать некорректно.

## Как должен выглядеть `api.js`

Лучше держать все запросы в одном месте.

Сейчас frontend использует такой подход:

1. Есть одна общая функция `request()`
2. Есть объект `api`
3. В объекте `api` лежат методы для конкретных endpoint

Пример:

```js
const api = {
    login(username, password) {
        return request('/login.php', {
            method: 'POST',
            body: { username, password },
        });
    },

    me() {
        return request('/me.php');
    },

    logout() {
        return request('/logout.php', {
            method: 'POST',
        });
    },
};
```

### Почему это удобно

- все URL лежат в одном месте
- легко менять backend-домен
- легко добавлять новые методы
- страницы не знают деталей `fetch`

## Что важно в запросах frontend

### 1. Все запросы должны ожидать JSON

Backend отвечает JSON, поэтому frontend должен работать именно с ним.

### 2. Для сессий обязательно нужен `credentials: 'include'`

Пример:

```js
const response = await fetch(`${API_BASE_URL}/login.php`, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify({
        username,
        password,
    }),
});
```

Если убрать `credentials: 'include'`, то backend может авторизовать пользователя, но браузер не сохранит или не отправит cookie сессии, и `me.php` будет возвращать `401`.

### 3. Лучше держать body только в JSON

Для текущей API frontend должен отправлять данные так:

```json
{
  "username": "ivan",
  "password": "123"
}
```

## Как связать страницу входа с API

На странице входа должна быть форма с полями:

- `username`
- `password`

Лучше не отправлять форму обычным способом.

Нужно:

1. Повесить обработчик на `submit`
2. Сделать `event.preventDefault()`
3. Прочитать данные из формы
4. Вызвать `api.login(username, password)`
5. При успехе перейти на `profile.html`
6. При ошибке показать сообщение пользователю

Пример:

```js
form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const username = String(formData.get('username') || '').trim();
    const password = String(formData.get('password') || '');

    try {
        await api.login(username, password);
        window.location.href = 'profile.html';
    } catch (error) {
        errorElement.textContent = error.message;
    }
});
```

## Как связать страницу профиля с API

На странице профиля frontend должен при загрузке проверить текущую сессию через:

- `api.me()`

Логика такая:

1. Страница загружается
2. Frontend вызывает `api.me()`
3. Если backend вернул пользователя, показываем профиль
4. Если backend вернул `401`, отправляем пользователя обратно на страницу входа

Пример:

```js
try {
    const data = await api.me();
    usernameElement.textContent = data.user.username;
} catch (error) {
    if (error.status === 401) {
        window.location.href = 'index.html';
    }
}
```

## Как сделать кнопку выхода

На странице профиля должна быть кнопка, которая вызывает:

- `api.logout()`

После успешного выхода frontend обычно делает редирект на страницу входа.

Пример:

```js
logoutButton.addEventListener('click', async () => {
    await api.logout();
    window.location.href = 'index.html';
});
```

## Как добавить новый frontend-метод под новый backend endpoint

Допустим, ты добавил на backend новый файл:

- `BackEnd/api/posts.php`

Тогда на frontend нужно:

### 1. Добавить метод в `FrontEnd/api.js`

```js
const api = {
    login(username, password) {
        return request('/login.php', {
            method: 'POST',
            body: { username, password },
        });
    },

    me() {
        return request('/me.php');
    },

    logout() {
        return request('/logout.php', {
            method: 'POST',
        });
    },

    posts() {
        return request('/posts.php');
    },
};
```

### 2. Вызвать этот метод на нужной странице

```js
const data = await api.posts();
console.log(data.posts);
```

### 3. Отрисовать результат на странице

То есть frontend расширяется в таком порядке:

1. backend endpoint
2. метод в `api.js`
3. вызов метода на странице
4. вывод данных в HTML

## Как добавить POST-метод на frontend

Если новый backend endpoint принимает данные, шаблон будет такой:

```js
createPost(title, text) {
    return request('/posts-create.php', {
        method: 'POST',
        body: {
            title,
            text,
        },
    });
}
```

А затем:

```js
await api.createPost('Новый пост', 'Текст поста');
```

## Если frontend будет не на чистом HTML, а на framework

Эта API подходит не только для обычных HTML-страниц, но и для:

- Vue
- React
- Nuxt
- Next.js
- любой другой frontend

Принцип тот же:

- заводишь отдельный API client
- все запросы отправляешь через него
- в запросах оставляешь `credentials: 'include'`
- отображение данных делаешь уже средствами framework

Например, в React или Vue лучше сделать отдельный модуль:

- `src/api.js`
- `src/services/api.js`
- `src/lib/api.js`

И уже из компонентов вызывать:

```js
const user = await api.me();
```

## Рекомендуемый шаблон frontend для будущего роста

Если frontend будет расти, удобно держать такую структуру:

```text
FrontEnd/
|-- index.html
|-- profile.html
|-- api.js
|-- js/
|   |-- login-page.js
|   |-- profile-page.js
|   `-- posts-page.js
`-- css/
    `-- style.css
```

Или, если проект станет больше:

```text
FrontEnd/
|-- src/
|   |-- api/
|   |   `-- api.js
|   |-- pages/
|   |-- components/
|   `-- utils/
`-- public/
```

### Главное правило

Не смешивай:

- разметку страницы
- логику запросов
- отрисовку больших блоков интерфейса

Лучше:

- API-запросы держать в `api.js`
- обработку страницы держать в отдельных JS-файлах
- HTML использовать только для структуры

## Главный файл для расширения: `api/common.php`

Вся общая логика лежит в `api/common.php`.

Там уже есть готовые функции:

- `api_start()` - общий старт API
- `api_set_cors()` - CORS-заголовки
- `api_configure_session_cookie()` - cookie для сессии
- `api_require_method('GET' | 'POST')` - проверка HTTP-метода
- `api_input()` - чтение JSON body
- `api_response([...], 200)` - JSON-ответ
- `api_current_user()` - текущий авторизованный пользователь
- `api_login_user($user)` - создать сессию
- `api_logout_user()` - удалить сессию
- `api_find_user_by_username($username)` - найти пользователя в БД

### Правило расширения

Если какая-то логика может пригодиться больше чем в одном endpoint, лучше выносить ее в `api/common.php`.

Примеры:

- поиск пользователя по `id`
- проверка прав доступа
- работа с постами, комментариями, заявками
- общая валидация входных данных
- общие SQL helper-функции

## Как добавить новый endpoint

Самый простой путь:

1. Скопировать файл `api/_template.php`
2. Переименовать его, например, в `api/posts.php`
3. Заменить логику внутри на нужную
4. Добавить вызов этого endpoint во frontend

### Пример: новый endpoint `api/posts.php`

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

api_require_method('GET');

$user = api_current_user();

if ($user === null) {
    api_response([
        'message' => 'Нужна авторизация.'
    ], 401);
}

api_response([
    'posts' => [
        ['id' => 1, 'title' => 'Первый пост'],
        ['id' => 2, 'title' => 'Второй пост']
    ]
]);
```

## Как добавить новую функцию в backend

Допустим, в будущем тебе нужна работа не только с авторизацией, но и с:

- профилем пользователя
- товарами
- новостями
- постами
- заявками
- сообщениями

Тогда схема будет такой:

### 1. Добавить endpoint

Например:

- `api/profile.php`
- `api/products.php`
- `api/orders.php`
- `api/messages.php`

### 2. Если нужна общая логика, вынести в `common.php`

Например:

```php
function api_find_user_by_id(int $id): ?array
{
    $link = dbConnect();
    $stmt = mysqli_prepare($link, 'SELECT id, username FROM accounts WHERE id = ? LIMIT 1');

    if ($stmt === false) {
        mysqli_close($link);
        api_response(['message' => 'Ошибка базы данных.'], 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $userId, $username);

    $user = null;

    if (mysqli_stmt_fetch($stmt)) {
        $user = [
            'id' => $userId,
            'username' => $username,
        ];
    }

    mysqli_stmt_close($stmt);
    mysqli_close($link);

    return $user;
}
```

### 3. Подключить новый endpoint на frontend

В `FrontEnd/api.js` добавить новый метод в объект `api`.

Пример:

```js
const api = {
    login(username, password) {
        return request('/login.php', {
            method: 'POST',
            body: { username, password },
        });
    },

    me() {
        return request('/me.php');
    },

    logout() {
        return request('/logout.php', {
            method: 'POST',
        });
    },

    posts() {
        return request('/posts.php');
    },
};
```

После этого на frontend можно вызывать:

```js
const data = await api.posts();
```

## Как изменять текущую авторизацию

Сейчас пароль специально упрощен до проверки:

```php
if ($password !== '123') {
    api_response([
        'message' => 'Неверный пароль.',
    ], 401);
}
```

Это удобно для тестирования, но не для реального проекта.

### Если позже захочешь нормальную авторизацию

Нужно будет:

1. Хранить `password_hash()` в базе
2. В `login.php` выбирать поле `password`
3. Проверять пароль через `password_verify()`

Пример будущей логики:

```php
$stmt = mysqli_prepare($link, 'SELECT id, username, password FROM accounts WHERE username = ? LIMIT 1');

if (!password_verify($password, $user['password'])) {
    api_response([
        'message' => 'Неверный пароль.',
    ], 401);
}
```

## Полезные правила для будущего развития

### 1. Один endpoint - одна задача

Хорошо:

- `login.php` - только вход
- `me.php` - только проверка сессии
- `logout.php` - только выход

Плохо:

- один огромный `api.php`, который делает всё подряд

### 2. Общие функции - в `common.php`

Если код повторяется в двух местах, выноси его в `common.php`.

### 3. Все ответы - только JSON

Для API лучше всегда возвращать:

- данные
- сообщение
- HTTP-статус

Не смешивай API и HTML в одном endpoint.

### 4. Проверяй метод запроса

Для каждого endpoint сразу задавай:

```php
api_require_method('GET');
```

или

```php
api_require_method('POST');
```

### 5. Для protected-ручек сначала проверяй пользователя

Шаблон:

```php
$user = api_current_user();

if ($user === null) {
    api_response([
        'message' => 'Нужна авторизация.'
    ], 401);
}
```

## Частые проблемы

### После логина frontend перекидывает обратно на `index.html`

Проверь:

- frontend открыт по HTTP, а не через `file://`
- `API_BASE_URL` указывает на правильный backend
- запросы идут с `credentials: 'include'`
- backend реально отвечает с нужного домена
- браузер сохраняет cookie сессии

### `404 Пользователь не найден`

Проверь:

- существует ли таблица `accounts`
- есть ли в ней нужный `username`

### Ошибка подключения к БД

Проверь:

- значения в `.env`
- поднят ли MySQL
- существует ли база `hakaton`

## Рекомендуемый порядок работы дальше

Если будешь расширять проект, удобнее идти так:

1. Создать endpoint на backend
2. Проверить его отдельно
3. Добавить метод в `FrontEnd/api.js`
4. Вызвать этот метод на нужной странице frontend
5. Только потом усложнять UI

## Итог

Сейчас backend собран как простой шаблон для роста:

- авторизация уже работает
- общая логика вынесена
- новый endpoint можно добавить быстро
- frontend уже готов работать с новыми API-методами

Если потом захочешь, можно следующим шагом привести API к более "проектной" структуре, например:

- `api/auth/login.php`
- `api/auth/logout.php`
- `api/auth/me.php`
- `api/users/list.php`
- `api/posts/list.php`
- `api/posts/create.php`

Но для текущего этапа текущая структура уже удобна и достаточно проста для редактирования.
