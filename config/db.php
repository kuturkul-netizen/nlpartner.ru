<?php
/**
 * Подключение к базе данных
 * Данные берутся из файла .env
 */

// Загружаем переменные окружения
require_once __DIR__ . '/env_loader.php';

// Получаем настройки из .env
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

// Проверка наличия обязательных параметров
if (empty($db) || empty($user)) {
    // В продакшене лучше писать в лог, а не выводить на экран
    error_log("Ошибка: Не заданы DB_NAME или DB_USER в файле .env");
    die("Ошибка конфигурации базы данных. Обратитесь к администратору.");
}

// Формирование DSN строки
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Настройки PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Выбрасывать исключения при ошибках
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Возвращать массивы по умолчанию
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Использовать нативные подготовленные выражения
];

try {
    // Создание подключения
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Логирование реальной ошибки (видна только в логах сервера)
    error_log("PDO Connection Error: " . $e->getMessage());

    // Вывод безопасного сообщения для пользователя
    // Если нужно отладить подключение, временно раскомментируйте строку ниже:
    // die("Ошибка БД: " . $e->getMessage() . " (User: $user, DB: $db)");

    die("Ошибка подключения к базе данных. Проверьте логи или настройки .env.");
}