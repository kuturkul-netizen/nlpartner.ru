<?php
// 1. Запускаем сессию, чтобы получить к ней доступ
session_start();

// 2. Очищаем все переменные сессии
$_SESSION = [];

// 3. Уничтожаем куку сессии (для безопасности)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Уничтожаем сессию полностью
session_destroy();

// 5. Перенаправляем пользователя на страницу входа
header('Location: /auth/login.php');
exit;