<?php
// Файл: admin/approve-user.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Проверка прав администратора
require_admin();

// Обработка только POST запросов
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pending-users.php');
    exit;
}

// Проверка CSRF токена
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die('CSRF token validation failed');
}

$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($user_id <= 0) {
    $_SESSION['admin_message'] = '❌ Неверный ID пользователя';
    $_SESSION['admin_message_type'] = 'error';
    header('Location: pending-users.php');
    exit;
}

try {
    // Получаем данные пользователя
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['admin_message'] = '❌ Пользователь не найден';
        $_SESSION['admin_message_type'] = 'error';
        header('Location: pending-users.php');
        exit;
    }
    
    // Проверяем, что пользователь ещё не подтверждён
    if ($user['is_admin_approved'] == 1) {
        $_SESSION['admin_message'] = '⚠️ Этот пользователь уже подтверждён';
        $_SESSION['admin_message_type'] = 'warning';
        header('Location: pending-users.php');
        exit;
    }
    
    // Подтверждаем пользователя
    $stmt = $pdo->prepare('UPDATE users SET is_admin_approved = 1, approved_at = NOW() WHERE id = ?');
    $stmt->execute([$user_id]);
    
    // Отправляем уведомление пользователю
    $subject = 'Аккаунт подтверждён - NEIROLINKS';
    $message = "Здравствуйте!\n\n"
             . "Ваш аккаунт на портале NEIROLINKS был подтверждён администратором.\n\n"
             . "Роль: " . ($user['role'] === 'dealer' ? 'Дилер' : 'Агент') . "\n"
             . "Теперь вы можете войти в систему и получить доступ ко всем функциям.\n\n"
             . "Ссылка для входа: https://" . ($_SERVER['HTTP_HOST'] ?? 'nlpartner.ru') . "/auth/login.php\n\n"
             . "С уважением,\nКоманда NEIROLINKS";
    
    $subject_encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'nlpartner.ru');
    $headers = "From: NEIROLINKS <noreply@{$domain}>\r\n"
             . "Reply-To: support@{$domain}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    
    @mail($user['email'], $subject_encoded, $message, $headers);
    
    // Успешное сообщение
    $_SESSION['admin_message'] = "✅ Пользователь <strong>" . htmlspecialchars($user['company']) . "</strong> подтверждён! Уведомление отправлено на email.";
    $_SESSION['admin_message_type'] = 'success';
    
} catch (PDOException $e) {
    error_log("Approve User Error: " . $e->getMessage());
    $_SESSION['admin_message'] = '❌ Ошибка базы данных. Попробуйте позже.';
    $_SESSION['admin_message_type'] = 'error';
} catch (Exception $e) {
    error_log("Approve User Error: " . $e->getMessage());
    $_SESSION['admin_message'] = '❌ Произошла ошибка. Попробуйте позже.';
    $_SESSION['admin_message_type'] = 'error';
}

header('Location: pending-users.php');
exit;
?>