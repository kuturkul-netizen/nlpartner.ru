<?php
// Файл: admin.php (Корневой файл админки, если он существует отдельно от папки admin/)
// Примечание: Если ваша админка находится в папке /admin/index.php, этот файл может быть не нужен.
// Но если он есть в корне, вот исправленный код.

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Используем новую функцию проверки прав
require_admin();

$user = get_logged_user();

// Простая заглушка или редирект в основную папку админки
header('Location: /admin/index.php');
exit;
?>