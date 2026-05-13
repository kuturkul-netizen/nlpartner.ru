<?php
// Файл: includes/auth_helper.php

// 1. Подключаем конфиг
if (!file_exists(__DIR__ . '/../config.php')) {
    die('Ошибка: Файл config.php не найден в корне сайта.');
}
require_once __DIR__ . '/../config.php';

// 2. Подключаем классы PHPMailer вручную (так как use нельзя использовать внутри if)
// Мы проверяем существование файлов, чтобы избежать фатальных ошибок, если библиотеки нет
$mailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
$smtpPath   = __DIR__ . '/PHPMailer/src/SMTP.php';
$excPath    = __DIR__ . '/PHPMailer/src/Exception.php';

if (file_exists($mailerPath) && file_exists($smtpPath) && file_exists($excPath)) {
    require_once $excPath;
    require_once $smtpPath;
    require_once $mailerPath;
    // Теперь используем полные имена классов или алиасы, если нужно
    // Но для простоты будем использовать полные имена или проверять класс
    $phpMailerExists = true;
} else {
    $phpMailerExists = false;
}

/**
 * Проверка: авторизован ли пользователь
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Получение данных текущего пользователя
 * Переименовано из get_current_user() во избежание конфликта с системной функцией PHP
 */
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    global $pdo;
    if (!isset($pdo)) return null;

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Алиас для обратной совместимости
 */
function get_current_user_data() {
    return get_logged_user();
}

/**
 * Проверка: является ли пользователь администратором
 */
function is_admin() {
    $user = get_logged_user();
    return $user && ($user['role'] === 'admin');
}

/**
 * Требует права администратора. Если нет - редирект или ошибка.
 */
function require_admin() {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit;
    }
    if (!is_admin()) {
        http_response_code(403);
        die('<h1>Доступ запрещен</h1><p>У вас нет прав администратора.</p><a href="/index.php">На главную</a>');
    }
}

/**
 * СТАРАЯ ФУНКЦИЯ (для обратной совместимости)
 * Позволяет использовать старый синтаксис requireAuth(['admin'])
 */
function requireAuth($roles = []) {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit;
    }

    $user = get_logged_user();
    
    if (!empty($roles)) {
        if (!in_array($user['role'], $roles) && $user['role'] !== 'admin') {
             http_response_code(403);
             die('<h1>Доступ запрещен</h1><p>Недостаточно прав.</p>');
        }
    }
}

/**
 * Отправка email через PHPMailer
 */
function send_email($to, $subject, $body) {
    global $phpMailerExists;
    
    if (!$phpMailerExists) {
        error_log("PHPMailer files not found.");
        return false;
    }

    // Используем полное имя класса, так как 'use' был бы глобальным, а мы в функции
    // Или создаем алиас внутри функции, если нужно, но проще через полное имя или new \PHPMailer\PHPMailer\PHPMailer
    // Однако, так как мы подключили файлы выше, класс доступен по полному пути
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.yandex.ru';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
        $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;

        $fromEmail = defined('SMTP_USER') ? SMTP_USER : 'no-reply@nlpartner.ru';
        $mail->setFrom($fromEmail, 'NEIROLINKS Partner');
        
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Генерация случайного токена
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Уведомление админа о новом пользователе
 */
function notify_admin_new_user($user) {
    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@nlpartner.ru';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://nlpartner.ru';
    
    $subject = "Новая заявка на подтверждение: " . ($user['company'] ?? 'Без названия');
    $body = "
    <h2>Новый партнер ожидает подтверждения</h2>
    <p><strong>Компания:</strong> " . htmlspecialchars($user['company'] ?? '-') . "</p>
    <p><strong>Роль:</strong> " . htmlspecialchars($user['role'] ?? '-') . "</p>
    <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
    <p><strong>Телефон:</strong> " . htmlspecialchars($user['phone'] ?? '-') . "</p>
    <hr>
    <p>Пожалуйста, проверьте данные в админ-панели:</p>
    <a href='{$siteUrl}/admin/pending-users.php' style='display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:5px;'>Перейти к модерации</a>
    ";
    
    return send_email($adminEmail, $subject, $body);
}

/**
 * Уведомление пользователю об одобрении
 */
function notify_user_approved($user) {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://nlpartner.ru';
    $subject = "Ваш аккаунт партнера подтвержден!";
    $body = "
    <h2>Добро пожаловать в команду NEIROLINKS!</h2>
    <p>Ваша регистрация в качестве <strong>" . htmlspecialchars($user['role']) . "</strong> подтверждена администратором.</p>
    <p>Теперь вы имеете полный доступ к личному кабинету.</p>
    <a href='{$siteUrl}/auth/login.php' style='display:inline-block;padding:10px 20px;background:#28a745;color:#fff;text-decoration:none;border-radius:5px;'>Войти в кабинет</a>
    ";
    
    return send_email($user['email'], $subject, $body);
}

/**
 * Уведомление пользователю об отклонении
 */
function notify_user_rejected($user, $comment = '') {
    $reason = $comment ? $comment : "Не указано";
    $subject = "Статус вашей регистрации";
    $body = "
    <h2>Регистрация отклонена</h2>
    <p>К сожалению, ваша заявка на роль <strong>" . htmlspecialchars($user['role']) . "</strong> была отклонена администратором.</p>
    <p><strong>Причина:</strong> " . htmlspecialchars($reason) . "</p>
    <p>Если вы считаете это ошибкой, свяжитесь с поддержкой.</p>
    ";
    
    return send_email($user['email'], $subject, $body);
}

/**
 * Повторная отправка уведомления админу
 */
function resend_admin_notification($user) {
    return notify_admin_new_user($user);
}
?>