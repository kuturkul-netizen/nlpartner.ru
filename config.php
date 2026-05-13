<?php
// Файл: config.php

// 1. Определяем базовые пути
define('BASE_PATH', __DIR__);
define('SITE_URL', 'https://nlpartner.ru'); // Замените на ваш домен если нужно

// 2. Функция для парсинга .env файла вручную (чтобы не ставить зависимости)
function load_env($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Разделяем ключ и значение
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'"); // Убираем пробелы и кавычки
            
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
    return true;
}

// 3. Загружаем .env из корня
load_env(__DIR__ . '/.env');

// 4. Проверка обязательных настроек (для отладки)
if (!defined('DB_HOST')) {
    die('Ошибка: Не найдены настройки БД. Проверьте файл .env');
}

// 5. Подключение к базе данных
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // В продакшене лучше писать в лог, а не выводить ошибку пользователю
    error_log("DB Connection Error: " . $e->getMessage());
    die('Ошибка подключения к базе данных. Проверьте логи.');
}

// 6. Настройки администратора (если нет в .env, ставим дефолт)
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'info@nlpartner.ru');
}

// 7. Старт сессии (если еще не начата)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>