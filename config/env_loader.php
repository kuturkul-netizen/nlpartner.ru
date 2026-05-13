<?php
/**
 * Загрузчик переменных окружения из файла .env
 */
function loadEnv($path = null) {
    if (!$path) {
        $path = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($path)) {
        // Если .env нет, пробуем загрузить из стандартных переменных окружения сервера
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
            $value = trim($value);

            // Убираем кавычки если есть
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    return true;
}

// Автоматическая загрузка при подключении файла
loadEnv();