<?php
// Файл: auth/verify.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$error = '';
$success = false;
$user_data = null;

if (isset($_GET['token'])) {
    // ✅ ИСПРАВЛЕНИЕ: FILTER_SANITIZE_STRING устарел в PHP 8.1
    // Используем htmlspecialchars для безопасной очистки строки
    $token = htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8');
    
    try {
        // Проверяем токен в базе
        $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Обновляем статус: email подтвержден, токен удален
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verified_at = NOW() WHERE id = ?");
            $update->execute([$user['id']]);
            
            // Отправляем уведомление админу (функция должна быть в auth_helper.php)
            if (function_exists('notify_admin_new_user')) {
                notify_admin_new_user($user);
            }
            
            $success = true;
            $user_data = $user;
        } else {
            $error = "Неверный токен или адрес уже подтвержден.";
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "Ошибка базы данных. Попробуйте позже.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение регистрации | NEIROLINKS</title>
    
    <!-- 🔹 ФАВИКОНКИ -->
    <link rel="shortcut icon" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- Подключение основных стилей -->
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/style_auth.css">
    
    <!-- Стили специфичные для этой страницы -->
    <style>
        .status-box { 
            max-width: 500px; 
            margin: 20px auto; 
            padding: 30px; 
            border-radius: var(--radius, 16px); 
            box-shadow: var(--shadow, 0 10px 25px -5px rgb(0 0 0 / 0.1)); 
            text-align: center; 
            background: #fff; 
        }
        .status-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .status-box.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .status-box h2 { margin-top: 0; margin-bottom: 15px; }
        .btn-link { 
            display: inline-block; 
            margin-top: 20px; 
            padding: 10px 20px; 
            background: var(--primary, #0f172a); 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-link:hover { background: #1e293b; transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <?php if ($success): ?>
            <div class="card status-box success">
                <h2>✅ Email подтвержден!</h2>
                <p>Спасибо, <?= htmlspecialchars($user_data['company'] ?? 'Партнер') ?>.</p>
                <p>Ваша заявка принята и передана администратору на проверку.</p>
                <p><strong>Следующий шаг:</strong> Ожидайте письма с подтверждением доступа. Обычно это занимает до 24 часов.</p>
                <a href="/auth/login.php" class="btn-link">Перейти ко входу</a>
            </div>
        <?php else: ?>
            <div class="card status-box error">
                <h2>⚠️ Ошибка подтверждения</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="/auth/register.php" class="btn-link">Попробовать снова</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>